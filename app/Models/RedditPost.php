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
        'selftext',
        'author',
        'score',
        'num_comments',
        'upvote_ratio',
        'permalink',
        'post_url',
        'is_self',
        'reddit_created_at',
        'first_fetched_at',
        'last_fetched_at',
        'qualification_status',
        'comments_collection_status',
        'comments_collected_at',
        'comment_collection_attempts',
        'pending_rechecked_at',
        'raw_data',
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
            'num_comments' => 'integer',
            'is_self' => 'boolean',
            'upvote_ratio' => 'decimal:4',
            'raw_data' => 'array',
            'qualification_status' => PostQualificationStatus::class,
            'comments_collection_status' => CommentCollectionStatus::class,
            'comment_collection_attempts' => 'integer',
            'reddit_created_at' => 'datetime',
            'first_fetched_at' => 'datetime',
            'last_fetched_at' => 'datetime',
            'qualified_at' => 'datetime',
            'comments_collected_at' => 'datetime',
            'pending_rechecked_at' => 'datetime',
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
        return $this->hasMany(RedditComment::class, 'post_id');
    }

    /**
     * @return HasMany<AiAnalysis, $this>
     */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * @return HasMany<PainPoint, $this>
     */
    public function painPoints(): HasMany
    {
        return $this->hasMany(PainPoint::class);
    }
}
