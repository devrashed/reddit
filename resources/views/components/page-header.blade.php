@props(['title', 'subtitle' => null])

<div class="page-heading">
    <div>
        <h2 class="page-title">{{ $title }}</h2>

        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
