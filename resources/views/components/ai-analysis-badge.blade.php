@props(['status'])

@php
    $aiStatus = \App\Enums\AiAnalysisStatus::fromDatabase($status);
@endphp

<span class="badge rounded-pill {{ $aiStatus->badgeClass() }}"
      title="AI analysis status: {{ $aiStatus->label() }}">
    AI: {{ $aiStatus->label() }}
</span>
