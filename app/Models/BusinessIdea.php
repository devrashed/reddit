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
        'title',
        'description',
        'target_audience',
        'proposed_solution',
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
}
