<?php

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\NormalizedUrlDto;

final class UrlNormalizer
{
    /**
     * Checks whether the URL looks like a supported Yandex Maps organization card
     */
    public function supports(string $url): bool
    {
        $parts = $this->parse($url);

        return $parts !== null && $this->isOrganizationCard($parts);
    }

    /**
     * Normalizes a supported organization URL and extracts object id when possible
     *
     * @throws \InvalidArgumentException
     */
    public function normalize(string $url): NormalizedUrlDto
    {
        $sourceUrl = trim($url);
        $parts = $this->parse($sourceUrl);

        if ($parts === null || ! $this->isOrganizationCard($parts)) {
            throw new \InvalidArgumentException(
                'URL is not a supported Yandex Maps organization card',
            );
        }

        $objectId = $this->objectId($parts);

        return new NormalizedUrlDto(
            sourceUrl: $sourceUrl,
            normalizedUrl: $this->normalizedUrl($parts, $objectId),
            objectId: $objectId,
        );
    }

    /**
     * Reads organization object id from parsed URL parts
     *
     * @param  array{host: string, path: string, query: string|null}  $parts
     */
    public function objectId(array $parts): ?string
    {
        $path = $parts['path'];

        if (
            preg_match("#/maps/(?:-/)?org/[^/]+/(\d+)#", $path, $matches) === 1
        ) {
            return $matches[1];
        }

        if (preg_match("#/maps/(?:-/)?org/(\d+)#", $path, $matches) === 1) {
            return $matches[1];
        }

        if ($parts['query'] !== null) {
            parse_str($parts['query'], $query);

            if (! empty($query['oid']) && ctype_digit((string) $query['oid'])) {
                return (string) $query['oid'];
            }
        }

        return null;
    }

    /**
     * Parses HTTPS Yandex host, path and query or returns null for unsupported input
     *
     * @return array{host: string, path: string, query: string|null}|null
     */
    private function parse(string $url): ?array
    {
        $url = trim($url);

        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! $this->isYandexHost($host)) {
            return null;
        }

        return [
            'host' => $host,
            'path' => rtrim($parts['path'] ?? '', '/'),
            'query' => $parts['query'] ?? null,
        ];
    }

    /**
     * Checks whether the host belongs to an allowed Yandex regional domain
     */
    private function isYandexHost(string $host): bool
    {
        return preg_match(
            '/^(?:[a-z0-9-]+\.)?yandex\.(ru|by|com|kz|ua|uz|com\.tr)$/i',
            $host,
        ) === 1;
    }

    /**
     * Checks whether parsed URL points to an organization card, not a generic maps page
     *
     * @param  array{host: string, path: string, query: string|null}  $parts
     */
    private function isOrganizationCard(array $parts): bool
    {
        if (preg_match('#/maps/(?:-/)?org/#', $parts['path']) === 1) {
            return true;
        }

        if (str_contains($parts['path'], '/maps') && $parts['query'] !== null) {
            parse_str($parts['query'], $query);

            return ! empty($query['oid']);
        }

        return false;
    }

    /**
     * Builds a canonical organization URL without tracking query parameters
     *
     * @param  array{host: string, path: string, query: string|null}  $parts
     */
    private function normalizedUrl(array $parts, ?string $objectId): string
    {
        if (
            preg_match(
                "#(/maps/(?:-/)?org/[^/]+/\d+)#",
                $parts['path'],
                $matches,
            ) === 1
        ) {
            return "https://{$parts['host']}{$matches[1]}/";
        }

        if (
            preg_match("#(/maps/(?:-/)?org/\d+)#", $parts['path'], $matches) ===
            1
        ) {
            return "https://{$parts['host']}{$matches[1]}/";
        }

        if ($objectId !== null) {
            return "https://{$parts['host']}/maps/?oid={$objectId}";
        }

        return "https://{$parts['host']}{$parts['path']}/";
    }
}
