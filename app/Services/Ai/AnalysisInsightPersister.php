<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;
use App\Models\BusinessIdea;
use App\Models\PainPoint;
use Illuminate\Support\Str;

class AnalysisInsightPersister
{
    /**
     * Persist the structured pain point and business idea for an analysis version.
     *
     * The unique ai_analysis_id constraints make this operation idempotent.
     */
    public function persist(AiAnalysis $analysis): void
    {
        $result = is_array($analysis->analysis_result) ? $analysis->analysis_result : [];

        $evidence = [
            'post' => $result['evidence_from_post'] ?? [],
            'comments' => $result['evidence_from_comments'] ?? [],
            'classification' => $result['evidence_classification'] ?? [],
        ];

        $painPoint = PainPoint::query()->updateOrCreate(
            ['ai_analysis_id' => $analysis->getKey()],
            [
                'source_post_id' => $analysis->post_id,
                'title' => Str::limit(
                    (string) ($result['main_pain_point'] ?? $result['problem_identification'] ?? 'AI-identified pain point'),
                    250
                ),
                'description' => $result['problem_identification'] ?? null,
                'root_cause' => $result['root_cause'] ?? null,
                'severity' => null,
                'target_audience' => $result['target_audience'] ?? null,
                'evidence' => $evidence,
                'is_ai_generated' => true,
                'category' => 'ai_analysis',
                'analysis_status' => 'completed',
            ]
        );

        BusinessIdea::query()->updateOrCreate(
            ['ai_analysis_id' => $analysis->getKey()],
            [
                'pain_point_id' => $painPoint->getKey(),
                'source_post_id' => $analysis->post_id,
                'title' => Str::limit(
                    (string) ($result['business_opportunity'] ?? $result['suggested_product_type'] ?? 'AI-suggested business idea'),
                    250
                ),
                'description' => $result['business_opportunity'] ?? null,
                'product_type' => Str::limit((string) ($result['suggested_product_type'] ?? ''), 100) ?: null,
                'target_market' => Str::limit((string) ($result['target_audience'] ?? ''), 250) ?: null,
                'proposed_solution' => $result['solution_gap'] ?? null,
                'mvp_features' => $result['suggested_mvp_features'] ?? [],
                'monetization_ideas' => $result['monetization_ideas'] ?? [],
                'risks' => $result['risks_and_limitations'] ?? [],
                'evidence' => $evidence,
                'is_ai_generated' => true,
                'status' => 'draft',
            ]
        );
    }
}
