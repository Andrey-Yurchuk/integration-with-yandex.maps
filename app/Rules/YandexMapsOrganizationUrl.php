<?php

namespace App\Rules;

use App\Services\YandexMaps\UrlNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class YandexMapsOrganizationUrl implements ValidationRule
{
    /**
     * Validates HTTPS Yandex Maps organization card URLs
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value)) {
            $fail('Provide a valid organization link');

            return;
        }

        if (! str_starts_with($value, 'https://')) {
            $fail('The organization link must use HTTPS');

            return;
        }

        if (! app(UrlNormalizer::class)->supports($value)) {
            $fail('Provide a link to a Yandex Maps organization card');
        }
    }
}
