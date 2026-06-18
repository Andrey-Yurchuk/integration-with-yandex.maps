<?php

namespace App\Jobs\YandexMaps;

use App\Enums\OrganizationSyncStatus;
use App\Exceptions\YandexMaps\BlockedException;
use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\InvalidUrlException;
use App\Exceptions\YandexMaps\ParserTimeoutException;
use App\Exceptions\YandexMaps\UnavailableException;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\ReviewRepository;
use App\Repositories\Organizations\SyncRunRepository;
use App\Services\YandexMaps\BlockPolicy;
use App\Services\YandexMaps\Parser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public int $organizationId)
    {
        $this->timeout = self::configuredTimeout();
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(
        Parser $parser,
        OrganizationRepository $organizations,
        ReviewRepository $reviews,
        SyncRunRepository $syncRuns,
        BlockPolicy $blockPolicy,
    ): void {
        $lock = Cache::lock(
            $this->lockKey(),
            self::configuredTimeout(),
        );

        if (! $lock->get()) {
            $this->release(15);

            return;
        }

        try {
            $organization = Organization::query()->findOrFail(
                $this->organizationId,
            );
            $syncRun = $syncRuns->start($organization);
            $organization = $organizations->markRunning($organization);

            try {
                $parsed = $parser->parse($this->parseUrl($organization));

                DB::transaction(function () use (
                    $organizations,
                    $reviews,
                    $syncRuns,
                    $blockPolicy,
                    $organization,
                    $syncRun,
                    $parsed,
                ): void {
                    $organization = $organizations->updateFromDto(
                        $organization,
                        $parsed,
                    );
                    $reviewsSaved = $reviews->upsertForOrganization(
                        $organization,
                        $parsed->reviews,
                    );

                    $isCompleteSnapshot = $parsed->reviewsCount <= count($parsed->reviews);
                    $reviewsHidden = 0;
                    $missingReviewsProcessed = false;
                    $missingReviewsSkippedReason = null;

                    if ($isCompleteSnapshot) {
                        $contentHashes = $reviews->extractContentHashes($parsed->reviews);
                        $reviewsHidden = $reviews->hideMissingForOrganization(
                            $organization,
                            $contentHashes,
                        );
                        $missingReviewsProcessed = true;
                    } else {
                        $missingReviewsSkippedReason = 'incomplete_reviews_snapshot';
                    }

                    $syncRuns->markSucceeded($syncRun, [
                        'reviews_found' => count($parsed->reviews),
                        'reviews_saved' => $reviewsSaved,
                        'ratings_count' => $parsed->ratingsCount,
                        'reviews_count' => $parsed->reviewsCount,
                        'meta' => [
                            'parser_version' => $parsed->parserVersion ??
                                config('yandex-maps.parser_version'),
                            'reviews_complete_snapshot' => $isCompleteSnapshot,
                            'reviews_hidden' => $reviewsHidden,
                            'missing_reviews_processed' => $missingReviewsProcessed,
                            'missing_reviews_skipped_reason' => $missingReviewsSkippedReason,
                        ],
                    ]);

                    $organizations->markSucceeded($organization);
                    $blockPolicy->clear($organization);
                });
            } catch (Throwable $exception) {
                $this->failSync(
                    $exception,
                    $organization,
                    $syncRun,
                    $organizations,
                    $syncRuns,
                    $blockPolicy,
                );

                if (! $this->isDomainException($exception)) {
                    throw $exception;
                }
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        if (! in_array($organization->sync_status, [
            OrganizationSyncStatus::Queued,
            OrganizationSyncStatus::Running,
        ], true)) {
            return;
        }

        $organizations = app(OrganizationRepository::class);
        $syncRuns = app(SyncRunRepository::class);
        $message = $this->failedMessage($exception);
        $errorType = $this->failedErrorType($exception);

        $syncRun = $organization->syncRun;

        if (
            $syncRun !== null
            && $syncRun->status === OrganizationSyncStatus::Running
        ) {
            DB::transaction(function () use (
                $syncRuns,
                $organizations,
                $syncRun,
                $organization,
                $message,
                $errorType,
                $exception,
            ): void {
                $syncRuns->markFailed(
                    $syncRun,
                    $errorType,
                    $message,
                    [
                        'exception' => $exception !== null ? $exception::class : null,
                    ],
                );

                $organizations->markFailed($organization, $message);
            });

            return;
        }

        $organizations->markFailed($organization, $message);
    }

    /**
     * Returns the organization URL passed to the parser
     */
    private function parseUrl(Organization $organization): string
    {
        return $organization->normalized_url ?? $organization->source_url;
    }

    /**
     * Returns the cache lock key for organization sync serialization
     */
    private function lockKey(): string
    {
        return 'yandex-maps-sync:'.$this->organizationId;
    }

    private static function configuredTimeout(): int
    {
        return (int) config('yandex-maps.timeout', 300);
    }

    /**
     * Marks sync run and organization as failed inside a transaction
     */
    private function failSync(
        Throwable $exception,
        Organization $organization,
        OrganizationSyncRun $syncRun,
        OrganizationRepository $organizations,
        SyncRunRepository $syncRuns,
        BlockPolicy $blockPolicy,
    ): void {
        DB::transaction(function () use (
            $exception,
            $organization,
            $syncRun,
            $organizations,
            $syncRuns,
            $blockPolicy,
        ): void {
            $meta = [
                'exception' => $exception::class,
            ];

            if ($exception instanceof BlockedException) {
                $blockPolicy->markBlocked($organization);
                $organization->refresh();

                $meta['blocked_attempts'] = $organization->blocked_attempts;
                $meta['blocked_until'] = $organization->blocked_until?->toIso8601String();
                $meta['fallback_strategy'] = 'delayed_retry';
                $meta['retry_stopped_reason'] = $blockPolicy->attemptsExceeded($organization)
                    ? 'max_attempts_exceeded'
                    : null;
            }

            $syncRuns->markFailed(
                $syncRun,
                $this->errorType($exception),
                $this->errorMessage($exception),
                $meta,
            );

            $organizations->markFailed(
                $organization,
                $this->errorMessage($exception),
            );
        });
    }

    /**
     * Checks whether the exception is a parser domain error
     */
    private function isDomainException(Throwable $exception): bool
    {
        return $exception instanceof InvalidUrlException ||
            $exception instanceof UnavailableException ||
            $exception instanceof BlockedException ||
            $exception instanceof ChangedSchemaException ||
            $exception instanceof ParserTimeoutException;
    }

    /**
     * Maps parser and runtime exceptions to a sync error type
     */
    private function errorType(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof InvalidUrlException => 'invalid_url',
            $exception instanceof UnavailableException => 'unavailable',
            $exception instanceof BlockedException => 'blocked',
            $exception instanceof ChangedSchemaException => 'changed_schema',
            $exception instanceof ParserTimeoutException => 'parser_timeout',
            default => 'unexpected',
        };
    }

    /**
     * Returns a user-facing sync error message without sensitive details
     */
    private function errorMessage(Throwable $exception): string
    {
        if ($this->isDomainException($exception)) {
            return $exception->getMessage();
        }

        return 'Organization synchronization failed unexpectedly';
    }

    private function failedErrorType(?Throwable $exception): string
    {
        if ($exception instanceof TimeoutExceededException) {
            return 'job_timeout';
        }

        if ($exception instanceof ParserTimeoutException) {
            return 'parser_timeout';
        }

        return 'unexpected';
    }

    private function failedMessage(?Throwable $exception): string
    {
        if ($exception instanceof TimeoutExceededException) {
            return 'Organization synchronization timed out';
        }

        if ($exception instanceof ParserTimeoutException) {
            return $exception->getMessage();
        }

        if ($exception !== null && $this->isDomainException($exception)) {
            return $exception->getMessage();
        }

        return 'Organization synchronization failed unexpectedly';
    }
}
