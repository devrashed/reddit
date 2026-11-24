<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessIdea extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pain_point_id',
        'source_post_id',
        'ai_analysis_id',
        'title',
        'description',
        'product_type',
        'target_market',
        'proposed_solution',
        'mvp_features',
        'monetization_ideas',
        'risks',
        'evidence',
        'is_ai_generated',
        'market_score',
        'feasibility_score',
        'overall_score',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mvp_features' => 'array',
            'monetization_ideas' => 'array',
            'risks' => 'array',
            'evidence' => 'array',
            'is_ai_generated' => 'boolean',
            'market_score' => 'integer',
            'feasibility_score' => 'integer',
            'overall_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PainPoint, $this>
     */
    public function painPoint(): BelongsTo
    {
        return $this->belongsTo(PainPoint::class);
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
}
