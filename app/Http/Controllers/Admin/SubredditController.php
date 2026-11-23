<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubredditStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubredditIndexRequest;
use App\Http\Requests\Admin\SubredditRequest;
use App\Http\Requests\Admin\SubredditStatusRequest;
use App\Models\Subreddit;
use App\Services\SubredditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubredditController extends Controller
{
    public function __construct(private readonly SubredditService $subreddits) {}

    /**
     * Display a paginated, searchable list of subreddits.
     */
    public function index(SubredditIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.subreddits.index', [
            'subreddits' => $this->subreddits->paginate($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    /**
     * Show the form for adding a subreddit.
     */
    public function create(): View
    {
        return view('admin.subreddits.create');
    }

    /**
     * Store a new subreddit and prepare its initial collection.
     */
    public function store(SubredditRequest $request): RedirectResponse
    {
        $subreddit = $this->subreddits->create($request->validated());

        return redirect()
            ->route('admin.subreddits.show', $subreddit)
            ->with('success', "r/{$subreddit->name} has been added. Its initial historical collection is pending.");
    }

    /**
     * Display a subreddit and its collection history.
     */
    public function show(Subreddit $subreddit): View
    {
        $subreddit->loadCount(['posts', 'collectionLogs']);

        return view('admin.subreddits.show', [
            'subreddit' => $subreddit,
            'recentLogs' => $subreddit->collectionLogs()->latest('id')->limit(5)->get(),
        ]);
    }

    /**
     * Show the form for editing a subreddit.
     */
    public function edit(Subreddit $subreddit): View
    {
        return view('admin.subreddits.edit', compact('subreddit'));
    }

    /**
     * Update an existing subreddit.
     */
    public function update(SubredditRequest $request, Subreddit $subreddit): RedirectResponse
    {
        $subreddit = $this->subreddits->update($subreddit, $request->validated());

        return redirect()
            ->route('admin.subreddits.show', $subreddit)
            ->with('success', "r/{$subreddit->name} has been updated.");
    }

    /**
     * Activate or pause a subreddit.
     */
    public function updateStatus(SubredditStatusRequest $request, Subreddit $subreddit): JsonResponse|RedirectResponse
    {
        $subreddit = $this->subreddits->updateStatus(
            $subreddit,
            SubredditStatus::from($request->validated('status'))
        );

        $message = "r/{$subreddit->name} is now ".strtolower($subreddit->status->label()).'.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $subreddit->status->value,
                'label' => $subreddit->status->label(),
                'badge_class' => $subreddit->status->badgeClass(),
                'next_status' => $subreddit->status->toggle()->value,
                'next_label' => $subreddit->status->toggleLabel(),
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Delete a subreddit and its related posts.
     */
    public function destroy(Request $request, Subreddit $subreddit): JsonResponse|RedirectResponse
    {
        $name = $subreddit->name;

        $this->subreddits->delete($subreddit);

        $message = "r/{$name} has been deleted.";

        if ($request->expectsJson()) {
            return response()->json([
                'deleted' => true,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('admin.subreddits.index')
            ->with('success', $message);
    }
}
