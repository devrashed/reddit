<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PainPoint;
use App\Models\Subreddit;
use App\Services\Ai\PainPointQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PainPointController extends Controller
{
    public function __construct(private readonly PainPointQueryService $painPoints) {}

    /**
     * Display searchable, paginated pain points.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'subreddit_id' => ['nullable', 'integer', 'exists:subreddits,id'],
            'severity' => ['nullable', Rule::in(['not_assessed', 'low', 'medium', 'high'])],
        ]);

        return view('admin.pain-points.index', [
            'painPoints' => $this->painPoints->paginate($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'subreddit_id' => $filters['subreddit_id'] ?? '',
                'severity' => $filters['severity'] ?? '',
            ],
            'subreddits' => Subreddit::query()->orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }

    /**
     * Display a pain point with evidence and related ideas.
     */
    public function show(PainPoint $painPoint): View
    {
        $painPoint->load([
            'sourcePost:id,title,subreddit_id,permalink,post_url',
            'sourcePost.subreddit:id,name,display_name',
            'aiAnalysis:id,post_id,version,analysis_status,analyzed_at',
            'businessIdeas:id,pain_point_id,title,product_type,status',
        ]);

        return view('admin.pain-points.show', compact('painPoint'));
    }
}
