<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PainPoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reddit_post_id',
        'title',
        'description',
        'category',
        'sentiment',
        'mention_count',
        'confidence_score',
        'analysis_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mention_count' => 'integer',
            'confidence_score' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<RedditPost, $this>
     */
    public function redditPost(): BelongsTo
    {
        return $this->belongsTo(RedditPost::class);
    }

    /**
     * @return HasMany<BusinessIdea, $this>
     */
    public function businessIdeas(): HasMany
    {
        return $this->hasMany(BusinessIdea::class);
    }
}
