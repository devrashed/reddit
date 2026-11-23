@props(['title', 'description', 'icon' => 'bi-tools', 'phase' => 'Phase 2'])

<div class="card">
    <div class="card-body placeholder-state">
        <span class="placeholder-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>

        <span class="badge rounded-pill badge-soft border mb-3">Scaffolded &middot; {{ $phase }}</span>

        <h2 class="h4 mb-2">{{ $title }}</h2>
        <p class="text-muted mx-auto mb-0" style="max-width: 34rem;">{{ $description }}</p>
    </div>
</div>
