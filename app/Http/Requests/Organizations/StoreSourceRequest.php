<?php

namespace App\Http\Requests\Organizations;

use App\Rules\YandexMapsOrganizationUrl;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_url' => [
                'required',
                'string',
                'url',
                'max:2048',
                new YandexMapsOrganizationUrl,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_url.required' => 'Paste a link to the organization card on Yandex Maps',
            'source_url.url' => 'Provide a valid organization link',
            'source_url.max' => 'The organization link is too long',
        ];
    }
}
