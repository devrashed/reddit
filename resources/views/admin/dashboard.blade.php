<x-layouts.admin title="Overview">
    <x-breadcrumb :items="[['label' => 'Overview']]" />

    <x-page-header
        title="Business discovery overview"
        subtitle="Track collected signals, extracted pain points, and generated opportunities in one place."
    >
        <x-slot:actions>
            <span class="badge rounded-pill badge-soft border px-3 py-2">
                <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i> Foundation ready
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
        <div class="col-lg-8">
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
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Subreddit</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Fetched</th>
                                <th scope="col">Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLogs as $log)
                                <tr>
                                    <td>{{ $log->subreddit?->display_name ?? 'Unassigned' }}</td>
                                    <td>
                                        <span class="badge text-bg-light border text-capitalize">{{ $log->status }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($log->posts_fetched) }}</td>
                                    <td class="text-muted small">
                                        {{ $log->started_at?->format('d M Y, H:i') ?? 'Not started' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-inboxes" aria-hidden="true"></i>
                                            No collection runs yet. Activity will appear here once Reddit collection
                                            is enabled.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>System information</h2>
                    <span class="badge text-bg-light border">Phase 1</span>
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

            <div class="card">
                <div class="card-heading">
                    <h2>Quick links</h2>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('admin.subreddits.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Manage subreddits
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('admin.ai-analysis.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        AI analysis history
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

    <div class="alert alert-info admin-alert d-flex gap-2 mt-4" role="alert">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>Phase 1 foundation is in place.</strong>
            Reddit collection, DeepSeek analysis, and idea scoring will be delivered in later phases.
        </div>
    </div>
</x-layouts.admin>
