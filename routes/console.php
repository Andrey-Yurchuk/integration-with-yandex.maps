<?php

use App\DTO\YandexMaps\ReviewDto;
use App\Models\Organization;
use App\Repositories\Organizations\ReviewRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('yandex-maps:benchmark-review-upsert {--count=600 : Number of reviews to upsert per scenario}', function () {
    $reviewCount = max(1, (int) $this->option('count'));
    $connectionName = (string) config('database.default');
    $driver = (string) config("database.connections.{$connectionName}.driver");

    $buildReviewDtos = static function (int $count): array {
        $reviewDtos = [];

        for ($index = 1; $index <= $count; $index++) {
            $day = str_pad((string) (($index % 28) + 1), 2, '0', STR_PAD_LEFT);

            $reviewDtos[] = new ReviewDto(
                externalId: 'benchmark-review-'.$index,
                authorName: 'Benchmark Author '.$index,
                authorAvatarUrl: null,
                reviewedAt: Carbon::parse('2024-01-'.$day.' 12:00:00')->toDateTimeImmutable(),
                text: 'Benchmark review text '.$index,
                rating: ($index % 5) + 1,
                rawPayload: ['benchmark' => true, 'index' => $index],
            );
        }

        return $reviewDtos;
    };

    $activeQueryCounter = null;

    DB::listen(static function () use (&$activeQueryCounter): void {
        if ($activeQueryCounter !== null) {
            $activeQueryCounter++;
        }
    });

    $measureUpsertQueries = static function (
        ReviewRepository $repository,
        Organization $organization,
        array $reviewDtos,
    ) use (&$activeQueryCounter): int {
        $activeQueryCounter = 0;

        try {
            $repository->upsertForOrganization($organization, $reviewDtos);
        } finally {
            $queryCount = $activeQueryCounter;
            $activeQueryCounter = null;
        }

        return $queryCount;
    };

    $firstRunQueries = 0;
    $repeatedRunQueries = 0;

    DB::beginTransaction();

    try {
        $organization = Organization::factory()->create([
            'title' => 'Benchmark Review Upsert',
        ]);

        $reviewDtos = $buildReviewDtos($reviewCount);
        $repository = app(ReviewRepository::class);

        $firstRunQueries = $measureUpsertQueries($repository, $organization, $reviewDtos);
        $repeatedRunQueries = $measureUpsertQueries($repository, $organization, $reviewDtos);
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
})->purpose('Benchmark SQL queries for ReviewRepository::upsertForOrganization()');
