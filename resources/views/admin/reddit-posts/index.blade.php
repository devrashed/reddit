@php
    $search = old('search', $filters['search'] ?? '');
    $subredditId = old('subreddit_id', $filters['subreddit_id'] ?? '');
    $qualification = old('qualification_status', $filters['qualification_status'] ?? '');
    $aiStatus = old('ai_status', $filters['ai_status'] ?? '');
    $dateFrom = old('date_from', $filters['date_from'] ?? '');
    $dateTo = old('date_to', $filters['date_to'] ?? '');
    $minScore = old('min_score', $filters['min_score'] ?? '');
    $minComments = old('min_comments', $filters['min_comments'] ?? '');
    $sortValue = old('sort', $sort ?? \App\Services\Reddit\RedditPostQueryService::SORT_NEWEST);

    $hasFilters = collect([$search, $subredditId, $qualification, $aiStatus, $dateFrom, $dateTo, $minScore, $minComments])
        ->contains(fn ($value): bool => $value !== null && $value !== '');

    $hasAdvancedFilters = collect([$aiStatus, $dateFrom, $dateTo, $minScore, $minComments])
        ->contains(fn ($value): bool => $value !== null && $value !== '');
@endphp

<x-layouts.admin title="Reddit Posts">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Reddit Posts']]" />

    <x-page-header
        title="Reddit Posts"
        subtitle="Browse collected posts, review engagement, and prepare AI analysis."
    >
        <x-slot:actions>
            <button type="button" class="btn btn-outline-secondary" data-posts-refresh>
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Refresh
            </button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('admin.reddit-posts.index') }}" id="postsFilterForm" class="card posts-filter-card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-4 col-lg-6">
                    <label for="postSearch" class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search"
                               id="postSearch"
                               name="search"
                               value="{{ $search }}"
                               class="form-control"
                               placeholder="Search title, content, or author..."
                               data-posts-search>
                    </div>
                </div>

                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label for="postSubreddit" class="form-label fw-semibold">Subreddit</label>
                    <select id="postSubreddit"
                            name="subreddit_id"
                            class="form-select"
                            data-posts-auto-submit>
                        <option value="">All subreddits</option>
                        @foreach ($subreddits as $subreddit)
                            <option value="{{ $subreddit->id }}" @selected((string) $subredditId === (string) $subreddit->id)>
                                r/{{ $subreddit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label for="postQualification" class="form-label fw-semibold">Qualification</label>
                    <select id="postQualification"
                            name="qualification_status"
                            class="form-select"
                            data-posts-auto-submit>
                        <option value="">All statuses</option>
                        @foreach (\App\Enums\PostQualificationStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($qualification === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label for="postSort" class="form-label fw-semibold">Sort by</label>
                    <select id="postSort" name="sort" class="form-select" data-posts-auto-submit>
                        <option value="newest" @selected($sortValue === 'newest')>Newest</option>
                        <option value="oldest" @selected($sortValue === 'oldest')>Oldest</option>
                        <option value="highest_score" @selected($sortValue === 'highest_score')>Highest score</option>
                        <option value="most_comments" @selected($sortValue === 'most_comments')>Most comments</option>
                        <option value="recently_updated" @selected($sortValue === 'recently_updated')>Recently updated</option>
                    </select>
                </div>

                <div class="col-xl-2 col-lg-8 col-md-6">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1" aria-hidden="true"></i> Apply
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                data-bs-toggle="collapse"
                                data-bs-target="#postsAdvancedFilters"
                                aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}"
                                aria-controls="postsAdvancedFilters">
                            <i class="bi bi-sliders" aria-hidden="true"></i>
                            <span class="visually-hidden">More filters</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="collapse mt-3 {{ $hasAdvancedFilters ? 'show' : '' }}" id="postsAdvancedFilters">
                <div class="row g-3">
                    <div class="col-xl-3 col-md-6">
                        <label for="postAiStatus" class="form-label fw-semibold">AI analysis</label>
                        <select id="postAiStatus" name="ai_status" class="form-select" data-posts-auto-submit>
                            <option value="">All AI statuses</option>
                            @foreach (\App\Enums\AiAnalysisStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($aiStatus === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-xl-2 col-md-6">
                        <label for="postDateFrom" class="form-label fw-semibold">Published from</label>
                        <input type="date" id="postDateFrom" name="date_from" value="{{ $dateFrom }}" class="form-control" data-posts-auto-submit>
                    </div>

                    <div class="col-xl-2 col-md-6">
                        <label for="postDateTo" class="form-label fw-semibold">Published to</label>
                        <input type="date" id="postDateTo" name="date_to" value="{{ $dateTo }}" class="form-control" data-posts-auto-submit>
                    </div>

                    <div class="col-xl-2 col-md-6">
                        <label for="postMinScore" class="form-label fw-semibold">Min score</label>
                        <input type="number" id="postMinScore" name="min_score" min="0" value="{{ $minScore }}" class="form-control" data-posts-auto-submit>
                    </div>

                    <div class="col-xl-2 col-md-6">
                        <label for="postMinComments" class="form-label fw-semibold">Min comments</label>
                        <input type="number" id="postMinComments" name="min_comments" min="0" value="{{ $minComments }}" class="form-control" data-posts-auto-submit>
                    </div>

                    <div class="col-xl-1 col-md-6 d-flex align-items-end">
                        @if ($hasFilters)
                            <a href="{{ route('admin.reddit-posts.index') }}" class="btn btn-light w-100">Reset</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-heading">
            <div>
                <h2>Collected posts</h2>
                <small>
                    <span id="postsResultCount">{{ number_format($posts->total()) }}</span>
                    {{ \Illuminate\Support\Str::plural('result', $posts->total()) }}
                </small>
            </div>
            <small class="text-muted">Qualified and pending posts</small>
        </div>

        <div class="posts-list-wrap">
            <div id="postsLoading" class="posts-loading d-none" role="status" aria-live="polite">
                <div class="spinner-border text-primary" aria-hidden="true"></div>
                <span class="visually-hidden">Loading posts...</span>
            </div>

            <div id="postsList" data-posts-list aria-live="polite">
                @include('admin.reddit-posts._list', ['posts' => $posts, 'filters' => $filters])
            </div>
        </div>
    </div>
</x-layouts.admin>
