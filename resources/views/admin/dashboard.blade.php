<x-layouts.admin title="Overview">
    <x-breadcrumb :items="[['label' => 'Overview']]" />

    <x-page-header
        title="Application overview"
        subtitle="Collection health, qualification, AI analysis, and generated business intelligence."
    >
        <x-slot:actions>
            <span class="badge rounded-pill badge-soft border px-3 py-2">
                <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i> Application complete
            </span>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach ($metrics as $metric)
            <x-stat-card
                :label="$metric['label']"
                :value="$metric['value']"
                :icon="$metric['icon']"
                :tone="$metric['tone']"
                :route="$metric['route']"
            />
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-heading">
                    <div>
                        <h2>Recent collection activity</h2>
                        <small>The five most recent collection runs</small>
                    </div>
                    <a href="{{ route('admin.collection-logs.index') }}" class="btn btn-sm btn-outline-secondary">
                        View logs
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Subreddit</th>
                                <th scope="col">Type</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Fetched</th>
                                <th scope="col">Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLogs as $log)
                                <tr>
                                    <td>
                                        @if ($log->subreddit)
                                            <a href="{{ route('admin.subreddits.show', $log->subreddit) }}" class="text-decoration-none">
                                                r/{{ $log->subreddit->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="text-capitalize small">{{ $log->collection_type }}</td>
                                    <td>
                                        <span class="badge rounded-pill {{ match ($log->status) {
                                            'completed' => 'badge-soft-success',
                                            'failed' => 'badge-soft-danger',
                                            'running' => 'badge-soft-info',
                                            default => 'badge-soft-secondary',
                                        } }}">{{ ucfirst($log->status) }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($log->posts_fetched) }}</td>
                                    <td class="small text-muted">{{ $log->started_at?->format('d M Y, H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="bi bi-inboxes" aria-hidden="true"></i>
                                            No collection runs yet.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-heading">
                    <div>
                        <h2>Recent AI analyses</h2>
                        <small>Latest saved analysis versions</small>
                    </div>
                    <a href="{{ route('admin.ai-analysis.index') }}" class="btn btn-sm btn-outline-secondary">
                        All analyses
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Post</th>
                                <th scope="col">Status</th>
                                <th scope="col">Tokens</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentAnalyses as $analysis)
                                <tr>
                                    <td>
                                        @if ($analysis->redditPost)
                                            <a href="{{ route('admin.reddit-posts.show', $analysis->redditPost) }}" class="text-decoration-none">
                                                {{ \Illuminate\Support\Str::limit($analysis->redditPost->title, 45) }}
                                            </a>
                                        @else
                                            <span class="text-muted">Deleted post</span>
                                        @endif
                                        <div class="small text-muted">
                                            v{{ $analysis->version }} · {{ $analysis->analyzed_at?->format('d M Y') ?? '—' }}
                                        </div>
                                    </td>
                                    <td><x-ai-analysis-badge :status="$analysis->analysis_status" /></td>
                                    <td class="small">
                                        {{ $analysis->total_tokens === null ? 'Not provided' : number_format($analysis->total_tokens) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <i class="bi bi-cpu" aria-hidden="true"></i>
                                            No AI analyses yet. Analyze a post to create a report.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-heading">
                    <h2>System information</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-5">Environment</dt>
                        <dd class="col-7 text-capitalize">{{ $system['environment'] }}</dd>

                        <dt class="col-5">Laravel</dt>
                        <dd class="col-7">{{ $system['laravel'] }}</dd>

                        <dt class="col-5">PHP</dt>
                        <dd class="col-7">{{ $system['php'] }}</dd>

                        <dt class="col-5">Timezone</dt>
                        <dd class="col-7">{{ $system['timezone'] }}</dd>

                        <dt class="col-5">Database</dt>
                        <dd class="col-7 text-capitalize">{{ config('database.default') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-heading">
                    <h2>Quick links</h2>
                </div>
                <div class="row g-0">
                    <div class="col-md-6">
                        <div class="list-group list-group-flush">
                            <a href="{{ route('admin.pain-points.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Pain points
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('admin.business-ideas.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Business ideas
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="list-group list-group-flush">
                            <a href="{{ route('admin.ai-analysis.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                AI analysis reports
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('admin.settings.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Application settings
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info admin-alert d-flex gap-2 mt-4" role="alert">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>Collection, qualification, and manual DeepSeek analysis are active.</strong>
            AI-generated pain points and business ideas are hypotheses, not validated market research.
        </div>
    </div>
</x-layouts.admin>
