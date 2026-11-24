<x-layouts.admin title="AI Analysis">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'AI Analysis']]" />

    <x-page-header
        title="AI Analysis"
        subtitle="Review saved DeepSeek reports, versions, token usage, and analysis history."
    />

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Analysis versions</h2>
                <small>{{ number_format($analyses->total()) }} saved {{ \Illuminate\Support\Str::plural('version', $analyses->total()) }}</small>
            </div>

            <form method="GET" action="{{ route('admin.ai-analysis.index') }}" class="subreddit-filters" role="search">
                <div class="input-group input-group-sm subreddit-search">
                    <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Search post title..." aria-label="Search analysis reports">
                </div>

                <select name="subreddit_id" class="form-select form-select-sm" aria-label="Filter by subreddit">
                    <option value="">All subreddits</option>
                    @foreach ($subreddits as $subreddit)
                        <option value="{{ $subreddit->id }}" @selected((string) $filters['subreddit_id'] === (string) $subreddit->id)>
                            r/{{ $subreddit->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-select form-select-sm" aria-label="Filter by analysis status">
                    <option value="">All statuses</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    <option value="processing" @selected($filters['status'] === 'processing')>Processing</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                </select>

                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-sm" aria-label="Analysis date from">
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-sm" aria-label="Analysis date to">

                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>

                @if (collect($filters)->contains(fn ($value): bool => $value !== null && $value !== ''))
                    <a href="{{ route('admin.ai-analysis.index') }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>

        @if ($analyses->isEmpty())
            <div class="empty-state">
                <i class="bi bi-cpu" aria-hidden="true"></i>
                No AI analysis versions match the current filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table ai-analysis-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Post</th>
                            <th scope="col">Analysis</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tokens</th>
                            <th scope="col">Version</th>
                            <th scope="col" class="text-end">Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analyses as $analysis)
                            <tr>
                                <td>
                                    @if ($analysis->redditPost)
                                        <a href="{{ route('admin.reddit-posts.show', $analysis->redditPost) }}" class="fw-semibold text-decoration-none">
                                            {{ \Illuminate\Support\Str::limit($analysis->redditPost->title, 110) }}
                                        </a>
                                        <div class="small text-muted">
                                            r/{{ $analysis->redditPost->subreddit?->name ?? 'unknown' }}
                                        </div>
                                    @else
                                        <span class="text-muted">Deleted post</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <div>{{ $analysis->analyzed_at?->format('d M Y, H:i') ?? $analysis->created_at?->format('d M Y, H:i') }}</div>
                                    <div class="text-muted">{{ $analysis->provider }} · {{ $analysis->model }}</div>
                                </td>
                                <td>
                                    <x-ai-analysis-badge :status="$analysis->analysis_status" />
                                </td>
                                <td class="small text-nowrap">
                                    @if ($analysis->total_tokens === null)
                                        <span class="text-muted">Not provided</span>
                                    @else
                                        <div>Total: {{ number_format($analysis->total_tokens) }}</div>
                                        <div class="text-muted">
                                            @if ($analysis->prompt_tokens !== null)Prompt {{ number_format($analysis->prompt_tokens) }}@endif
                                            @if ($analysis->prompt_tokens !== null && $analysis->completion_tokens !== null) · @endif
                                            @if ($analysis->completion_tokens !== null)Completion {{ number_format($analysis->completion_tokens) }}@endif
                                        </div>
                                    @endif
                                </td>
                                <td>v{{ $analysis->version }}</td>
                                <td class="text-end">
                                    @if ($analysis->analysis_status === 'completed')
                                        <a href="{{ route('admin.reddit-posts.analysis', ['redditPost' => $analysis->redditPost, 'version' => $analysis->version]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> View Report
                                        </a>
                                    @else
                                        <span class="small text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body pt-0">
                <x-pagination :paginator="$analyses" />
            </div>
        @endif
    </div>
</x-layouts.admin>
