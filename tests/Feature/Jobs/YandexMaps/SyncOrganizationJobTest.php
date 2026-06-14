<?php

namespace Tests\Feature\Jobs\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\DTO\YandexMaps\ReviewDto;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\YandexMaps\UnavailableException;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\ReviewRepository;
use App\Repositories\Organizations\SyncRunRepository;
use App\Services\YandexMaps\Parser;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\YandexMaps\FakeParser;
use Tests\TestCase;

final class SyncOrganizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_organization_succeeded_and_persists_parser_result(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $parser = (new FakeParser)->returns($this->organizationDto());

        $this->runJob($organization, $parser);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Succeeded, $organization->sync_status);
        $this->assertSame('Cafe Pushkin', $organization->title);
        $this->assertSame('Moscow, Tverskoy Blvd, 26A', $organization->address);
        $this->assertSame('4.50', $organization->rating);
        $this->assertSame(1200, $organization->ratings_count);
        $this->assertSame(450, $organization->reviews_count);
        $this->assertSame('fake-1.0', $organization->parser_version);
        $this->assertNull($organization->last_sync_error);
        $this->assertDatabaseCount('reviews', 3);

        $this->assertDatabaseHas('organization_sync_runs', [
            'organization_id' => $organization->id,
            'status' => OrganizationSyncStatus::Succeeded->value,
            'reviews_found' => 3,
            'reviews_saved' => 3,
            'ratings_count' => 1200,
            'reviews_count' => 450,
        ]);
    }

    public function test_does_not_duplicate_reviews_on_repeated_job_run(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $parser = (new FakeParser)->returns($this->organizationDto());

        $this->runJob($organization, $parser);
        $this->runJob($organization->fresh(), $parser);

        $this->assertDatabaseCount('reviews', 3);
    }

    public function test_marks_organization_and_sync_run_failed_on_domain_exception(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $parser = (new FakeParser)->throws(new UnavailableException('Organization page is unavailable'));

        $this->runJob($organization, $parser);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame('Organization page is unavailable', $organization->last_sync_error);

        $this->assertDatabaseHas('organization_sync_runs', [
            'organization_id' => $organization->id,
            'status' => OrganizationSyncStatus::Failed->value,
            'error_type' => 'unavailable',
            'error_message' => 'Organization page is unavailable',
        ]);
    }

    public function test_skips_parser_when_organization_lock_is_already_held(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $parser = new FakeParser;
        $lock = Cache::lock('yandex-maps-sync:'.$organization->id, (int) config('yandex-maps.timeout', 300));
        $lock->get();

        try {
            $this->runJob($organization, $parser);
        } finally {
            $lock->release();
        }

        $this->assertFalse($parser->called);
        $organization->refresh();
        $this->assertSame(OrganizationSyncStatus::Queued, $organization->sync_status);
        $this->assertDatabaseCount('organization_sync_runs', 0);
    }

    public function test_failed_marks_running_organization_as_failed_on_job_timeout(): void
    {
        $organization = Organization::factory()->syncing()->create();
        $syncRun = OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Running,
        ]);

        $job = new SyncOrganizationJob($organization->id);
        $job->failed(new TimeoutExceededException);

        $organization->refresh();
        $syncRun->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame('Organization synchronization timed out', $organization->last_sync_error);
        $this->assertNotNull($organization->last_sync_finished_at);
        $this->assertSame(OrganizationSyncStatus::Failed, $syncRun->status);
        $this->assertSame('job_timeout', $syncRun->error_type);
    }

    public function test_job_timeout_matches_configured_sync_timeout(): void
    {
        config()->set('yandex-maps.timeout', 300);

        $job = new SyncOrganizationJob(1);

        $this->assertSame(300, $job->timeout);
    }

    private function runJob(Organization $organization, Parser $parser): void
    {
        $job = new SyncOrganizationJob($organization->id);

        $job->handle(
            $parser,
            app(OrganizationRepository::class),
            app(ReviewRepository::class),
            app(SyncRunRepository::class),
        );
    }

    private function organizationDto(): OrganizationDto
    {
        return new OrganizationDto(
            sourceUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            normalizedUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            objectId: '123456789012',
            title: 'Cafe Pushkin',
            address: 'Moscow, Tverskoy Blvd, 26A',
            rating: 4.5,
            ratingsCount: 1200,
            reviewsCount: 450,
            reviews: [
                new ReviewDto('review-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, ['id' => 'review-1']),
                new ReviewDto('review-2', 'Bob', null, new DateTimeImmutable('2024-02-01'), 'Good', 4, ['id' => 'review-2']),
                new ReviewDto(null, 'Charlie', null, new DateTimeImmutable('2024-03-01'), 'Fine', 3, null),
            ],
            parserVersion: 'fake-1.0',
        );
    }
}
