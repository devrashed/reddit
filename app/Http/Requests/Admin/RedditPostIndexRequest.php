<?php

namespace App\Http\Requests\Admin;

use App\Enums\AiAnalysisStatus;
use App\Enums\PostQualificationStatus;
use App\Services\Reddit\RedditPostQueryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedditPostIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'subreddit_id' => ['nullable', 'integer', 'exists:subreddits,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                ...($this->filled('date_from') ? ['after_or_equal:date_from'] : []),
            ],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'min_comments' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'qualification_status' => ['nullable', Rule::enum(PostQualificationStatus::class)],
            'ai_status' => ['nullable', Rule::enum(AiAnalysisStatus::class)],
            'sort' => ['nullable', Rule::in(RedditPostQueryService::SORTS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
