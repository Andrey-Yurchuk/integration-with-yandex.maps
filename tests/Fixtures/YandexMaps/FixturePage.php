<?php

namespace Tests\Fixtures\YandexMaps;

final class FixturePage
{
    /**
     * Wraps maps state JSON into an HTML page fragment
     *
     * @param  array<string, mixed>  $state
     */
    public static function html(array $state): string
    {
        return '<!DOCTYPE html><html><body><script type="application/json" class="state-view">'
            .json_encode($state, JSON_THROW_ON_ERROR)
            .'</script></body></html>';
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function organizationState(array $overrides = []): array
    {
        $base = [
            'stack' => [
                [
                    'mode' => 'orgpage',
                    'results' => [
                        'items' => [
                            [
                                'id' => '1234567890',
                                'title' => 'Cafe Pushkin',
                                'address' => 'Moscow, Tverskoy Blvd, 26A',
                                'ratingData' => [
                                    'ratingCount' => 1200,
                                    'ratingValue' => 4.5,
                                    'reviewCount' => 450,
                                ],
                                'reviewResults' => [
                                    'reviews' => self::reviews(2),
                                    'params' => [
                                        'page' => 1,
                                        'totalPages' => 3,
                                        'limit' => 50,
                                        'count' => 450,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function reviews(int $count, int $offset = 0): array
    {
        $reviews = [];

        for ($index = 0; $index < $count; $index++) {
            $number = $offset + $index + 1;

            $reviews[] = [
                'reviewId' => 'review-'.$number,
                'author' => [
                    'name' => 'Author '.$number,
                    'avatarUrl' => 'https://avatars.example/avatar/{size}',
                ],
                'text' => 'Review text '.$number,
                'rating' => ($number % 5) + 1,
                'updatedTime' => '2024-06-01T12:00:00.000Z',
            ];
        }

        return $reviews;
    }

    /**
     * @return array<string, mixed>
     */
    public static function paginatedOrganizationState(
        int $page,
        int $reviewCount,
        int $offset = 0,
        ?int $reviewsRemained = null,
    ): array {
        $state = self::organizationState();
        $loadedReviewsCount = $offset + $reviewCount;
        $totalCount = 450;
        $state['stack'][0]['results']['items'][0]['reviewResults'] = [
            'reviews' => self::reviews($reviewCount, $offset),
            'params' => [
                'page' => $page,
                'totalPages' => 10,
                'limit' => 50,
                'count' => $totalCount,
                'loadedReviewsCount' => $loadedReviewsCount,
                'reviewsRemained' => $reviewsRemained ?? max(0, $totalCount - $loadedReviewsCount),
            ],
        ];

        return $state;
    }
}
