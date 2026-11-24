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
        'source_post_id',
        'ai_analysis_id',
        'title',
        'description',
        'root_cause',
        'severity',
        'target_audience',
        'evidence',
        'is_ai_generated',
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
            'evidence' => 'array',
            'is_ai_generated' => 'boolean',
            'mention_count' => 'integer',
            'confidence_score' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<RedditPost, $this>
     */
    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(RedditPost::class, 'source_post_id');
    }

    /**
     * @return BelongsTo<AiAnalysis, $this>
     */
    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class, 'ai_analysis_id');
    }

    /**
     * @return HasMany<BusinessIdea, $this>
     */
    public function businessIdeas(): HasMany
    {
        return $this->hasMany(BusinessIdea::class);
    }
}
