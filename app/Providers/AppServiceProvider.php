<?php

namespace App\Providers;

use App\Services\YandexMaps\BlockPolicy;
use App\Services\YandexMaps\InternalRequestParser;
use App\Services\YandexMaps\Parser;
use App\Services\YandexMaps\UnavailableParser;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Parser::class, function ($app): Parser {
            $mode = (string) config('yandex-maps.parser_mode', 'internal');

            return match ($mode) {
                'internal', 'hybrid' => $app->make(InternalRequestParser::class),
                'browser' => $app->make(UnavailableParser::class),
                default => throw new InvalidArgumentException(
                    "Unsupported Yandex Maps parser mode [{$mode}]",
                ),
            };
        });

        $this->app->bind(BlockPolicy::class, function (): BlockPolicy {
            $maxAttempts = (int) config('yandex-maps.blocked_retry.max_attempts', 5);
            $jitterPercent = (int) config('yandex-maps.blocked_retry.jitter_percent', 10);

            /** @var array<int, mixed>|null $delays */
            $delays = config('yandex-maps.blocked_retry.delays_minutes');

            if (! is_array($delays) || count($delays) === 0) {
                $delays = [15, 60, 360, 1440];
            }

            $delaysMinutes = array_map(fn ($value): int => (int) $value, $delays);

            return new BlockPolicy(
                maxAttempts: $maxAttempts,
                jitterPercent: $jitterPercent,
                delaysMinutes: $delaysMinutes,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
