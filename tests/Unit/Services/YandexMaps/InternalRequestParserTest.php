<?php

namespace Tests\Unit\Services\YandexMaps;

use App\DTO\YandexMaps\ReviewDto;
use App\Exceptions\YandexMaps\BlockedException;
use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\InvalidUrlException;
use App\Exceptions\YandexMaps\ParserTimeoutException;
use App\Exceptions\YandexMaps\UnavailableException;
use App\Services\YandexMaps\InternalRequestParser;
use App\Services\YandexMaps\PageStateExtractor;
use App\Services\YandexMaps\UrlNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\YandexMaps\FixturePage;
use Tests\TestCase;

final class InternalRequestParserTest extends TestCase
{
    private const ORG_URL = 'https://yandex.ru/maps/org/cafe_pushkin/1234567890/';

    private const REVIEWS_URL = 'https://yandex.ru/maps/org/cafe_pushkin/1234567890/reviews/';

    private InternalRequestParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new InternalRequestParser(
            new UrlNormalizer,
            new PageStateExtractor,
        );
    }

    public function test_builds_organization_dto_from_fixture_responses(): void
    {
        $this->fakeOrganizationPages([
            self::REVIEWS_URL => FixturePage::html(FixturePage::organizationState()),
        ]);

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertSame(self::ORG_URL, $result->sourceUrl);
        $this->assertSame('1234567890', $result->objectId);
        $this->assertSame('Cafe Pushkin', $result->title);
        $this->assertSame('Moscow, Tverskoy Blvd, 26A', $result->address);
        $this->assertSame(4.5, $result->rating);
        $this->assertSame(1200, $result->ratingsCount);
        $this->assertSame(450, $result->reviewsCount);
        $this->assertCount(2, $result->reviews);
        $this->assertSame('internal-1.0.0', $result->parserVersion);
    }

    public function test_maps_review_author_date_text_and_rating(): void
    {
        $this->fakeOrganizationPages([
            self::REVIEWS_URL => FixturePage::html(FixturePage::organizationState()),
        ]);

        $review = $this->parser->parse(self::ORG_URL)->reviews[0];

        $this->assertInstanceOf(ReviewDto::class, $review);
        $this->assertSame('review-1', $review->externalId);
        $this->assertSame('Author 1', $review->authorName);
        $this->assertSame('https://avatars.example/avatar/islands-68', $review->authorAvatarUrl);
        $this->assertSame('Review text 1', $review->text);
        $this->assertSame(2, $review->rating);
        $this->assertNotNull($review->reviewedAt);
        $this->assertSame('2024-06-01T12:00:00.000Z', $review->reviewedAt?->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function test_collects_multiple_review_pages(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (preg_match('/[?&]page=2(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 2, reviewCount: 50, offset: 50),
                ));
            }

            if (preg_match('/[?&]page=\d+(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 3, reviewCount: 0, offset: 100),
                ));
            }

            return Http::response(FixturePage::html(
                FixturePage::paginatedOrganizationState(page: 1, reviewCount: 50),
            ));
        });

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertCount(100, $result->reviews);
        $this->assertSame('review-1', $result->reviews[0]->externalId);
        $this->assertSame('review-100', $result->reviews[99]->externalId);
    }

    public function test_retries_empty_page_when_more_reviews_are_expected(): void
    {
        config()->set('yandex-maps.retry.page_times', 1);
        config()->set('yandex-maps.retry.page_sleep_ms', 0);

        $pageTwoAttempts = 0;

        Http::fake(function ($request) use (&$pageTwoAttempts) {
            $url = $request->url();

            if (preg_match('/[?&]page=2(?:&|$)/', $url) === 1) {
                $pageTwoAttempts++;

                if ($pageTwoAttempts === 1) {
                    return Http::response(FixturePage::html(
                        FixturePage::paginatedOrganizationState(page: 2, reviewCount: 0, offset: 50),
                    ));
                }

                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 2, reviewCount: 50, offset: 50),
                ));
            }

            if (preg_match('/[?&]page=\d+(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 3, reviewCount: 0, offset: 100),
                ));
            }

            return Http::response(FixturePage::html(
                FixturePage::paginatedOrganizationState(page: 1, reviewCount: 50),
            ));
        });

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertSame(2, $pageTwoAttempts);
        $this->assertCount(100, $result->reviews);
        $this->assertSame('review-100', $result->reviews[99]->externalId);
    }

    public function test_stops_after_page_retries_are_exhausted(): void
    {
        config()->set('yandex-maps.retry.page_times', 1);
        config()->set('yandex-maps.retry.page_sleep_ms', 0);

        Http::fake(function ($request) {
            $url = $request->url();

            if (preg_match('/[?&]page=2(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 2, reviewCount: 0, offset: 50),
                ));
            }

            return Http::response(FixturePage::html(
                FixturePage::paginatedOrganizationState(page: 1, reviewCount: 50),
            ));
        });

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertCount(50, $result->reviews);
        $this->assertSame(2, $result->rawPayload['pages'][1]['attempts']);
    }

    public function test_stops_at_configured_max_reviews(): void
    {
        config()->set('yandex-maps.max_reviews', 75);

        Http::fake(function ($request) {
            $url = $request->url();

            if (preg_match('/[?&]page=3(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 3, reviewCount: 50, offset: 100),
                ));
            }

            if (preg_match('/[?&]page=2(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 2, reviewCount: 50, offset: 50),
                ));
            }

            if (preg_match('/[?&]page=\d+(?:&|$)/', $url) === 1) {
                return Http::response(FixturePage::html(
                    FixturePage::paginatedOrganizationState(page: 4, reviewCount: 0, offset: 150),
                ));
            }

            return Http::response(FixturePage::html(
                FixturePage::paginatedOrganizationState(page: 1, reviewCount: 50),
            ));
        });

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertCount(75, $result->reviews);
        $this->assertSame('review-75', $result->reviews[74]->externalId);
    }

    public function test_returns_empty_reviews_when_organization_has_no_reviews(): void
    {
        $state = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/YandexMaps/empty_reviews_state.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->fakeOrganizationPages([
            self::REVIEWS_URL => FixturePage::html($state),
        ]);

        $result = $this->parser->parse(self::ORG_URL);

        $this->assertSame(0, $result->reviewsCount);
        $this->assertSame([], $result->reviews);
    }

    public function test_inconsistent_zero_rating_data_with_reviews_throws_changed_schema_exception(): void
    {
        $this->fakeOrganizationPages([
            self::REVIEWS_URL => FixturePage::html(FixturePage::organizationState([
                'stack' => [
                    [
                        'results' => [
                            'items' => [
                                [
                                    'ratingData' => [
                                        'ratingCount' => 0,
                                        'ratingValue' => 0,
                                        'reviewCount' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])),
        ]);

        $this->expectException(ChangedSchemaException::class);
        $this->expectExceptionMessage('Yandex Maps organization rating data is inconsistent with reviews payload');

        $this->parser->parse(self::ORG_URL);
    }

    public function test_invalid_url_throws_invalid_url_exception(): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->parser->parse('https://google.com/maps/place/test');
    }

    public function test_unavailable_organization_throws_unavailable_exception(): void
    {
        $state = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/YandexMaps/not_found_state.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->fakeOrganizationPages([
            self::REVIEWS_URL => FixturePage::html($state),
        ]);

        $this->expectException(UnavailableException::class);

        $this->parser->parse(self::ORG_URL);
    }

    public function test_blocked_response_throws_blocked_exception(): void
    {
        $html = (string) file_get_contents(base_path('tests/Fixtures/YandexMaps/blocked.html'));

        $this->fakeOrganizationPages([
            self::REVIEWS_URL => $html,
        ]);

        $this->expectException(BlockedException::class);

        $this->parser->parse(self::ORG_URL);
    }

    public function test_changed_schema_throws_changed_schema_exception(): void
    {
        $html = (string) file_get_contents(base_path('tests/Fixtures/YandexMaps/changed_schema.html'));

        $this->fakeOrganizationPages([
            self::REVIEWS_URL => $html,
        ]);

        $this->expectException(ChangedSchemaException::class);

        $this->parser->parse(self::ORG_URL);
    }

    public function test_timeout_maps_to_parser_timeout_exception(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Operation timed out after 120000 milliseconds');
        });

        $this->expectException(ParserTimeoutException::class);

        $this->parser->parse(self::ORG_URL);
    }

    public function test_http_403_maps_to_blocked_exception(): void
    {
        Http::fake([
            self::REVIEWS_URL => Http::response('blocked', 403),
        ]);

        $this->expectException(BlockedException::class);

        $this->parser->parse(self::ORG_URL);
    }

    /**
     * @param  array<string, string>  $responses
     */
    private function fakeOrganizationPages(array $responses): void
    {
        Http::fake($responses);
    }
}
