<?php

namespace App\Services\YandexMaps;

use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\UnavailableException;

final class PageStateExtractor
{
    /**
     * Parses embedded maps state JSON from an organization HTML page
     *
     * @return array<string, mixed>
     */
    public function extractState(string $html): array
    {
        if (! preg_match(
            '/<script type="application\/json" class="state-view">(.*?)<\/script>/s',
            $html,
            $matches,
        )) {
            throw new ChangedSchemaException(
                'Yandex Maps page state is missing or has an unexpected format',
            );
        }

        try {
            /** @var array<string, mixed> $state */
            $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ChangedSchemaException(
                'Yandex Maps page state is missing or has an unexpected format',
            );
        }

        return $state;
    }

    /**
     * Returns the organization card item from maps page state
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function organizationItem(array $state): array
    {
        $stack = $state['stack'] ?? null;

        if (! is_array($stack)) {
            throw new ChangedSchemaException(
                'Yandex Maps organization payload is missing required fields',
            );
        }

        foreach ($stack as $entry) {
            if (! is_array($entry) || ($entry['mode'] ?? null) !== 'orgpage') {
                continue;
            }

            if (($entry['error'] ?? null) === 'not-found') {
                throw new UnavailableException('Organization page is unavailable');
            }

            $items = $entry['results']['items'] ?? null;

            if (! is_array($items) || $items === []) {
                throw new UnavailableException('Organization page is unavailable');
            }

            $item = $items[0];

            if (! is_array($item)) {
                throw new ChangedSchemaException(
                    'Yandex Maps organization payload is missing required fields',
                );
            }

            return $item;
        }

        throw new ChangedSchemaException(
            'Yandex Maps organization payload is missing required fields',
        );
    }

    /**
     * Returns review payloads from maps page state
     *
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    public function reviews(array $state): array
    {
        $item = $this->organizationItem($state);
        $reviewResults = $item['reviewResults'] ?? $item['reviews'] ?? null;

        if (! is_array($reviewResults)) {
            return [];
        }

        $reviews = $reviewResults['reviews'] ?? null;

        if (! is_array($reviews)) {
            throw new ChangedSchemaException(
                'Yandex Maps reviews payload is missing required fields',
            );
        }

        return array_values(array_filter($reviews, is_array(...)));
    }

    /**
     * Returns review pagination meta from maps page state
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    public function reviewParams(array $state): ?array
    {
        $item = $this->organizationItem($state);
        $reviewResults = $item['reviewResults'] ?? $item['reviews'] ?? null;

        if (! is_array($reviewResults)) {
            return null;
        }

        $params = $reviewResults['params'] ?? null;

        return is_array($params) ? $params : null;
    }
}
