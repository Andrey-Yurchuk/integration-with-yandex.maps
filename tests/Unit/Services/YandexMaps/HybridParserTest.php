<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Services\YandexMaps\HybridParser;
use App\Services\YandexMaps\InternalRequestParser;
use Tests\TestCase;

final class HybridParserTest extends TestCase
{
    public function test_can_be_resolved_from_container(): void
    {
        $parser = $this->app->make(HybridParser::class);

        $this->assertInstanceOf(HybridParser::class, $parser);
    }

    public function test_uses_internal_request_parser_as_dependency(): void
    {
        config()->set('yandex-maps.parser_mode', 'hybrid');

        $parser = $this->app->make(HybridParser::class);

        $reflection = new \ReflectionClass($parser);
        $property = $reflection->getProperty('internalParser');
        $property->setAccessible(true);
        $internalParser = $property->getValue($parser);

        $this->assertInstanceOf(InternalRequestParser::class, $internalParser);
    }
}
