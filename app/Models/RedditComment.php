<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedditComment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'reddit_comment_id',
        'parent_id',
        'author',
        'body',
        'score',
        'link_id',
        'reddit_created_at',
        'raw_data',
        'fetched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'raw_data' => 'array',
            'reddit_created_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RedditPost, $this>
     */
    public function redditPost(): BelongsTo
    {
        return $this->belongsTo(RedditPost::class, 'post_id');
    }
}
