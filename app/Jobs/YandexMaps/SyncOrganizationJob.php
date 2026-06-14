<?php

namespace App\Jobs\YandexMaps;

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
use App\Services\YandexMaps\Parser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(public int $organizationId) {}

    public function handle(
        Parser $parser,
        OrganizationRepository $organizations,
        ReviewRepository $reviews,
        SyncRunRepository $syncRuns,
    ): void {
        $lock = Cache::lock(
            $this->lockKey(),
            (int) config("yandex-maps.timeout", 120),
        );

        if (!$lock->get()) {
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

                    $syncRuns->markSucceeded($syncRun, [
                        "reviews_found" => count($parsed->reviews),
                        "reviews_saved" => $reviewsSaved,
                        "ratings_count" => $parsed->ratingsCount,
                        "reviews_count" => $parsed->reviewsCount,
                        "meta" => [
                            "parser_version" =>
                                $parsed->parserVersion ??
                                config("yandex-maps.parser_version"),
                        ],
                    ]);

                    $organizations->markSucceeded($organization);
                });
            } catch (Throwable $exception) {
                $this->failSync(
                    $exception,
                    $organization,
                    $syncRun,
                    $organizations,
                    $syncRuns,
                );

                if (!$this->isDomainException($exception)) {
                    throw $exception;
                }
            }
        } finally {
            $lock->release();
        }
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
        return "yandex-maps-sync:" . $this->organizationId;
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
    ): void {
        DB::transaction(function () use (
            $exception,
            $organization,
            $syncRun,
            $organizations,
            $syncRuns,
        ): void {
            $syncRuns->markFailed(
                $syncRun,
                $this->errorType($exception),
                $this->errorMessage($exception),
                [
                    "exception" => $exception::class,
                ],
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
            $exception instanceof InvalidUrlException => "invalid_url",
            $exception instanceof UnavailableException => "unavailable",
            $exception instanceof BlockedException => "blocked",
            $exception instanceof ChangedSchemaException => "changed_schema",
            $exception instanceof ParserTimeoutException => "parser_timeout",
            default => "unexpected",
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

        return "Organization synchronization failed unexpectedly.";
    }
}
