@php
    $source = $businessIdea->sourcePost;
    $redditUrl = $source?->post_url ?: ($source?->permalink ? 'https://www.reddit.com'.$source->permalink : null);
    $mvpFeatures = is_array($businessIdea->mvp_features) ? $businessIdea->mvp_features : [];
    $monetizationIdeas = is_array($businessIdea->monetization_ideas) ? $businessIdea->monetization_ideas : [];
    $risks = is_array($businessIdea->risks) ? $businessIdea->risks : [];
@endphp

<x-layouts.admin :title="$businessIdea->title">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Business Ideas', 'url' => route('admin.business-ideas.index')],
        ['label' => \Illuminate\Support\Str::limit($businessIdea->title, 60)],
    ]" />

    <x-page-header :title="$businessIdea->title" subtitle="AI-generated opportunity linked to evidence and source discussion.">
        <x-slot:actions>
            <x-ai-generated-badge :is-ai-generated="$businessIdea->is_ai_generated" />

            @if ($businessIdea->painPoint)
                <a href="{{ route('admin.pain-points.show', $businessIdea->painPoint) }}" class="btn btn-sm btn-outline-secondary">Pain point</a>
            @endif

            @if ($businessIdea->aiAnalysis)
                <a href="{{ route('admin.reddit-posts.analysis', ['redditPost' => $businessIdea->aiAnalysis->post_id, 'version' => $businessIdea->aiAnalysis->version]) }}"
                   class="btn btn-sm btn-outline-primary">
                    View analysis
                </a>
            @endif

            <a href="{{ route('admin.business-ideas.index') }}" class="btn btn-sm btn-light">All ideas</a>
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info admin-alert d-flex gap-2" role="alert">
        <i class="bi bi-lightbulb" aria-hidden="true"></i>
        <div>
            This opportunity is an AI-generated hypothesis. It has not been validated as a business, and
            no revenue, demand, or willingness-to-pay claim is implied.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Opportunity details</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="stat-label">Product type</div>
                            <div>{{ $businessIdea->product_type ?: 'Not specified' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-label">Target market</div>
                            <div>{{ $businessIdea->target_market ?: 'Not specified' }}</div>
                        </div>
                    </div>

                    <div class="analysis-section">
                        <h3 class="h6 text-uppercase text-muted">Description</h3>
                        <p class="mb-0">{{ $businessIdea->description ?: 'Not provided.' }}</p>
                    </div>

                    <div class="analysis-section">
                        <h3 class="h6 text-uppercase text-muted">Proposed solution</h3>
                        <p class="mb-0">{{ $businessIdea->proposed_solution ?: 'Not provided.' }}</p>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-heading">
                    <h2>MVP, monetization, and risks</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="evidence-box h-100">
                                <h3 class="h6">Suggested MVP features</h3>
                                @if ($mvpFeatures === [])
                                    <p class="small text-muted mb-0">Not provided.</p>
                                @else
                                    <ul class="small mb-0">
                                        @foreach ($mvpFeatures as $feature)
                                            <li>{{ $feature }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="evidence-box h-100">
                                <h3 class="h6">Monetization ideas</h3>
                                @if ($monetizationIdeas === [])
                                    <p class="small text-muted mb-0">Not provided.</p>
                                @else
                                    <ul class="small mb-0">
                                        @foreach ($monetizationIdeas as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="evidence-box h-100">
                                <h3 class="h6">Risks and limitations</h3>
                                @if ($risks === [])
                                    <p class="small text-muted mb-0">Not provided.</p>
                                @else
                                    <ul class="small mb-0">
                                        @foreach ($risks as $risk)
                                            <li>{{ $risk }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-heading">
                    <div>
                        <h2>Evidence and interpretation</h2>
                        <small>Direct evidence versus AI-generated hypotheses</small>
                    </div>
                </div>
                <div class="card-body">
                    <x-evidence-panel :evidence="$businessIdea->evidence ?? []" />
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Source tracking</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-5">Status</dt>
                        <dd class="col-7 text-end text-capitalize">{{ $businessIdea->status }}</dd>

                        <dt class="col-5">Subreddit</dt>
                        <dd class="col-7 text-end">{{ $source?->subreddit?->name ? 'r/'.$source->subreddit->name : 'Unavailable' }}</dd>

                        <dt class="col-5">Analysis</dt>
                        <dd class="col-7 text-end">{{ $businessIdea->aiAnalysis ? 'Version '.$businessIdea->aiAnalysis->version : 'Unlinked' }}</dd>

                        <dt class="col-5">Created</dt>
                        <dd class="col-7 text-end">{{ $businessIdea->created_at?->format('d M Y, H:i') }}</dd>
                    </dl>

                    @if ($source)
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
                    <h2>Related pain point</h2>
                </div>

                @if ($businessIdea->painPoint)
                    <div class="card-body">
                        <a href="{{ route('admin.pain-points.show', $businessIdea->painPoint) }}" class="fw-semibold d-block mb-2">
                            {{ $businessIdea->painPoint->title }}
                        </a>
                        <p class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($businessIdea->painPoint->root_cause ?: 'Root cause not provided.', 180) }}</p>
                        <div class="small text-muted">Severity: {{ $businessIdea->painPoint->severity ?? 'Not assessed' }}</div>
                    </div>
                @else
                    <div class="empty-state py-4">
                        <i class="bi bi-bandaid" aria-hidden="true"></i>
                        No pain point is linked to this idea.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
