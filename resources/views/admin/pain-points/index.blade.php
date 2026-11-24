<x-layouts.admin title="Pain Points">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Pain Points']]" />

    <x-page-header
        title="Pain Points"
        subtitle="AI-generated pain points traced to their Reddit source and saved analysis."
    />

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Discovered pain points</h2>
                <small>{{ number_format($painPoints->total()) }} {{ \Illuminate\Support\Str::plural('record', $painPoints->total()) }}</small>
            </div>

            <form method="GET" action="{{ route('admin.pain-points.index') }}" class="subreddit-filters" role="search">
                <div class="input-group input-group-sm subreddit-search">
                    <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Search pain points..." aria-label="Search pain points">
                </div>

                <select name="subreddit_id" class="form-select form-select-sm" aria-label="Filter by subreddit">
                    <option value="">All subreddits</option>
                    @foreach ($subreddits as $subreddit)
                        <option value="{{ $subreddit->id }}" @selected((string) $filters['subreddit_id'] === (string) $subreddit->id)>
                            r/{{ $subreddit->name }}
                        </option>
                    @endforeach
                </select>

                <select name="severity" class="form-select form-select-sm" aria-label="Filter by severity">
                    <option value="">All severities</option>
                    <option value="not_assessed" @selected($filters['severity'] === 'not_assessed')>Not assessed</option>
                    <option value="low" @selected($filters['severity'] === 'low')>Low</option>
                    <option value="medium" @selected($filters['severity'] === 'medium')>Medium</option>
                    <option value="high" @selected($filters['severity'] === 'high')>High</option>
                </select>

                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>

                @if ($filters['search'] !== '' || $filters['subreddit_id'] !== '' || $filters['severity'] !== '')
                    <a href="{{ route('admin.pain-points.index') }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>

        @if ($painPoints->isEmpty())
            <div class="empty-state">
                <i class="bi bi-bandaid" aria-hidden="true"></i>
                No pain points match the current filters. Analyze a Reddit post to generate structured findings.
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table pain-points-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Pain Point</th>
                            <th scope="col">Subreddit</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Source</th>
                            <th scope="col">Analysis</th>
                            <th scope="col">Created</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($painPoints as $painPoint)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.pain-points.show', $painPoint) }}" class="fw-semibold text-decoration-none">
                                        {{ $painPoint->title }}
                                    </a>
                                    <div class="small text-muted post-preview">
                                        {{ \Illuminate\Support\Str::limit($painPoint->description ?: 'No description available.', 160) }}
                                    </div>
                                    <div class="mt-1">
                                        <x-ai-generated-badge :is-ai-generated="$painPoint->is_ai_generated" />
                                    </div>
                                </td>
                                <td>
                                    @if ($painPoint->sourcePost?->subreddit)
                                        r/{{ $painPoint->sourcePost->subreddit->name }}
                                    @else
                                        <span class="text-muted">Unavailable</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($painPoint->severity)
                                        <span class="badge rounded-pill badge-soft-warning text-capitalize">{{ $painPoint->severity }}</span>
                                    @else
                                        <span class="small text-muted">Not assessed</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($painPoint->sourcePost)
                                        <a href="{{ route('admin.reddit-posts.show', $painPoint->sourcePost) }}" class="small">
                                            {{ \Illuminate\Support\Str::limit($painPoint->sourcePost->title, 55) }}
                                        </a>
                                    @else
                                        <span class="text-muted small">Source unavailable</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($painPoint->aiAnalysis)
                                        <div>Version {{ $painPoint->aiAnalysis->version }}</div>
                                        <div class="text-muted">{{ $painPoint->aiAnalysis->analyzed_at?->format('d M Y') ?? '—' }}</div>
                                    @else
                                        <span class="text-muted">Unlinked</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $painPoint->created_at?->format('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.pain-points.show', $painPoint) }}" class="btn btn-sm btn-outline-secondary">
                                        View details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body pt-0">
                <x-pagination :paginator="$painPoints" />
            </div>
        @endif
    </div>
</x-layouts.admin>
