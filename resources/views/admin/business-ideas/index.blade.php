<x-layouts.admin title="Business Ideas">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Business Ideas']]" />

    <x-page-header
        title="Business Ideas"
        subtitle="AI-generated opportunities linked to pain points, Reddit posts, and saved analyses."
    />

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Generated opportunities</h2>
                <small>{{ number_format($businessIdeas->total()) }} {{ \Illuminate\Support\Str::plural('idea', $businessIdeas->total()) }}</small>
            </div>

            <form method="GET" action="{{ route('admin.business-ideas.index') }}" class="subreddit-filters" role="search">
                <div class="input-group input-group-sm subreddit-search">
                    <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Search ideas..." aria-label="Search business ideas">
                </div>

                <select name="subreddit_id" class="form-select form-select-sm" aria-label="Filter by subreddit">
                    <option value="">All subreddits</option>
                    @foreach ($subreddits as $subreddit)
                        <option value="{{ $subreddit->id }}" @selected((string) $filters['subreddit_id'] === (string) $subreddit->id)>
                            r/{{ $subreddit->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-select form-select-sm" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="reviewed" @selected($filters['status'] === 'reviewed')>Reviewed</option>
                    <option value="validated" @selected($filters['status'] === 'validated')>Validated</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                </select>

                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>

                @if ($filters['search'] !== '' || $filters['subreddit_id'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('admin.business-ideas.index') }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>

        @if ($businessIdeas->isEmpty())
            <div class="empty-state">
                <i class="bi bi-lightbulb" aria-hidden="true"></i>
                No business ideas match the current filters. Analyze a Reddit post to generate structured opportunities.
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table business-ideas-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Business Idea</th>
                            <th scope="col">Target Market</th>
                            <th scope="col">Source</th>
                            <th scope="col">Pain Point</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($businessIdeas as $idea)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.business-ideas.show', $idea) }}" class="fw-semibold text-decoration-none">
                                        {{ $idea->title }}
                                    </a>
                                    <div class="small text-muted">{{ $idea->product_type ?: 'Product type not specified' }}</div>
                                    <div class="mt-1">
                                        <x-ai-generated-badge :is-ai-generated="$idea->is_ai_generated" />
                                    </div>
                                </td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($idea->target_market ?: 'Not specified', 80) }}</td>
                                <td class="small">
                                    @if ($idea->sourcePost)
                                        <a href="{{ route('admin.reddit-posts.show', $idea->sourcePost) }}">
                                            {{ \Illuminate\Support\Str::limit($idea->sourcePost->title, 45) }}
                                        </a>
                                        <div class="text-muted">r/{{ $idea->sourcePost->subreddit?->name }}</div>
                                    @else
                                        <span class="text-muted">Unavailable</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($idea->painPoint)
                                        <a href="{{ route('admin.pain-points.show', $idea->painPoint) }}">
                                            {{ \Illuminate\Support\Str::limit($idea->painPoint->title, 45) }}
                                        </a>
                                    @else
                                        <span class="text-muted">Unlinked</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge rounded-pill badge-soft-secondary text-capitalize">{{ $idea->status }}</span>
                                </td>
                                <td class="small text-muted">{{ $idea->created_at?->format('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.business-ideas.show', $idea) }}" class="btn btn-sm btn-outline-secondary">
                                        View details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body pt-0">
                <x-pagination :paginator="$businessIdeas" />
            </div>
        @endif
    </div>
</x-layouts.admin>
