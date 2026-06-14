<?php

namespace Tests\Unit\DTO\YandexMaps;

use App\DTO\YandexMaps\FailureDto;
use Tests\TestCase;

final class FailureDtoTest extends TestCase
{
    public function test_stores_structured_failure_data(): void
    {
        $failure = new FailureDto(
            type: 'changed_schema',
            message: 'Unexpected response shape',
            context: ['endpoint' => '/reviews'],
            isRetryable: false,
        );

        $this->assertSame('changed_schema', $failure->type);
        $this->assertSame('Unexpected response shape', $failure->message);
        $this->assertSame(['endpoint' => '/reviews'], $failure->context);
        $this->assertFalse($failure->isRetryable);
    }
}
