<?php

namespace Database\Factories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSyncRun>
 */
class OrganizationSyncRunFactory extends Factory
{
    protected $model = OrganizationSyncRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => OrganizationSyncStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'reviews_found' => 0,
            'reviews_saved' => 0,
            'ratings_count' => null,
            'reviews_count' => null,
            'error_type' => null,
            'error_message' => null,
            'meta' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationSyncStatus::Succeeded,
            'finished_at' => now(),
            'reviews_found' => fake()->numberBetween(50, 600),
            'reviews_saved' => fake()->numberBetween(50, 600),
            'ratings_count' => fake()->numberBetween(100, 5000),
            'reviews_count' => fake()->numberBetween(50, 600),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationSyncStatus::Failed,
            'finished_at' => now(),
            'error_type' => 'unavailable',
            'error_message' => 'Organization page is unavailable',
            'meta' => ['parser' => 'internal'],
        ]);
    }
}
