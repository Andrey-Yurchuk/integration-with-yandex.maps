<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
