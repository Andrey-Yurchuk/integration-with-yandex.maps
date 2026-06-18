<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Services\YandexMaps\HybridParser;
use App\Services\YandexMaps\InternalRequestParser;
use App\Services\YandexMaps\Parser;
use App\Services\YandexMaps\UnavailableParser;
use InvalidArgumentException;
use Tests\TestCase;

final class ParserBindingTest extends TestCase
{
    public function test_internal_mode_resolves_internal_request_parser(): void
    {
        config()->set('yandex-maps.parser_mode', 'internal');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(InternalRequestParser::class, $parser);
    }

    public function test_hybrid_mode_resolves_hybrid_parser(): void
    {
        config()->set('yandex-maps.parser_mode', 'hybrid');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(HybridParser::class, $parser);
    }

    public function test_browser_mode_resolves_unavailable_parser_placeholder(): void
    {
        config()->set('yandex-maps.parser_mode', 'browser');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(UnavailableParser::class, $parser);
    }

    public function test_unsupported_mode_throws_exception(): void
    {
        config()->set('yandex-maps.parser_mode', 'unsupported');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Yandex Maps parser mode [unsupported]');

        $this->app->make(Parser::class);
    }
}
