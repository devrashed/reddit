<?php

namespace App\Http\Requests\Admin;

use App\Models\Subreddit;
use App\Rules\UniqueSubredditName;
use Illuminate\Foundation\Http\FormRequest;

class SubredditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Normalize the subreddit name before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => Subreddit::normalizeName($name)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $subreddit = $this->route('subreddit');
        $ignoreId = $subreddit instanceof Subreddit ? $subreddit->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:21',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_]{0,19}[A-Za-z0-9]$/',
                new UniqueSubredditName($ignoreId),
            ],
            'display_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use only letters, numbers, and underscores. The name cannot start or end with an underscore.',
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'subreddit name',
            'display_name' => 'display name',
        ];
    }
}
