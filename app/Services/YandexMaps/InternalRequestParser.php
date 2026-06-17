<?php

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\NormalizedUrlDto;
use App\DTO\YandexMaps\OrganizationDto;
use App\DTO\YandexMaps\ReviewDto;
use App\Exceptions\YandexMaps\BlockedException;
use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\InvalidUrlException;
use App\Exceptions\YandexMaps\ParserTimeoutException;
use App\Exceptions\YandexMaps\UnavailableException;
use DateTimeImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class InternalRequestParser implements Parser
{
    public function __construct(
        private readonly UrlNormalizer $urlNormalizer,
        private readonly PageStateExtractor $stateExtractor,
    ) {}

    /**
     * Parses a Yandex Maps organization card URL into organization and review data
     *
     * @throws InvalidUrlException
     * @throws UnavailableException
     * @throws BlockedException
     * @throws ChangedSchemaException
     * @throws ParserTimeoutException
     */
    public function parse(string $url): OrganizationDto
    {
        try {
            $normalized = $this->urlNormalizer->normalize($url);
        } catch (\InvalidArgumentException) {
            throw new InvalidUrlException('URL is not a supported Yandex Maps organization card');
        }

        $reviewsBaseUrl = $this->reviewsBaseUrl($normalized);
        $http = $this->httpClient();
        $maxReviews = (int) config('yandex-maps.max_reviews', 600);
        $pageSize = (int) config('yandex-maps.page_size', 50);
        $reviews = [];
        $organizationItem = null;
        $rawPayload = [
            'parser' => 'internal',
            'pages' => [],
        ];

        for ($page = 1; $page <= $this->maxReviewPages($maxReviews, $pageSize); $page++) {
            $pageUrl = $page === 1
                ? $reviewsBaseUrl
                : $reviewsBaseUrl.'?page='.$page;

            $pageData = $this->loadReviewPage($http, $pageUrl, $reviewsBaseUrl);

            if ($page === 1) {
                $organizationItem = $this->stateExtractor->organizationItem($pageData['state']);
            }

            $pageReviews = $pageData['reviews'];
            $rawPayload['pages'][] = [
                'page' => $page,
                'params' => $pageData['params'],
                'reviews_found' => count($pageReviews),
                'attempts' => $pageData['attempts'],
            ];

            if ($pageReviews === []) {
                break;
            }

            foreach ($pageReviews as $review) {
                $reviews[] = $this->mapReview($review);

                if (count($reviews) >= $maxReviews) {
                    break 2;
                }
            }

            if (count($pageReviews) < $pageSize) {
                break;
            }
        }

        if (! is_array($organizationItem)) {
            throw new ChangedSchemaException(
                'Yandex Maps organization payload is missing required fields',
            );
        }

        $ratingData = $organizationItem['ratingData'] ?? null;

        if (! is_array($ratingData)) {
            throw new ChangedSchemaException(
                'Yandex Maps organization payload is missing required fields',
            );
        }

        $rating = $this->ratingValue($ratingData);
        $ratingsCount = $this->intValue($ratingData['ratingCount'] ?? 0);
        $reviewsCount = $this->intValue($ratingData['reviewCount'] ?? 0);

        $this->ensureConsistentRating($reviews, $rating, $ratingsCount, $reviewsCount);

        return new OrganizationDto(
            sourceUrl: $normalized->sourceUrl,
            normalizedUrl: $normalized->normalizedUrl,
            objectId: $normalized->objectId,
            title: $this->stringOrNull($organizationItem['title'] ?? null),
            address: $this->stringOrNull($organizationItem['address'] ?? $organizationItem['fullAddress'] ?? null),
            rating: $rating,
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
            reviews: $reviews,
            parserVersion: (string) config('yandex-maps.parser_version', '1.0.0'),
            rawPayload: $rawPayload,
        );
    }

    /**
     * Builds the reviews tab URL for paginated HTML requests
     */
    private function reviewsBaseUrl(NormalizedUrlDto $normalized): string
    {
        $url = rtrim($normalized->normalizedUrl, '/');

        if (str_ends_with($url, '/reviews')) {
            return $url.'/';
        }

        if (str_contains($url, '/maps/org/')) {
            return $url.'/reviews/';
        }

        if ($normalized->objectId !== null) {
            $host = parse_url($normalized->normalizedUrl, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                throw new InvalidUrlException('URL is not a supported Yandex Maps organization card');
            }

            return sprintf(
                'https://%s/maps/org/-/%s/reviews/',
                $host,
                $normalized->objectId,
            );
        }

        throw new InvalidUrlException('URL is not a supported Yandex Maps organization card');
    }

    /**
     * Returns an HTTP client configured for Yandex Maps HTML requests
     */
    private function httpClient(): PendingRequest
    {
        $cookieJar = new CookieJar;

        return Http::withOptions(['cookies' => $cookieJar])
            ->withHeaders([
                'User-Agent' => (string) config('yandex-maps.user_agent'),
                'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language' => (string) config('yandex-maps.accept_language'),
            ])
            ->timeout((int) config('yandex-maps.timeout', 300))
            ->retry(
                (int) config('yandex-maps.retry.times', 2),
                (int) config('yandex-maps.retry.sleep_ms', 500),
                throw: false,
            );
    }

    /**
     * Fetches an organization reviews page and maps transport errors to parser exceptions
     *
     * @throws ParserTimeoutException
     * @throws UnavailableException
     * @throws BlockedException
     */
    private function fetchPage(
        PendingRequest $http,
        string $url,
        string $referer,
    ): string {
        try {
            $response = $http
                ->withHeaders(['Referer' => $referer])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new ParserTimeoutException(
                'Yandex Maps parser request timed out',
                previous: $exception,
            );
        } catch (RequestException $exception) {
            if ($this->isTimeout($exception)) {
                throw new ParserTimeoutException(
                    'Yandex Maps parser request timed out',
                    previous: $exception,
                );
            }

            throw new UnavailableException(
                'Organization page is unavailable',
                previous: $exception,
            );
        }

        if (in_array($response->status(), [403, 429], true)) {
            throw new BlockedException('Yandex Maps blocked the parser request');
        }

        if ($response->status() === 404) {
            throw new UnavailableException('Organization page is unavailable');
        }

        if (! $response->successful()) {
            throw new UnavailableException('Organization page is unavailable');
        }

        $body = $response->body();

        if ($this->isBlockedResponse($body)) {
            throw new BlockedException('Yandex Maps blocked the parser request');
        }

        return $body;
    }

    /**
     * Loads reviews from one paginated HTML page, retrying empty responses when more pages are expected
     *
     * @return array{state: array<string, mixed>, reviews: array<int, array<string, mixed>>, params: array<string, mixed>|null, attempts: int}
     */
    private function loadReviewPage(
        PendingRequest $http,
        string $pageUrl,
        string $referer,
    ): array {
        $maxAttempts = 1 + $this->pageRetryTimes();
        $attempt = 0;
        $state = [];
        $reviews = [];
        $params = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $html = $this->fetchPage($http, $pageUrl, $referer);
            $state = $this->stateExtractor->extractState($html);
            $reviews = $this->stateExtractor->reviews($state);
            $params = $this->stateExtractor->reviewParams($state);

            if ($reviews !== [] || ! $this->hasMoreReviews($params)) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep($this->pageRetrySleepMicroseconds());
            }
        }

        return [
            'state' => $state,
            'reviews' => $reviews,
            'params' => $params,
            'attempts' => $attempt,
        ];
    }

    /**
     * Checks whether Yandex pagination meta indicates more review pages
     *
     * @param  array<string, mixed>|null  $params
     */
    private function hasMoreReviews(?array $params): bool
    {
        if ($params === null) {
            return false;
        }

        $reviewsRemained = $params['reviewsRemained'] ?? null;

        if (is_numeric($reviewsRemained) && (int) $reviewsRemained > 0) {
            return true;
        }

        $page = $params['page'] ?? null;
        $totalPages = $params['totalPages'] ?? null;

        return is_numeric($page)
            && is_numeric($totalPages)
            && (int) $page < (int) $totalPages;
    }

    /**
     * Returns the configured number of retries for an empty review page
     */
    private function pageRetryTimes(): int
    {
        return max(0, (int) config('yandex-maps.retry.page_times', 2));
    }

    /**
     * Returns the configured delay between empty review page retries in microseconds
     */
    private function pageRetrySleepMicroseconds(): int
    {
        return max(0, (int) config('yandex-maps.retry.page_sleep_ms', 500)) * 1000;
    }

    /**
     * Maps a raw Yandex review payload to a review DTO
     *
     * @param  array<string, mixed>  $review
     *
     * @throws ChangedSchemaException
     */
    private function mapReview(array $review): ReviewDto
    {
        $author = $review['author'] ?? [];
        $author = is_array($author) ? $author : [];

        if (! array_key_exists('author', $review) && ! array_key_exists('text', $review)) {
            throw new ChangedSchemaException(
                'Yandex Maps reviews payload is missing required fields',
            );
        }

        $avatarUrl = $this->stringOrNull($author['avatarUrl'] ?? null);

        if ($avatarUrl !== null) {
            $avatarUrl = str_replace('{size}', 'islands-68', $avatarUrl);
        }

        return new ReviewDto(
            externalId: $this->stringOrNull($review['reviewId'] ?? $review['id'] ?? null),
            authorName: $this->stringOrNull($author['name'] ?? null) ?? 'Anonymous',
            authorAvatarUrl: $avatarUrl,
            reviewedAt: $this->reviewedAt($review['updatedTime'] ?? $review['date'] ?? null),
            text: $this->stringOrNull($review['text'] ?? null),
            rating: $this->nullableInt($review['rating'] ?? null),
            rawPayload: $review,
        );
    }

    /**
     * Returns the maximum number of review pages to request for the configured limit
     */
    private function maxReviewPages(int $maxReviews, int $pageSize): int
    {
        if ($pageSize <= 0) {
            return 1;
        }

        return (int) ceil($maxReviews / $pageSize);
    }

    /**
     * Checks whether the HTML body looks like a Yandex anti-bot challenge page
     */
    private function isBlockedResponse(string $body): bool
    {
        return str_contains($body, 'SmartCaptcha')
            || str_contains($body, 'showcaptcha')
            || str_contains($body, '/checkcaptcha');
    }

    /**
     * Checks whether an HTTP client exception was caused by a request timeout
     */
    private function isTimeout(RequestException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout');
    }

    /**
     * Reads the organization average rating from Yandex rating data
     *
     * @param  array<string, mixed>  $ratingData
     */
    private function ratingValue(array $ratingData): ?float
    {
        $rating = $ratingData['ratingValue'] ?? null;

        if ($rating === null || $rating === '') {
            return null;
        }

        return round((float) $rating, 2);
    }

    /**
     * Coerces a Yandex counter value to a non-negative integer
     */
    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * Fails partial Yandex responses instead of saving visible reviews with zero summary counters
     *
     * @param  array<int, ReviewDto>  $reviews
     *
     * @throws ChangedSchemaException
     */
    private function ensureConsistentRating(
        array $reviews,
        ?float $rating,
        int $ratingsCount,
        int $reviewsCount,
    ): void {
        if ($reviews === []) {
            return;
        }

        if ($rating !== null && $rating > 0.0 && $ratingsCount > 0 && $reviewsCount > 0) {
            return;
        }

        throw new ChangedSchemaException(
            'Yandex Maps organization rating data is inconsistent with reviews payload',
        );
    }

    /**
     * Coerces a review rating value to an integer or null
     */
    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Coerces a scalar value to a trimmed string or null
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Parses a Yandex review timestamp into an immutable datetime
     */
    private function reviewedAt(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
