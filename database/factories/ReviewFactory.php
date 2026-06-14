<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Review;
use App\Repositories\Organizations\ReviewRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $authorName = fake()->name();
        $reviewedAt = fake()->dateTimeBetween('-2 years', 'now');
        $text = fake()->paragraph();
        $rating = fake()->numberBetween(1, 5);

        $attributes = [
            'organization_id' => Organization::factory(),
            'external_id' => (string) fake()->unique()->numerify('review-########'),
            'author_name' => $authorName,
            'author_avatar_url' => fake()->optional()->imageUrl(64, 64),
            'reviewed_at' => $reviewedAt,
            'text' => $text,
            'rating' => $rating,
            'raw_payload' => ['source' => 'factory'],
        ];

        $attributes['content_hash'] = ReviewRepository::contentHash($attributes);

        return $attributes;
    }
}
