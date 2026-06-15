<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Services\YandexMaps\InternalRequestParser;
use App\Services\YandexMaps\Parser;
use App\Services\YandexMaps\UnavailableParser;
use Tests\TestCase;

final class ParserBindingTest extends TestCase
{
    public function test_internal_mode_resolves_internal_request_parser(): void
    {
        config()->set('yandex-maps.parser_mode', 'internal');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(InternalRequestParser::class, $parser);
    }

    public function test_hybrid_mode_resolves_internal_request_parser_until_hybrid_exists(): void
    {
        config()->set('yandex-maps.parser_mode', 'hybrid');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(InternalRequestParser::class, $parser);
    }

    public function test_browser_mode_resolves_unavailable_parser_placeholder(): void
    {
        config()->set('yandex-maps.parser_mode', 'browser');

        $parser = $this->app->make(Parser::class);

        $this->assertInstanceOf(UnavailableParser::class, $parser);
    }
}
