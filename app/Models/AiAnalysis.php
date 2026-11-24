<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiAnalysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'provider',
        'model',
        'analysis_type',
        'analysis_result',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'analysis_status',
        'version',
        'analyzed_at',
        'cost',
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
            'analysis_result' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'version' => 'integer',
            'analyzed_at' => 'datetime',
            'cost' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<RedditPost, $this>
     */
    public function redditPost(): BelongsTo
    {
        return $this->belongsTo(RedditPost::class, 'post_id');
    }

    /**
     * @return HasOne<PainPoint, $this>
     */
    public function painPoint(): HasOne
    {
        return $this->hasOne(PainPoint::class, 'ai_analysis_id');
    }

    /**
     * @return HasOne<BusinessIdea, $this>
     */
    public function businessIdea(): HasOne
    {
        return $this->hasOne(BusinessIdea::class, 'ai_analysis_id');
    }
}
