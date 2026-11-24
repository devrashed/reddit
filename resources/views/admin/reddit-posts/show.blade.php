@php
    $redditUrl = $post->post_url
        ?: ($post->permalink ? 'https://www.reddit.com'.$post->permalink : null);
    $postSubtitle = 'r/'.($post->subreddit?->name ?? 'unknown')
        .' · u/'.($post->author ?: '[deleted]')
        .' · '.($post->reddit_created_at?->format('d M Y, H:i') ?? 'Date unavailable');
@endphp

<x-layouts.admin :title="$post->title">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Reddit Posts', 'url' => route('admin.reddit-posts.index')],
        ['label' => \Illuminate\Support\Str::limit($post->title, 60)],
    ]" />

    <x-page-header :title="$post->title" :subtitle="$postSubtitle">
        <x-slot:actions>
            <x-qualification-badge :status="$post->qualification_status" />
            <x-comment-collection-badge :status="$post->comments_collection_status" />
            <x-ai-analysis-badge :status="$latestAiAnalysisStatus" />

            @if ($redditUrl)
                <a href="{{ $redditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Reddit
                </a>
            @endif

            <x-analyze-button
                :post="$post"
                :status="$latestAiAnalysisStatus"
                :completed-version="$latestCompletedAiVersion"
            />

            <a href="{{ route('admin.reddit-posts.index') }}" class="btn btn-sm btn-light">
                All posts
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-heading">
                    <div>
                        <h2>Post content</h2>
                        <small>Original Reddit self-text</small>
                    </div>
                    <span class="badge text-bg-light border">{{ $post->is_self ? 'Self post' : 'Link post' }}</span>
                </div>
                <div class="card-body">
                    @if (filled($post->selftext))
                        <div class="post-full-text">{{ $post->selftext }}</div>
                    @else
                        <div class="empty-state py-4">
                            <i class="bi bi-file-text" aria-hidden="true"></i>
                            No self-text content is available for this post.
                            @if ($redditUrl)
                                <div class="mt-3">
                                    <a href="{{ $redditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                        Open the original discussion
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-heading">
                    <div>
                        <h2>Saved comments</h2>
                        <small>
                            {{ number_format($savedCommentsCount) }} saved
                            @if ($displayedCommentsCount < $savedCommentsCount)
                                &middot; showing {{ number_format($displayedCommentsCount) }}
                            @endif
                        </small>
                    </div>
                    <x-comment-collection-badge :status="$post->comments_collection_status" />
                </div>

                @if (empty($commentThreads))
                    <div class="empty-state py-4">
                        <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                        No saved comments are available for this post yet.
                        Comments may not have been collected or may not be accessible through Reddit's public endpoint.
                    </div>
                @else
                    <div class="card-body comment-thread">
                        @include('admin.reddit-posts._comment-thread', ['nodes' => $commentThreads])
                    </div>

                    @if ($displayedCommentsCount < $savedCommentsCount)
                        <div class="card-body border-top small text-muted">
                            Showing the {{ number_format($displayedCommentsCount) }} highest-scoring saved comments
                            of {{ number_format($savedCommentsCount) }}.
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Post metrics</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-6">Score</dt>
                        <dd class="col-6 text-end">{{ number_format($post->score) }}</dd>

                        <dt class="col-6">Reddit comments</dt>
                        <dd class="col-6 text-end">{{ number_format($post->num_comments) }}</dd>

                        <dt class="col-6">Saved comments</dt>
                        <dd class="col-6 text-end">{{ number_format($savedCommentsCount) }}</dd>

                        <dt class="col-6">Upvote ratio</dt>
                        <dd class="col-6 text-end">
                            {{ $post->upvote_ratio !== null ? number_format((float) $post->upvote_ratio * 100, 1).'%' : 'Unavailable' }}
                        </dd>

                        <dt class="col-6">Published</dt>
                        <dd class="col-6 text-end">{{ $post->reddit_created_at?->format('d M Y, H:i') ?? 'Unavailable' }}</dd>

                        <dt class="col-6">First fetched</dt>
                        <dd class="col-6 text-end">{{ $post->first_fetched_at?->format('d M Y, H:i') ?? 'Unavailable' }}</dd>

                        <dt class="col-6">Last fetched</dt>
                        <dd class="col-6 text-end">{{ $post->last_fetched_at?->format('d M Y, H:i') ?? 'Unavailable' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Workflow status</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-6">Qualification</dt>
                        <dd class="col-6 text-end">
                            <x-qualification-badge :status="$post->qualification_status" />
                        </dd>

                        <dt class="col-6">Comments</dt>
                        <dd class="col-6 text-end">
                            <x-comment-collection-badge :status="$post->comments_collection_status" />
                        </dd>

                        <dt class="col-6">AI analysis</dt>
                        <dd class="col-6 text-end">
                            <x-ai-analysis-badge :status="$latestAiAnalysisStatus" />
                        </dd>

                        <dt class="col-6">Qualified at</dt>
                        <dd class="col-6 text-end">{{ $post->qualified_at?->format('d M Y, H:i') ?? '—' }}</dd>

                        <dt class="col-6">Comments collected</dt>
                        <dd class="col-6 text-end">{{ $post->comments_collected_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-heading">
                    <h2>Source</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-5">Subreddit</dt>
                        <dd class="col-7 text-end">
                            @if ($post->subreddit)
                                <a href="{{ route('admin.subreddits.show', $post->subreddit) }}" class="fw-semibold">
                                    r/{{ $post->subreddit->name }}
                                </a>
                            @else
                                Deleted
                            @endif
                        </dd>

                        <dt class="col-5">Author</dt>
                        <dd class="col-7 text-end">u/{{ $post->author ?: '[deleted]' }}</dd>
                    </dl>

                    @if ($redditUrl)
                        <a href="{{ $redditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary w-100 mt-3">
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                            Open Reddit discussion
                        </a>
                    @endif

                    <p class="small text-muted mb-0 mt-3">
                        AI analysis runs only when an administrator explicitly requests it. Reports, model details,
                        token usage, and prior versions are saved for later viewing.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
