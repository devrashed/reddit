<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reddit_post_id',
        'analysis_type',
        'model',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'result',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost' => 'decimal:6',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RedditPost, $this>
     */
    public function redditPost(): BelongsTo
    {
        return $this->belongsTo(RedditPost::class);
    }
}
