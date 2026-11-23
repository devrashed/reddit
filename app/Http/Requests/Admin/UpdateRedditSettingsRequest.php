<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRedditSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Normalize times before validation.
     */
    protected function prepareForValidation(): void
    {
        $times = $this->input('times');

        if (is_array($times)) {
            $times = array_values(array_filter(
                array_map(static fn (mixed $time): string => is_string($time) ? trim($time) : '', $times),
                static fn (string $time): bool => $time !== ''
            ));

            sort($times);

            $this->merge(['times' => $times]);
        }

        $this->merge(['regular_enabled' => $this->boolean('regular_enabled')]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'regular_enabled' => ['required', 'boolean'],
            'regular_frequency' => ['required', 'integer', 'between:1,6'],
            'times' => ['required', 'array', 'size:'.(int) $this->input('regular_frequency')],
            'times.*' => ['required', 'date_format:H:i', 'distinct'],
            'http_timeout' => ['required', 'integer', 'between:5,60'],
            'retry_attempts' => ['required', 'integer', 'between:1,5'],
            'historical_months' => ['required', 'integer', 'between:1,12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'times.size' => 'Provide exactly one collection time for each daily frequency.',
            'times.*.date_format' => 'Each collection time must use the HH:MM 24-hour format.',
            'times.*.distinct' => 'Collection times must be unique.',
        ];
    }
}
