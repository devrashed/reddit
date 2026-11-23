<?php

namespace App\Models;

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
            'posted_at' => 'datetime',
            'collected_at' => 'datetime',
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
