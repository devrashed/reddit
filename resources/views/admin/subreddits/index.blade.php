<x-layouts.admin title="Subreddits">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Subreddits']]" />

    <x-page-header
        title="Subreddits"
        subtitle="Manage the Reddit communities used for discovery and initial collection."
    >
        <x-slot:actions>
            <a href="{{ route('admin.subreddits.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add Subreddit
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Tracked communities</h2>
                <small>
                    <span id="subredditTotalCount">{{ number_format($subreddits->total()) }}</span>
                    tracked {{ \Illuminate\Support\Str::plural('community', $subreddits->total()) }}
                </small>
            </div>

            <form method="GET" action="{{ route('admin.subreddits.index') }}" class="subreddit-filters" role="search">
                <div class="input-group input-group-sm subreddit-search">
                    <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search"
                           name="search"
                           value="{{ $filters['search'] }}"
                           class="form-control"
                           placeholder="Search subreddits..."
                           aria-label="Search subreddits">
                </div>

                <select name="status" class="form-select form-select-sm" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\SubredditStatus::cases() as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>
                            {{ $statusOption->label() }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>

                @if ($filters['search'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('admin.subreddits.index') }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>

        @if ($subreddits->isEmpty())
            <div class="empty-state">
                <i class="bi bi-collection" aria-hidden="true"></i>

                @if ($filters['search'] !== '' || $filters['status'] !== '')
                    No subreddits match the current filters.
                    <div class="mt-3">
                        <a href="{{ route('admin.subreddits.index') }}" class="btn btn-sm btn-outline-secondary">
                            Clear filters
                        </a>
                    </div>
                @else
                    No subreddits have been added yet.
                    <div class="mt-3">
                        <a href="{{ route('admin.subreddits.create') }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add your first subreddit
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table subreddit-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Subreddit</th>
                            <th scope="col">URL</th>
                            <th scope="col">Status</th>
                            <th scope="col">Initial Collection</th>
                            <th scope="col">Last Collected</th>
                            <th scope="col">Created Date</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subreddits as $subreddit)
                            @php
                                $deleteConfirmation = 'Delete r/'.$subreddit->name.'?'
                                    .($subreddit->posts_count > 0
                                        ? ' '.number_format($subreddit->posts_count).' related '.\Illuminate\Support\Str::plural('post', $subreddit->posts_count).' will also be deleted.'
                                        : '')
                                    .' This action cannot be undone.';
                            @endphp

                            <tr id="subreddit-row-{{ $subreddit->id }}" data-subreddit-row data-ajax-row>
                                <td>
                                    <a href="{{ route('admin.subreddits.show', $subreddit) }}" class="fw-semibold text-decoration-none">
                                        r/{{ $subreddit->name }}
                                    </a>
                                    <div class="small text-muted">{{ $subreddit->display_name }}</div>
                                    <div class="small text-muted">
                                        {{ number_format($subreddit->posts_count) }}
                                        {{ \Illuminate\Support\Str::plural('post', $subreddit->posts_count) }}
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ $subreddit->url }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="subreddit-url small"
                                       title="{{ $subreddit->url }}">
                                        {{ $subreddit->url }}
                                    </a>
                                </td>
                                <td>
                                    <x-subreddit-status-badge :status="$subreddit->status" />
                                </td>
                                <td>
                                    <x-initial-collection-badge :status="$subreddit->initial_collection_status" />
                                </td>
                                <td class="small text-muted">
                                    {{ $subreddit->last_collected_at?->format('d M Y, H:i') ?? '—' }}
                                </td>
                                <td class="small text-muted">
                                    {{ $subreddit->created_at?->format('d M Y') }}
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <a href="{{ route('admin.subreddits.show', $subreddit) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="View r/{{ $subreddit->name }}"
                                           aria-label="View r/{{ $subreddit->name }}">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>

                                        <a href="{{ route('admin.subreddits.edit', $subreddit) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Edit r/{{ $subreddit->name }}"
                                           aria-label="Edit r/{{ $subreddit->name }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.subreddits.status', $subreddit) }}"
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
                                            </button>
                                        </form>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-confirm="{{ $deleteConfirmation }}"
                                                data-confirm-button="Delete subreddit"
                                                data-confirm-ajax="{{ route('admin.subreddits.destroy', $subreddit) }}"
                                                data-confirm-method="DELETE"
                                                data-confirm-remove="#subreddit-row-{{ $subreddit->id }}"
                                                data-confirm-count="#subredditTotalCount"
                                                title="Delete r/{{ $subreddit->name }}"
                                                aria-label="Delete r/{{ $subreddit->name }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body pt-0">
                <x-pagination :paginator="$subreddits" />
            </div>
        @endif
    </div>
</x-layouts.admin>
