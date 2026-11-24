@php
    $hasFilters = collect($filters)
        ->except('sort')
        ->contains(fn ($value): bool => $value !== null && $value !== '');
@endphp

@if ($posts->isEmpty())
    <div class="empty-state posts-empty-state">
        <i class="bi bi-inbox" aria-hidden="true"></i>

        @if (! empty($filters['search']))
            <h2 class="h5">No posts match your search.</h2>
            <p class="mb-0">Try a different keyword or clear the current filters.</p>
        @elseif (! empty($filters['subreddit_id']))
            <h2 class="h5">No posts exist for the selected subreddit.</h2>
            <p class="mb-0">Choose another community or clear the current filters.</p>
        @elseif (($filters['qualification_status'] ?? '') === \App\Enums\PostQualificationStatus::Qualified->value)
            <h2 class="h5">No qualified posts match the current filters.</h2>
            <p class="mb-0">Posts remain visible while their engagement is pending.</p>
        @elseif ($hasFilters)
            <h2 class="h5">No posts match the current filters.</h2>
            <p class="mb-0">Adjust the filters to see more collected posts.</p>
        @else
            <h2 class="h5">No Reddit posts have been collected yet.</h2>
            <p class="mb-0">Initial collection will populate this page after a subreddit is processed.</p>
        @endif

        @if ($hasFilters)
            <a href="{{ route('admin.reddit-posts.index') }}" class="btn btn-sm btn-outline-secondary mt-3">
                Clear filters
            </a>
        @endif
    </div>
@else
    <div class="table-responsive">
        <table class="table admin-table posts-table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Post</th>
                    <th scope="col">Engagement</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    @php
                        $redditUrl = $post->post_url
                            ?: ($post->permalink ? 'https://www.reddit.com'.$post->permalink : null);
                    @endphp

                    <tr>
                        <td>
                            <a href="{{ route('admin.reddit-posts.show', $post) }}" class="post-row-title">
                                {{ $post->title }}
                            </a>
                            <div class="post-preview small text-muted">
                                {{ \Illuminate\Support\Str::limit($post->selftext ?: 'No self-text content available.', 180) }}
                            </div>
                            <div class="post-row-meta small text-muted">
                                <a href="{{ route('admin.reddit-posts.index', ['subreddit_id' => $post->subreddit_id]) }}" class="text-decoration-none">
                                    r/{{ $post->subreddit?->name }}
                                </a>
                                <span aria-hidden="true">&middot;</span>
                                u/{{ $post->author ?: '[deleted]' }}
                                <span aria-hidden="true">&middot;</span>
                                {{ $post->reddit_created_at?->format('d M Y, H:i') ?? 'Date unavailable' }}
                            </div>
                        </td>

                        <td class="small text-nowrap">
                            <div><i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i>{{ number_format($post->score) }} score</div>
                            <div><i class="bi bi-chat-dots me-1" aria-hidden="true"></i>{{ number_format($post->num_comments) }} comments</div>
                            <div class="text-muted"><i class="bi bi-bookmark me-1" aria-hidden="true"></i>{{ number_format($post->comments_count) }} saved</div>
                        </td>

                        <td>
                            <div class="status-stack">
                                <x-qualification-badge :status="$post->qualification_status" />
                                <x-comment-collection-badge :status="$post->comments_collection_status" />
                                <x-ai-analysis-badge :status="$post->latest_ai_analysis_status" />
                            </div>
                        </td>

                        <td class="text-end">
                            <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                <a href="{{ route('admin.reddit-posts.show', $post) }}"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="View post details"
                                   aria-label="View details for {{ $post->title }}">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </a>

                                @if ($redditUrl)
                                    <a href="{{ $redditUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Open original Reddit discussion"
                                       aria-label="Open original Reddit discussion for {{ $post->title }}">
                                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                    </a>
                                @endif

                                <x-analyze-button
                                    :post="$post"
                                    :status="$post->latest_ai_analysis_status"
                                    :completed-version="$post->latest_completed_ai_version"
                                />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card-body pt-0">
        <x-pagination :paginator="$posts" />
    </div>
@endif
