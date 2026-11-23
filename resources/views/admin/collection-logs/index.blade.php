<x-layouts.admin title="Collection Logs">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Collection Logs']]" />

    <x-page-header
        title="Collection Logs"
        subtitle="Review initial, regular, and partial Reddit collection runs."
    >
        <x-slot:actions>
            <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-1" aria-hidden="true"></i> Settings
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Run history</h2>
                <small>{{ number_format($logs->total()) }} logged {{ \Illuminate\Support\Str::plural('run', $logs->total()) }}</small>
            </div>

            <form method="GET" action="{{ route('admin.collection-logs.index') }}" class="subreddit-filters" role="search">
                <select name="type" class="form-select form-select-sm" aria-label="Filter by collection type">
                    <option value="">All types</option>
                    <option value="initial" @selected($filters['type'] === 'initial')>Initial</option>
                    <option value="regular" @selected($filters['type'] === 'regular')>Regular</option>
                    <option value="comments" @selected($filters['type'] === 'comments')>Comments</option>
                </select>

                <select name="status" class="form-select form-select-sm" aria-label="Filter by collection status">
                    <option value="">All statuses</option>
                    <option value="running" @selected($filters['status'] === 'running')>Running</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                </select>

                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>

                @if ($filters['type'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('admin.collection-logs.index') }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>

        @if ($logs->isEmpty())
            <div class="empty-state">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                No collection runs match the current filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table collection-log-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Started</th>
                            <th scope="col">Subreddit</th>
                            <th scope="col">Status</th>
                            <th scope="col">Posts</th>
                            <th scope="col">Qualification</th>
                            <th scope="col" class="text-end">Comments</th>
                            <th scope="col">Notes / Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $duration = $log->started_at && $log->finished_at
                                    ? (int) $log->started_at->diffInSeconds($log->finished_at)
                                    : null;
                                $durationLabel = $duration === null
                                    ? '—'
                                    : ($duration >= 60 ? floor($duration / 60).'m '.($duration % 60).'s' : $duration.'s');
                                $summary = $log->error_message ?: $log->notes;
                            @endphp

                            <tr>
                                <td class="small text-muted text-nowrap">
                                    <div>{{ $log->started_at?->format('d M Y, H:i:s') ?? '—' }}</div>
                                    <div>{{ $durationLabel }}</div>
                                </td>
                                <td>
                                    @if ($log->subreddit)
                                        <a href="{{ route('admin.subreddits.show', $log->subreddit) }}" class="fw-semibold text-decoration-none">
                                            r/{{ $log->subreddit->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">Deleted subreddit</span>
                                    @endif
                                    <div class="mt-1">
                                        <span class="badge text-bg-light border text-capitalize">{{ $log->collection_type }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill {{ match ($log->status) {
                                        'completed' => 'badge-soft-success',
                                        'failed' => 'badge-soft-danger',
                                        'running' => 'badge-soft-info',
                                        default => 'badge-soft-secondary',
                                    } }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="small text-nowrap">
                                    <div>Fetched: <strong>{{ number_format($log->posts_fetched) }}</strong></div>
                                    <div class="text-success">New: {{ number_format($log->posts_created) }}</div>
                                    <div class="text-muted">Updated: {{ number_format($log->posts_updated) }}</div>
                                </td>
                                <td class="small text-nowrap">
                                    <div>Newly qualified: <strong>{{ number_format($log->newly_qualified_posts) }}</strong></div>
                                    <div class="text-muted">Pending checked: {{ number_format($log->pending_posts_checked) }}</div>
                                    <div class="{{ $log->failed_operations > 0 ? 'text-danger' : 'text-muted' }}">
                                        Failed ops: {{ number_format($log->failed_operations) }}
                                    </div>
                                </td>
                                <td class="text-end">{{ number_format($log->comments_fetched) }}</td>
                                <td class="small {{ $log->error_message ? 'text-danger' : 'text-muted' }}">
                                    <span class="d-inline-block" style="max-width: 280px; white-space: normal;" title="{{ $summary }}">
                                        {{ $summary ?: '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body pt-0">
                <x-pagination :paginator="$logs" />
            </div>
        @endif
    </div>
</x-layouts.admin>
