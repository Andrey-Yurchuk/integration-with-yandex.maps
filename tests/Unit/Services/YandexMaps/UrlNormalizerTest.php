<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Services\YandexMaps\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new UrlNormalizer;
    }

    #[DataProvider('supportedOrganizationUrlsProvider')]
    public function test_supports_typical_yandex_maps_organization_urls(string $url): void
    {
        $this->assertTrue($this->normalizer->supports($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function supportedOrganizationUrlsProvider(): array
    {
        return [
            'yandex.ru org path' => ['https://yandex.ru/maps/org/cafe_pushkin/123456789012/'],
            'yandex.ru org path without trailing slash' => ['https://yandex.ru/maps/org/cafe_pushkin/123456789012'],
            'yandex.ru maps dash org path' => ['https://yandex.ru/maps/-/org/cafe_pushkin/123456789012/'],
            'yandex.by org path' => ['https://yandex.by/maps/org/test_org/9876543210/'],
            'yandex.com org path' => ['https://yandex.com/maps/org/company/111222333444/'],
            'yandex.ru oid query' => ['https://yandex.ru/maps/?oid=555666777888&ll=37.617635,55.755814&z=16'],
            'yandex.kz org path' => ['https://yandex.kz/maps/org/example/222333444555/'],
        ];
    }

    #[DataProvider('unsupportedUrlsProvider')]
    public function test_rejects_unsupported_urls(string $url): void
    {
        $this->assertFalse($this->normalizer->supports($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsupportedUrlsProvider(): array
    {
        return [
            'http scheme' => ['http://yandex.ru/maps/org/test/1234567890/'],
            'non yandex host' => ['https://google.com/maps/place/test'],
            'yandex search page' => ['https://yandex.ru/search/?text=cafe'],
            'maps without organization' => ['https://yandex.ru/maps/?ll=37.617635,55.755814&z=16'],
            'invalid url' => ['not-a-url'],
        ];
    }

    public function test_normalize_extracts_object_id_from_org_path(): void
    {
        $result = $this->normalizer->normalize('https://yandex.ru/maps/org/cafe_pushkin/123456789012/?utm_source=test');

        $this->assertSame('123456789012', $result->objectId);
        $this->assertSame('https://yandex.ru/maps/org/cafe_pushkin/123456789012/', $result->normalizedUrl);
        $this->assertSame(
            'https://yandex.ru/maps/org/cafe_pushkin/123456789012/?utm_source=test',
            $result->sourceUrl,
        );
    }

    public function test_normalize_extracts_object_id_from_oid_query(): void
    {
        $result = $this->normalizer->normalize('https://yandex.ru/maps/?oid=555666777888&ll=37.617635,55.755814&z=16');

        $this->assertSame('555666777888', $result->objectId);
        $this->assertSame('https://yandex.ru/maps/?oid=555666777888', $result->normalizedUrl);
    }

    public function test_normalize_keeps_valid_url_without_object_id(): void
    {
        $url = 'https://yandex.ru/maps/org/unknown-format/';

        $this->assertTrue($this->normalizer->supports($url));

        $result = $this->normalizer->normalize($url);

        $this->assertNull($result->objectId);
        $this->assertSame('https://yandex.ru/maps/org/unknown-format/', $result->normalizedUrl);
    }
}
