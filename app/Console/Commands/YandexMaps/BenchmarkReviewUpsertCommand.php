<?php

namespace App\Console\Commands\YandexMaps;

use App\DTO\YandexMaps\ReviewDto;
use App\Models\Organization;
use App\Repositories\Organizations\ReviewRepository;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('yandex-maps:benchmark-review-upsert {--count=600 : Number of reviews to upsert per scenario}')]
#[Description('Benchmark SQL queries for ReviewRepository::upsertForOrganization()')]
final class BenchmarkReviewUpsertCommand extends Command
{
    private ?int $activeQueryCounter = null;

    public function handle(ReviewRepository $repository): int
    {
        $reviewCount = max(1, (int) ($this->option('count') ?? 600));
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver");

        DB::listen(function (): void {
            if ($this->activeQueryCounter !== null) {
                $this->activeQueryCounter++;
            }
        });

        $firstRunQueries = 0;
        $repeatedRunQueries = 0;

        DB::beginTransaction();

        try {
            $organization = Organization::factory()->create([
                'title' => 'Benchmark Review Upsert',
            ]);

            $reviewDtos = $this->buildReviewDtos($reviewCount);

            $firstRunQueries = $this->measureUpsertQueries($repository, $organization, $reviewDtos);
            $repeatedRunQueries = $this->measureUpsertQueries($repository, $organization, $reviewDtos);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        $this->newLine();
        $this->info('Review upsert SQL benchmark');
        $this->newLine();
        $this->line('Database connection: '.$connectionName.' ('.$driver.')');
        $this->line('Reviews per run: '.$reviewCount);
        $this->newLine();
        $this->line('First run (new reviews): '.$firstRunQueries.' SQL queries');
        $this->line('Repeated run (same reviews): '.$repeatedRunQueries.' SQL queries');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<int, ReviewDto>
     */
    private function buildReviewDtos(int $count): array
    {
        $reviewDtos = [];

        for ($index = 1; $index <= $count; $index++) {
            $day = str_pad((string) (($index % 28) + 1), 2, '0', STR_PAD_LEFT);

            $reviewDtos[] = new ReviewDto(
                externalId: 'benchmark-review-'.$index,
                authorName: 'Benchmark Author '.$index,
                authorAvatarUrl: null,
                reviewedAt: new DateTimeImmutable('2024-01-'.$day.' 12:00:00'),
                text: 'Benchmark review text '.$index,
                rating: ($index % 5) + 1,
                rawPayload: ['benchmark' => true, 'index' => $index],
            );
        }

        return $reviewDtos;
    }

    /**
     * @param  array<int, ReviewDto>  $reviewDtos
     */
    private function measureUpsertQueries(
        ReviewRepository $repository,
        Organization $organization,
        array $reviewDtos,
    ): int {
        $this->activeQueryCounter = 0;

        try {
            $repository->upsertForOrganization($organization, $reviewDtos);
        } finally {
            $queryCount = $this->activeQueryCounter;
            $this->activeQueryCounter = null;
        }

        return $queryCount;
    }
}
