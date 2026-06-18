<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Exceptions\YandexMaps\BlockedException;
use App\Services\YandexMaps\HybridParser;
use App\Services\YandexMaps\InternalRequestParser;
use App\Services\YandexMaps\PageStateExtractor;
use App\Services\YandexMaps\UrlNormalizer;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\YandexMaps\FixturePage;
use Tests\TestCase;

final class HybridParserTest extends TestCase
{
    private const ORG_URL = 'https://yandex.ru/maps/org/cafe_pushkin/1234567890/';

    private const REVIEWS_URL = 'https://yandex.ru/maps/org/cafe_pushkin/1234567890/reviews/';

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

    public function test_returns_internal_parser_result_on_successful_parse(): void
    {
        Http::fake([
            self::REVIEWS_URL => Http::response(FixturePage::html(FixturePage::organizationState())),
        ]);

        $result = $this->hybridParser()->parse(self::ORG_URL);

        $this->assertSame('1234567890', $result->objectId);
        $this->assertSame('Cafe Pushkin', $result->title);
        $this->assertCount(2, $result->reviews);
    }

    public function test_propagates_blocked_exception_without_extra_fallback_attempt(): void
    {
        config()->set('yandex-maps.retry.times', 0);

        Http::fake([
            self::REVIEWS_URL => Http::response('blocked', 403),
        ]);

        try {
            $this->hybridParser()->parse(self::ORG_URL);
            $this->fail('Expected blocked exception to be thrown.');
        } catch (BlockedException $exception) {
            $this->assertSame('Yandex Maps blocked the parser request', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    private function hybridParser(): HybridParser
    {
        return new HybridParser(new InternalRequestParser(
            new UrlNormalizer,
            new PageStateExtractor,
        ));
    }
}
