<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessIdea;
use App\Models\Subreddit;
use App\Services\Ai\BusinessIdeaQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessIdeaController extends Controller
{
    public function __construct(private readonly BusinessIdeaQueryService $businessIdeas) {}

    /**
     * Display searchable, paginated business ideas.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'subreddit_id' => ['nullable', 'integer', 'exists:subreddits,id'],
            'status' => ['nullable', Rule::in(['draft', 'reviewed', 'validated', 'archived'])],
        ]);

        return view('admin.business-ideas.index', [
            'businessIdeas' => $this->businessIdeas->paginate($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'subreddit_id' => $filters['subreddit_id'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'subreddits' => Subreddit::query()->orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }

    /**
     * Display a business idea with evidence and source links.
     */
    public function show(BusinessIdea $businessIdea): View
    {
        $businessIdea->load([
            'sourcePost:id,title,subreddit_id,permalink,post_url',
            'sourcePost.subreddit:id,name,display_name',
            'painPoint:id,title,root_cause,severity,ai_analysis_id',
            'aiAnalysis:id,post_id,version,analysis_status,analyzed_at',
        ]);

        return view('admin.business-ideas.show', compact('businessIdea'));
    }
}
