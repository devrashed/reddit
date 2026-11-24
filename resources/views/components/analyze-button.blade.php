@props(['post', 'status', 'completedVersion' => null])

@php
    $aiStatus = \App\Enums\AiAnalysisStatus::fromDatabase($status);
    $hasCompleted = $completedVersion !== null;
@endphp

<div class="d-inline-flex flex-wrap justify-content-end align-items-center gap-1">
    @if ($hasCompleted)
        <a href="{{ route('admin.reddit-posts.analysis', ['redditPost' => $post, 'version' => $completedVersion]) }}"
           class="btn btn-sm btn-outline-primary"
           title="View saved analysis">
            <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>
            View Analysis
        </a>
    @endif

    @if ($aiStatus->isProcessing())
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
            Analysis in progress
        </button>
    @elseif ($hasCompleted)
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                data-reanalyze-post
                data-url="{{ route('admin.reddit-posts.reanalyze', $post) }}"
                data-post-title="{{ $post->title }}"
                title="Re-analyze this post">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
            Re-analyze
        </button>
    @else
        <button type="button"
                class="btn btn-sm btn-primary"
                data-analyze-post
                data-url="{{ route('admin.reddit-posts.analyze', $post) }}"
                title="{{ $aiStatus === \App\Enums\AiAnalysisStatus::Failed ? 'Retry analysis' : 'Analyze this post' }}: {{ $post->title }}">
            <i class="bi {{ $aiStatus === \App\Enums\AiAnalysisStatus::Failed ? 'bi-arrow-clockwise' : 'bi-cpu' }} me-1" aria-hidden="true"></i>
            {{ $aiStatus === \App\Enums\AiAnalysisStatus::Failed ? 'Retry analysis' : 'Analyze' }}
        </button>
    @endif
</div>
