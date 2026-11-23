@php
    $user = auth()->user();
    $initials = strtoupper(mb_substr($user?->name ?? 'A', 0, 1));
@endphp

<aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="brand-link" id="adminSidebarLabel">
            <span class="brand-mark"><i class="bi bi-radar" aria-hidden="true"></i></span>
            <span class="brand-text">
                <strong>Reddit Lab</strong>
                <small>Business Intelligence</small>
            </span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close navigation"></button>
    </div>

    <div class="offcanvas-body">
        <nav class="sidebar-nav" aria-label="Admin navigation">
            @foreach (config('navigation.sidebar') as $section)
                @if ($section['label'])
                    <div class="sidebar-heading">{{ $section['label'] }}</div>
                @endif

                @foreach ($section['items'] as $item)
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-link {{ request()->routeIs($item['active']) ? 'active' : '' }}"
                       @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar">{{ $initials }}</span>
                <div class="min-w-0">
                    <div class="sidebar-user-name text-truncate">{{ $user?->name }}</div>
                    <div class="sidebar-user-meta text-truncate">{{ $user?->email }}</div>
                </div>
            </div>
        </div>
    </div>
</aside>
