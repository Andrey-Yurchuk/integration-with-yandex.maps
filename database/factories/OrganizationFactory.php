<?php

namespace Database\Factories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $objectId = (string) fake()->unique()->numerify('##########');

        return [
            'user_id' => User::factory(),
            'source_url' => 'https://yandex.ru/maps/org/test/'.$objectId,
            'normalized_url' => 'https://yandex.ru/maps/org/test/'.$objectId,
            'yandex_object_id' => $objectId,
            'title' => fake()->company(),
            'address' => fake()->address(),
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_count' => fake()->numberBetween(10, 5000),
            'reviews_count' => fake()->numberBetween(5, 600),
            'sync_status' => OrganizationSyncStatus::Awaiting,
            'last_sync_started_at' => null,
            'last_sync_finished_at' => null,
            'last_sync_error' => null,
            'parser_version' => null,
        ];
    }

    public function syncing(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => OrganizationSyncStatus::Running,
            'last_sync_started_at' => now(),
        ]);
    }
}
