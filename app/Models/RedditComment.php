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
        'reddit_post_id',
        'reddit_comment_id',
        'parent_reddit_id',
        'author',
        'body',
        'score',
        'link_id',
        'created_utc',
        'raw_data',
        'collected_at',
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
            'created_utc' => 'datetime',
            'collected_at' => 'datetime',
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
