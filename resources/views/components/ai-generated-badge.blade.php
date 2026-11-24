@props(['isAiGenerated' => true])

@if ($isAiGenerated)
    <span class="badge rounded-pill badge-soft-info" title="Generated from AI analysis; validate independently">
        <i class="bi bi-stars me-1" aria-hidden="true"></i>AI-generated
    </span>
@else
    <span class="badge rounded-pill badge-soft-secondary">Manual</span>
@endif
