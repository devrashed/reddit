@php
    $deleteConfirmation = 'Delete r/'.$subreddit->name.'?'
        .($subreddit->posts_count > 0
            ? ' '.number_format($subreddit->posts_count).' related '.\Illuminate\Support\Str::plural('post', $subreddit->posts_count).' will also be deleted.'
            : '')
        .' This action cannot be undone.';
@endphp

<x-layouts.admin :title="'r/'.$subreddit->name">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Subreddits', 'url' => route('admin.subreddits.index')],
        ['label' => 'r/'.$subreddit->name],
    ]" />

    <div data-subreddit-row>
        <x-page-header :title="'r/'.$subreddit->name" :subtitle="$subreddit->display_name">
            <x-slot:actions>
                <x-subreddit-status-badge :status="$subreddit->status" />

                <a href="{{ route('admin.subreddits.edit', $subreddit) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i> Edit
                </a>

                <form method="POST"
                      action="{{ route('admin.subreddits.status', $subreddit) }}"
                      class="d-inline-flex"
                      data-subreddit-status-form>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $subreddit->status->toggle()->value }}">

                    <button type="submit"
                            class="btn btn-sm btn-outline-secondary"
                            data-subreddit-toggle
                            data-url="{{ route('admin.subreddits.status', $subreddit) }}"
                            data-name="{{ $subreddit->name }}"
                            title="{{ $subreddit->status->toggleLabel() }} r/{{ $subreddit->name }}"
                            aria-label="{{ $subreddit->status->toggleLabel() }} r/{{ $subreddit->name }}">
                        <i class="bi {{ $subreddit->status->toggleIcon() }}" data-subreddit-toggle-icon aria-hidden="true"></i>
                        <span class="ms-1" data-subreddit-toggle-label>{{ $subreddit->status->toggleLabel() }}</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.subreddits.destroy', $subreddit) }}" class="d-inline-flex">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="{{ $deleteConfirmation }}"
                            data-confirm-button="Delete subreddit">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i> Delete
                    </button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @if ($subreddit->initial_collection_status === \App\Enums\InitialCollectionStatus::Pending)
            <div class="alert alert-info admin-alert d-flex gap-2" role="alert">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <div>
                    <strong>Initial historical collection is pending.</strong>
                    This subreddit is ready for Phase 3 collection processing. No collection job has been
                    dispatched and no AI analysis is run automatically.
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-heading">
                        <div>
                            <h2>Subreddit details</h2>
                            <small>Source, state, and timestamps</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <dl class="system-list row mb-0">
                            <dt class="col-sm-4">Subreddit</dt>
                            <dd class="col-sm-8">r/{{ $subreddit->name }}</dd>

                            <dt class="col-sm-4">Display name</dt>
                            <dd class="col-sm-8">{{ $subreddit->display_name }}</dd>

                            <dt class="col-sm-4">URL</dt>
                            <dd class="col-sm-8">
                                <a href="{{ $subreddit->url }}" target="_blank" rel="noopener noreferrer" class="text-break">
                                    {{ $subreddit->url }}
                                </a>
                            </dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <x-subreddit-status-badge :status="$subreddit->status" />
                            </dd>

                            <dt class="col-sm-4">Initial collection</dt>
                            <dd class="col-sm-8">
                                <x-initial-collection-badge :status="$subreddit->initial_collection_status" />
                            </dd>

                            <dt class="col-sm-4">Last collected</dt>
                            <dd class="col-sm-8">
                                {{ $subreddit->last_collected_at?->format('d M Y, H:i') ?? 'Never collected' }}
                            </dd>

                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8">{{ $subreddit->created_at?->format('d M Y, H:i') }}</dd>

                            <dt class="col-sm-4">Updated</dt>
                            <dd class="col-sm-8">{{ $subreddit->updated_at?->format('d M Y, H:i') }}</dd>

                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">
                                <span class="text-muted" style="white-space: pre-line;">{{ $subreddit->description ?: '—' }}</span>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-heading">
                        <div>
                            <h2>Recent collection runs</h2>
                            <small>The five most recent runs for this community</small>
                        </div>
                        <span class="badge text-bg-light border">Phase 3</span>
                    </div>

                    @if ($recentLogs->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            No collection runs have been recorded for this subreddit yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="text-end">Fetched</th>
                                        <th scope="col" class="text-end">Created</th>
                                        <th scope="col">Started</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentLogs as $log)
                                        <tr>
                                            <td>
                                                <span class="badge text-bg-light border text-capitalize">{{ $log->status }}</span>
                                            </td>
                                            <td class="text-end">{{ number_format($log->posts_fetched) }}</td>
                                            <td class="text-end">{{ number_format($log->posts_created) }}</td>
                                            <td class="small text-muted">
                                                {{ $log->started_at?->format('d M Y, H:i') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="stat-label">Collected posts</div>
                                <div class="stat-value">{{ number_format($subreddit->posts_count) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="stat-label">Collection runs</div>
                                <div class="stat-value">{{ number_format($subreddit->collection_logs_count) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="stat-label mb-1">Initial collection</div>
                                    <div class="small text-muted">Prepared for historical collection</div>
                                </div>
                                <x-initial-collection-badge :status="$subreddit->initial_collection_status" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-heading">
                        <h2>Collection timeline</h2>
                    </div>
                    <div class="card-body">
                        <dl class="system-list mb-0">
                            <dt>Initial collection started</dt>
                            <dd>{{ $subreddit->initial_collection_started_at?->format('d M Y, H:i') ?? 'Not started' }}</dd>

                            <dt>Initial collection completed</dt>
                            <dd>{{ $subreddit->initial_collection_completed_at?->format('d M Y, H:i') ?? 'Not completed' }}</dd>

                            <dt>Last collected</dt>
                            <dd>{{ $subreddit->last_collected_at?->format('d M Y, H:i') ?? 'Never collected' }}</dd>
                        </dl>

                        <p class="small text-muted mb-0 mt-3">
                            Historical collection will be implemented in Phase 3. This page currently records
                            readiness only.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
