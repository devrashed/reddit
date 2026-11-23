<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollectionLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionLogController extends Controller
{
    /**
     * Display the collection run logs.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([
                CollectionLog::TYPE_INITIAL,
                CollectionLog::TYPE_REGULAR,
                CollectionLog::TYPE_COMMENTS,
            ])],
            'status' => ['nullable', Rule::in([
                CollectionLog::STATUS_RUNNING,
                CollectionLog::STATUS_COMPLETED,
                CollectionLog::STATUS_FAILED,
            ])],
        ]);

        $logs = CollectionLog::query()
            ->with('subreddit')
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('collection_type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.collection-logs.index', [
            'logs' => $logs,
            'filters' => [
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }
}
