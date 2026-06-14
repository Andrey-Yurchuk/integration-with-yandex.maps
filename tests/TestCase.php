<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?string $compiledViewsPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'view.compiled' => $this->compiledViewsPath(),
        ]);
    }

    private function compiledViewsPath(): string
    {
        if (self::$compiledViewsPath !== null) {
            return self::$compiledViewsPath;
        }

        $storagePath = storage_path('framework/testing/views');

        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        if (is_writable($storagePath)) {
            return self::$compiledViewsPath = $storagePath;
        }

        self::$compiledViewsPath = sys_get_temp_dir().'/laravel-testing-views-'.getmypid();

        if (! is_dir(self::$compiledViewsPath)) {
            mkdir(self::$compiledViewsPath, 0775, true);
        }

        return self::$compiledViewsPath;
    }
}
