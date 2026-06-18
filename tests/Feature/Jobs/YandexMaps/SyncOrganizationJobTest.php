<?php

namespace Tests\Feature\Jobs\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\DTO\YandexMaps\ReviewDto;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\YandexMaps\BlockedException;
use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\InvalidUrlException;
use App\Exceptions\YandexMaps\ParserTimeoutException;
use App\Exceptions\YandexMaps\UnavailableException;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Models\Review;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\ReviewRepository;
use App\Repositories\Organizations\SyncRunRepository;
use App\Services\YandexMaps\BlockPolicy;
use App\Services\YandexMaps\Parser;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $snapshot = [
            'source_url' => $organization->source_url,
            'normalized_url' => $organization->normalized_url,
            'yandex_object_id' => $organization->yandex_object_id,
        ];

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
            'source_url' => $snapshot['source_url'],
            'normalized_url' => $snapshot['normalized_url'],
            'yandex_object_id' => $snapshot['yandex_object_id'],
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

    /**
     * @return array<string, array{0: \Throwable, 1: string, 2: string}>
     */
    public static function domainParserExceptions(): array
    {
        return [
            'invalid url' => [
                new InvalidUrlException('URL is not a supported Yandex Maps organization card'),
                'invalid_url',
                'URL is not a supported Yandex Maps organization card',
            ],
            'unavailable' => [
                new UnavailableException('Organization page is unavailable'),
                'unavailable',
                'Organization page is unavailable',
            ],
            'blocked' => [
                new BlockedException('Yandex Maps blocked the parser request'),
                'blocked',
                'blocked sync friendly message',
            ],
            'changed schema' => [
                new ChangedSchemaException('Yandex Maps page state is missing or has an unexpected format'),
                'changed_schema',
                'Yandex Maps page state is missing or has an unexpected format',
            ],
            'parser timeout' => [
                new ParserTimeoutException('Yandex Maps parser request timed out'),
                'parser_timeout',
                'Yandex Maps parser request timed out',
            ],
        ];
    }

    #[DataProvider('domainParserExceptions')]
    public function test_maps_domain_parser_exceptions_to_failed_sync_state(
        \Throwable $exception,
        string $errorType,
        string $message,
    ): void {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $this->runJob($organization, (new FakeParser)->throws($exception));

        $organization->refresh();
        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame(OrganizationSyncStatus::Failed, $syncRun->status);
        $this->assertSame($errorType, $syncRun->error_type);

        if ($exception instanceof BlockedException) {
            $message = $this->expectedBlockedSyncMessage($organization);

            $this->assertStringNotContainsString('blocked the parser request', $organization->last_sync_error);
        }

        $this->assertSame($message, $organization->last_sync_error);
        $this->assertSame($message, $syncRun->error_message);
        $this->assertSame($exception::class, $syncRun->meta['exception'] ?? null);
    }

    public function test_marks_failed_and_rethrows_unexpected_exception(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $exception = new \RuntimeException('Database connection lost');

        try {
            $this->runJob($organization, (new FakeParser)->throws($exception));
            $this->fail('Expected RuntimeException to be rethrown');
        } catch (\RuntimeException $caught) {
            $this->assertSame('Database connection lost', $caught->getMessage());
        }

        $organization->refresh();
        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame(
            'Organization synchronization failed unexpectedly',
            $organization->last_sync_error,
        );
        $this->assertSame('unexpected', $syncRun->error_type);
        $this->assertSame(\RuntimeException::class, $syncRun->meta['exception'] ?? null);
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

    public function test_hides_missing_reviews_after_complete_sync(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $oldReview = app(ReviewRepository::class)->upsertForOrganization($organization, [
            new ReviewDto('old-1', 'OldAuthor', null, new DateTimeImmutable('2023-01-01'), 'Old review', 5, null),
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => true,
        ]);

        $completeDto = new OrganizationDto(
            sourceUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            normalizedUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            objectId: '123456789012',
            title: 'Cafe Pushkin',
            address: 'Moscow, Tverskoy Blvd, 26A',
            rating: 4.5,
            ratingsCount: 1200,
            reviewsCount: 3,
            reviews: [
                new ReviewDto('review-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, ['id' => 'review-1']),
                new ReviewDto('review-2', 'Bob', null, new DateTimeImmutable('2024-02-01'), 'Good', 4, ['id' => 'review-2']),
                new ReviewDto(null, 'Charlie', null, new DateTimeImmutable('2024-03-01'), 'Fine', 3, null),
            ],
            parserVersion: 'fake-1.0',
        );

        $parser = (new FakeParser)->returns($completeDto);

        $this->runJob($organization, $parser);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => false,
        ]);

        $oldReviewRefreshed = app(ReviewRepository::class)->paginateForOrganization($organization)
            ->items();

        $hiddenReview = Review::query()
            ->where('organization_id', $organization->id)
            ->where('author_name', 'OldAuthor')
            ->first();

        $this->assertFalse($hiddenReview->is_visible);
        $this->assertNotNull($hiddenReview->missing_since);

        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();

        $this->assertTrue($syncRun->meta['missing_reviews_processed']);
        $this->assertSame(1, $syncRun->meta['reviews_hidden']);
        $this->assertTrue($syncRun->meta['reviews_complete_snapshot']);
        $this->assertNull($syncRun->meta['missing_reviews_skipped_reason']);
    }

    public function test_does_not_hide_reviews_when_sync_is_incomplete(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $oldReview = app(ReviewRepository::class)->upsertForOrganization($organization, [
            new ReviewDto('old-1', 'OldAuthor', null, new DateTimeImmutable('2023-01-01'), 'Old review', 5, null),
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => true,
        ]);

        $incompleteDto = new OrganizationDto(
            sourceUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            normalizedUrl: 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            objectId: '123456789012',
            title: 'Cafe Pushkin',
            address: 'Moscow, Tverskoy Blvd, 26A',
            rating: 4.5,
            ratingsCount: 1200,
            reviewsCount: 1000,
            reviews: [
                new ReviewDto('review-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null),
                new ReviewDto('review-2', 'Bob', null, new DateTimeImmutable('2024-02-01'), 'Good', 4, null),
            ],
            parserVersion: 'fake-1.0',
        );

        $parser = (new FakeParser)->returns($incompleteDto);

        $this->runJob($organization, $parser);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => true,
        ]);

        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();

        $this->assertFalse($syncRun->meta['missing_reviews_processed']);
        $this->assertSame(0, $syncRun->meta['reviews_hidden']);
        $this->assertFalse($syncRun->meta['reviews_complete_snapshot']);
        $this->assertSame('incomplete_reviews_snapshot', $syncRun->meta['missing_reviews_skipped_reason']);
    }

    public function test_does_not_hide_reviews_when_sync_fails(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $oldReview = app(ReviewRepository::class)->upsertForOrganization($organization, [
            new ReviewDto('old-1', 'OldAuthor', null, new DateTimeImmutable('2023-01-01'), 'Old review', 5, null),
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => true,
        ]);

        $parser = (new FakeParser)->throws(new BlockedException('Yandex Maps blocked the parser request'));

        $this->runJob($organization, $parser);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'author_name' => 'OldAuthor',
            'is_visible' => true,
        ]);

        $organization->refresh();
        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
    }

    public function test_marks_blocked_state_on_blocked_exception(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $parser = (new FakeParser)->throws(new BlockedException('Yandex Maps blocked the parser request'));

        $this->runJob($organization, $parser);

        $organization->refresh();
        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame($this->expectedBlockedSyncMessage($organization), $organization->last_sync_error);
        $this->assertStringNotContainsString('blocked the parser request', $organization->last_sync_error);
        $this->assertSame(1, $organization->blocked_attempts);
        $this->assertNotNull($organization->blocked_until);
        $this->assertTrue($organization->blocked_until->isFuture());

        $this->assertSame(OrganizationSyncStatus::Failed, $syncRun->status);
        $this->assertSame('blocked', $syncRun->error_type);
        $this->assertSame(BlockedException::class, $syncRun->meta['exception']);
        $this->assertSame('delayed_retry', $syncRun->meta['fallback_strategy']);
        $this->assertSame(1, $syncRun->meta['blocked_attempts']);
        $this->assertSame($organization->blocked_until->toIso8601String(), $syncRun->meta['blocked_until']);
        $this->assertNull($syncRun->meta['retry_stopped_reason']);
    }

    public function test_blocked_exception_is_not_rethrown_for_queue_retry(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $parser = (new FakeParser)->throws(new BlockedException('Yandex Maps blocked the parser request'));

        try {
            $this->runJob($organization, $parser);
        } catch (BlockedException $exception) {
            $this->fail('BlockedException should be converted into delayed retry state, not queue retry.');
        }

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame(1, $organization->blocked_attempts);
        $this->assertNotNull($organization->blocked_until);
    }

    public function test_increases_blocked_attempts_on_repeated_blocked_exception(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        $firstBlockedUntil = $organization->blocked_until;

        $parser = (new FakeParser)->throws(new BlockedException('Yandex Maps blocked the parser request'));

        $this->runJob($organization, $parser);

        $organization->refresh();

        $this->assertSame(2, $organization->blocked_attempts);
        $this->assertNotNull($organization->blocked_until);
        $this->assertTrue($organization->blocked_until->greaterThan($firstBlockedUntil));
    }

    public function test_marks_retry_stopped_when_max_attempts_exceeded(): void
    {
        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => $maxAttempts - 1,
            'blocked_until' => now()->subMinute(),
        ]);

        $parser = (new FakeParser)->throws(new BlockedException('Yandex Maps blocked the parser request'));

        $this->runJob($organization, $parser);

        $organization->refresh();
        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($maxAttempts, $organization->blocked_attempts);
        $this->assertSame('max_attempts_exceeded', $syncRun->meta['retry_stopped_reason']);
    }

    public function test_clears_blocked_state_after_successful_sync(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 2,
            'blocked_until' => now()->addHours(1),
        ]);

        $parser = (new FakeParser)->returns($this->organizationDto());

        $this->runJob($organization, $parser);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Succeeded, $organization->sync_status);
        $this->assertSame(0, $organization->blocked_attempts);
        $this->assertNull($organization->blocked_until);
    }

    public function test_other_domain_exceptions_do_not_change_blocked_state(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $parser = (new FakeParser)->throws(new UnavailableException('Organization page is unavailable'));

        $this->runJob($organization, $parser);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);
        $this->assertSame(0, $organization->blocked_attempts);
        $this->assertNull($organization->blocked_until);

        $syncRun = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertArrayNotHasKey('fallback_strategy', $syncRun->meta);
        $this->assertArrayNotHasKey('blocked_attempts', $syncRun->meta);
        $this->assertArrayNotHasKey('blocked_until', $syncRun->meta);
    }

    private function expectedBlockedSyncMessage(Organization $organization): string
    {
        $retryAt = $organization->blocked_until?->toIso8601String() ?? 'the scheduled cooldown';

        return "Yandex Maps temporarily limited synchronization. Next retry is scheduled after {$retryAt}.";
    }

    private function runJob(Organization $organization, Parser $parser): void
    {
        $job = new SyncOrganizationJob($organization->id);

        $job->handle(
            $parser,
            app(OrganizationRepository::class),
            app(ReviewRepository::class),
            app(SyncRunRepository::class),
            app(BlockPolicy::class),
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
