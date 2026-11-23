<?php

namespace App\Rules;

use App\Models\Subreddit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class UniqueSubredditName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = Subreddit::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($value)]);

        if ($this->ignoreId !== null) {
            $query->whereKeyNot($this->ignoreId);
        }

        if ($query->exists()) {
            $fail('This subreddit has already been added.');
        }
    }
}
