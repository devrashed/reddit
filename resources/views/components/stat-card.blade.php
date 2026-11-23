@props(['label', 'value', 'icon', 'tone' => 'primary', 'route' => null])

<div class="col-sm-6 col-xl-3">
    <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-start justify-content-between gap-3">
            <div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ number_format($value) }}</div>

                @if ($route)
                    <a href="{{ route($route) }}" class="stat-link">
                        View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            <span class="stat-icon text-{{ $tone }}">
                <i class="bi {{ $icon }}" aria-hidden="true"></i>
            </span>
        </div>
    </div>
</div>
