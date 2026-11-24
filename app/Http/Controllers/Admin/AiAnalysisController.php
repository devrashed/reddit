<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subreddit;
use App\Services\Ai\AiAnalysisQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiAnalysisController extends Controller
{
    public function __construct(private readonly AiAnalysisQueryService $analyses) {}

    /**
     * Display searchable, paginated AI analysis versions.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'subreddit_id' => ['nullable', 'integer', 'exists:subreddits,id'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'completed', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                ...($request->filled('date_from') ? ['after_or_equal:date_from'] : []),
            ],
        ]);

        return view('admin.ai-analysis.index', [
            'analyses' => $this->analyses->paginate($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'subreddit_id' => $filters['subreddit_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'subreddits' => Subreddit::query()->orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }
}
