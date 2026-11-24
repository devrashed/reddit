@php
    $source = $painPoint->sourcePost;
    $redditUrl = $source?->post_url ?: ($source?->permalink ? 'https://www.reddit.com'.$source->permalink : null);
@endphp

<x-layouts.admin :title="$painPoint->title">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Pain Points', 'url' => route('admin.pain-points.index')],
        ['label' => \Illuminate\Support\Str::limit($painPoint->title, 60)],
    ]" />

    <x-page-header :title="$painPoint->title" subtitle="AI-generated finding linked to its Reddit source and saved analysis.">
        <x-slot:actions>
            <x-ai-generated-badge :is-ai-generated="$painPoint->is_ai_generated" />

            @if ($source)
                <a href="{{ route('admin.reddit-posts.show', $source) }}" class="btn btn-sm btn-outline-secondary">Source post</a>
            @endif

            @if ($painPoint->aiAnalysis)
                <a href="{{ route('admin.reddit-posts.analysis', ['redditPost' => $painPoint->aiAnalysis->post_id, 'version' => $painPoint->aiAnalysis->version]) }}"
                   class="btn btn-sm btn-outline-primary">
                    View analysis
                </a>
            @endif

            <a href="{{ route('admin.pain-points.index') }}" class="btn btn-sm btn-light">All pain points</a>
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info admin-alert d-flex gap-2" role="alert">
        <i class="bi bi-stars" aria-hidden="true"></i>
        <div>
            This pain point was extracted from an AI analysis version. It is an interpretation of Reddit
            evidence, not independently validated market research.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Finding details</h2>
                </div>
                <div class="card-body">
                    <div class="analysis-section">
                        <h3 class="h6 text-uppercase text-muted">Description</h3>
                        <p class="mb-0">{{ $painPoint->description ?: 'Not provided.' }}</p>
                    </div>

                    <div class="analysis-section">
                        <h3 class="h6 text-uppercase text-muted">Root cause</h3>
                        <p class="mb-0">{{ $painPoint->root_cause ?: 'Not provided.' }}</p>
                    </div>

                    <div class="analysis-section">
                        <h3 class="h6 text-uppercase text-muted">Target audience</h3>
                        <p class="mb-0">{{ $painPoint->target_audience ?: 'Not provided.' }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-heading">
                    <div>
                        <h2>Evidence and interpretation</h2>
                        <small>Supported facts, AI interpretation, and unproven hypotheses</small>
                    </div>
                </div>
                <div class="card-body">
                    <x-evidence-panel :evidence="$painPoint->evidence ?? []" />
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Traceability</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-5">Severity</dt>
                        <dd class="col-7 text-end text-capitalize">{{ $painPoint->severity ?? 'Not assessed' }}</dd>

                        <dt class="col-5">Subreddit</dt>
                        <dd class="col-7 text-end">{{ $source?->subreddit?->name ? 'r/'.$source->subreddit->name : 'Unavailable' }}</dd>

                        <dt class="col-5">Analysis</dt>
                        <dd class="col-7 text-end">{{ $painPoint->aiAnalysis ? 'Version '.$painPoint->aiAnalysis->version : 'Unlinked' }}</dd>

                        <dt class="col-5">Created</dt>
                        <dd class="col-7 text-end">{{ $painPoint->created_at?->format('d M Y, H:i') }}</dd>
                    </dl>

                    @if ($source)
                        <div class="mt-3">
                            <div class="stat-label">Source post</div>
                            <a href="{{ route('admin.reddit-posts.show', $source) }}" class="fw-semibold">
                                {{ $source->title }}
                            </a>
                        </div>

                        <a href="{{ route('admin.reddit-posts.show', $source) }}" class="btn btn-outline-secondary w-100 mt-3">
                            Open source post
                        </a>
                    @endif

                    @if ($redditUrl)
                        <a href="{{ $redditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Original Reddit discussion
                        </a>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-heading">
                    <h2>Related business ideas</h2>
                </div>

                @if ($painPoint->businessIdeas->isEmpty())
                    <div class="empty-state py-4">
                        <i class="bi bi-lightbulb" aria-hidden="true"></i>
                        No business ideas are linked to this pain point.
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($painPoint->businessIdeas as $idea)
                            <a href="{{ route('admin.business-ideas.show', $idea) }}" class="list-group-item list-group-item-action">
                                <div class="fw-semibold">{{ $idea->title }}</div>
                                <div class="small text-muted">
                                    {{ $idea->product_type ?: 'Product type not specified' }} · {{ ucfirst($idea->status) }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
