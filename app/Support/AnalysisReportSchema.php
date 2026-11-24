<?php

namespace App\Support;

class AnalysisReportSchema
{
    /**
     * @var array<string, string>
     */
    public const SECTIONS = [
        'problem_identification' => 'Problem Identification',
        'main_pain_point' => 'Main Pain Point',
        'root_cause' => 'Root Cause',
        'current_workaround' => 'Current Workaround',
        'existing_solution_limitations' => 'Existing Solution Limitations',
        'solution_gap' => 'Solution Gap',
        'business_opportunity' => 'Business Opportunity',
        'suggested_product_type' => 'Suggested Product Type',
        'target_audience' => 'Target Audience',
        'suggested_mvp_features' => 'Suggested MVP Features',
        'monetization_ideas' => 'Monetization Ideas',
        'risks_and_limitations' => 'Risks and Limitations',
        'evidence_from_post' => 'Evidence from the Reddit Post',
        'evidence_from_comments' => 'Evidence from Saved Comments',
    ];

    /**
     * @var list<string>
     */
    public const LIST_SECTIONS = [
        'suggested_mvp_features',
        'monetization_ideas',
        'risks_and_limitations',
        'evidence_from_post',
        'evidence_from_comments',
    ];

    /**
     * @var array<string, string>
     */
    public const CLASSIFICATION_SECTIONS = [
        'supported_facts' => 'Directly Supported Facts',
        'ai_interpretations' => 'AI-Generated Interpretations',
        'hypotheses_to_validate' => 'Hypotheses Requiring Validation',
    ];
}
