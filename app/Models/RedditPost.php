<?php

namespace App\Models;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedditPost extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subreddit_id',
        'reddit_id',
        'title',
        'body',
        'author',
        'url',
        'permalink',
        'score',
        'comments_count',
        'posted_at',
        'collected_at',
        'is_self',
        'upvote_ratio',
        'raw_data',
        'qualification_status',
        'qualified_at',
        'comment_collection_status',
        'comment_collection_attempts',
        'comments_collected_at',
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
            'comments_count' => 'integer',
            'is_self' => 'boolean',
            'upvote_ratio' => 'decimal:4',
            'raw_data' => 'array',
            'qualification_status' => PostQualificationStatus::class,
            'comment_collection_status' => CommentCollectionStatus::class,
            'comment_collection_attempts' => 'integer',
            'posted_at' => 'datetime',
            'collected_at' => 'datetime',
            'qualified_at' => 'datetime',
            'comments_collected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subreddit, $this>
     */
    public function subreddit(): BelongsTo
    {
        return $this->belongsTo(Subreddit::class);
    }

    /**
     * @return HasMany<RedditComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(RedditComment::class, 'reddit_post_id');
    }

    /**
     * @return HasMany<PainPoint, $this>
     */
    public function painPoints(): HasMany
    {
        return $this->hasMany(PainPoint::class);
    }

    /**
     * @return HasMany<AiAnalysis, $this>
     */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }
}
