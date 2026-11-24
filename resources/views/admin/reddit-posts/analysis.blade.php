@php
    $report = is_array($analysis->analysis_result) ? $analysis->analysis_result : [];
    $sections = \App\Support\AnalysisReportSchema::SECTIONS;
    $classificationSections = \App\Support\AnalysisReportSchema::CLASSIFICATION_SECTIONS;
    $classification = is_array($report['evidence_classification'] ?? null)
        ? $report['evidence_classification']
        : [];
    $redditUrl = $post->post_url
        ?: ($post->permalink ? 'https://www.reddit.com'.$post->permalink : null);
@endphp

<x-layouts.admin :title="'AI Analysis v'.$analysis->version">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Reddit Posts', 'url' => route('admin.reddit-posts.index')],
        ['label' => \Illuminate\Support\Str::limit($post->title, 45), 'url' => route('admin.reddit-posts.show', $post)],
        ['label' => 'Analysis v'.$analysis->version],
    ]" />

    <x-page-header
        :title="'AI Analysis · Version '.$analysis->version"
        :subtitle="$post->title"
    >
        <x-slot:actions>
            <x-ai-analysis-badge :status="$analysis->analysis_status" />

            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-reanalyze-post
                    data-url="{{ route('admin.reddit-posts.reanalyze', $post) }}"
                    data-post-title="{{ $post->title }}">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                Re-analyze
            </button>

            <a href="{{ route('admin.reddit-posts.show', $post) }}" class="btn btn-sm btn-light">
                Back to post
            </a>

            @if ($redditUrl)
                <a href="{{ $redditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Reddit
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info admin-alert d-flex gap-2" role="alert">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            This AI-generated interpretation is based on one Reddit post and its saved comments. It is not
            proof of market demand, revenue potential, or willingness to pay. Validate findings with
            independent research.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-heading">
                    <div>
                        <h2>Business opportunity report</h2>
                        <small>Version {{ $analysis->version }} · {{ $analysis->model }}</small>
                    </div>
                    <x-ai-analysis-badge :status="$analysis->analysis_status" />
                </div>

                <div class="card-body analysis-report">
                    @foreach ($sections as $key => $title)
                        @php
                            $value = $report[$key] ?? null;
                        @endphp

                        <section class="analysis-section">
                            <h3 class="h6 text-uppercase text-muted mb-2">
                                {{ $loop->iteration }}. {{ $title }}
                            </h3>

                            @if (is_array($value))
                                @if ($value === [])
                                    <p class="text-muted mb-0">Not provided by the model.</p>
                                @else
                                    <ul class="mb-0">
                                        @foreach ($value as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @elseif (filled($value))
                                <p class="mb-0">{{ $value }}</p>
                            @else
                                <p class="text-muted mb-0">Not provided by the model.</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-heading">
                    <div>
                        <h2>Evidence classification</h2>
                        <small>What is supported, interpreted, and still unproven</small>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($classificationSections as $key => $title)
                            @php
                                $items = is_array($classification[$key] ?? null)
                                    ? $classification[$key]
                                    : [];
                            @endphp

                            <div class="col-md-4">
                                <div class="classification-box h-100">
                                    <h3 class="h6">{{ $title }}</h3>

                                    @if ($items === [])
                                        <p class="small text-muted mb-0">None identified.</p>
                                    @else
                                        <ul class="small mb-0">
                                            @foreach ($items as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($previousCompleted)
                <div class="card mt-3">
                    <div class="card-heading">
                        <div>
                            <h2>Compare versions</h2>
                            <small>Version {{ $previousCompleted->version }} vs version {{ $analysis->version }}</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Section</th>
                                        <th scope="col">Version {{ $previousCompleted->version }}</th>
                                        <th scope="col">Version {{ $analysis->version }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ([
                                        'problem_identification' => 'Problem Identification',
                                        'main_pain_point' => 'Main Pain Point',
                                        'business_opportunity' => 'Business Opportunity',
                                        'target_audience' => 'Target Audience',
                                    ] as $compareKey => $compareLabel)
                                        <tr>
                                            <td class="small fw-semibold">{{ $compareLabel }}</td>
                                            <td class="small text-muted">
                                                {{ \Illuminate\Support\Str::limit((string) data_get($previousCompleted->analysis_result, $compareKey, ''), 400) ?: '—' }}
                                            </td>
                                            <td class="small">
                                                {{ \Illuminate\Support\Str::limit((string) data_get($report, $compareKey, ''), 400) ?: '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Analysis metadata</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-6">Version</dt>
                        <dd class="col-6 text-end">{{ $analysis->version }}</dd>

                        <dt class="col-6">Provider</dt>
                        <dd class="col-6 text-end">{{ $analysis->provider }}</dd>

                        <dt class="col-6">Model</dt>
                        <dd class="col-6 text-end text-break">{{ $analysis->model }}</dd>

                        <dt class="col-6">Prompt tokens</dt>
                        <dd class="col-6 text-end">{{ $analysis->prompt_tokens === null ? 'Not provided' : number_format($analysis->prompt_tokens) }}</dd>

                        <dt class="col-6">Completion tokens</dt>
                        <dd class="col-6 text-end">{{ $analysis->completion_tokens === null ? 'Not provided' : number_format($analysis->completion_tokens) }}</dd>

                        <dt class="col-6">Total tokens</dt>
                        <dd class="col-6 text-end">{{ $analysis->total_tokens === null ? 'Not provided' : number_format($analysis->total_tokens) }}</dd>

                        <dt class="col-6">Analyzed at</dt>
                        <dd class="col-6 text-end">{{ $analysis->analyzed_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-heading">
                    <h2>Source post</h2>
                </div>
                <div class="card-body">
                    <dl class="system-list row mb-0">
                        <dt class="col-5">Subreddit</dt>
                        <dd class="col-7 text-end">r/{{ $post->subreddit?->name }}</dd>

                        <dt class="col-5">Author</dt>
                        <dd class="col-7 text-end">u/{{ $post->author ?: '[deleted]' }}</dd>

                        <dt class="col-5">Score</dt>
                        <dd class="col-7 text-end">{{ number_format($post->score) }}</dd>

                        <dt class="col-5">Reddit comments</dt>
                        <dd class="col-7 text-end">{{ number_format($post->num_comments) }}</dd>

                        <dt class="col-5">Published</dt>
                        <dd class="col-7 text-end">{{ $post->reddit_created_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    </dl>

                    <a href="{{ route('admin.reddit-posts.show', $post) }}" class="btn btn-outline-secondary w-100 mt-3">
                        Open post and comments
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-heading">
                    <h2>Version history</h2>
                </div>
                <div class="card-body">
                    <div class="version-list">
                        @foreach ($versions as $version)
                            @php
                                $versionStatus = \App\Enums\AiAnalysisStatus::fromDatabase($version->analysis_status);
                            @endphp

                            <div class="version-item {{ $version->version === $analysis->version ? 'is-current' : '' }}">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        @if ($version->analysis_status === 'completed')
                                            <a href="{{ route('admin.reddit-posts.analysis', ['redditPost' => $post, 'version' => $version->version]) }}" class="fw-semibold">
                                                Version {{ $version->version }}
                                            </a>
                                        @else
                                            <span class="fw-semibold">Version {{ $version->version }}</span>
                                        @endif
                                    </div>

                                    <span class="badge rounded-pill {{ $versionStatus->badgeClass() }}">
                                        {{ $versionStatus->label() }}
                                    </span>
                                </div>

                                <div class="small text-muted mt-1">
                                    {{ $version->analyzed_at?->format('d M Y, H:i') ?? $version->created_at?->format('d M Y, H:i') }}
                                    @if ($version->total_tokens !== null)
                                        &middot; {{ number_format($version->total_tokens) }} tokens
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
