<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

final class YandexMapsConfigTest extends TestCase
{
    public function test_default_parser_settings_are_configured(): void
    {
        $this->assertContains(config('yandex-maps.parser_mode'), ['internal', 'hybrid', 'browser']);
        $this->assertSame(600, config('yandex-maps.max_reviews'));
        $this->assertSame(50, config('yandex-maps.page_size'));
        $this->assertSame(300, config('yandex-maps.timeout'));
        $this->assertSame('internal-1.0.0', config('yandex-maps.parser_version'));
        $this->assertNotEmpty(config('yandex-maps.user_agent'));
        $this->assertNotEmpty(config('yandex-maps.accept_language'));
    }
}
