@props(['title' => 'Overview'])

@php
    $user = auth()->user();
    $initials = strtoupper(mb_substr($user?->name ?? 'A', 0, 1));
@endphp

<header class="admin-header">
    <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
        <button class="btn btn-light sidebar-toggle d-lg-none"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#adminSidebar"
                aria-controls="adminSidebar"
                aria-label="Toggle navigation">
            <i class="bi bi-list fs-5" aria-hidden="true"></i>
        </button>

        <div class="min-w-0">
            <div class="text-muted small d-none d-sm-block">{{ now()->format('l, d M Y') }}</div>
            <h1 class="header-title text-truncate">{{ $title }}</h1>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        <div class="system-status d-none d-md-flex align-items-center gap-2"
             id="systemStatus"
             data-status-url="{{ route('admin.system.status') }}"
             title="Checking system status...">
            <span class="status-dot" aria-hidden="true"></span>
            <span class="status-label">Checking...</span>
        </div>

        <div class="dropdown">
            <button class="btn btn-light user-menu dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                <span class="avatar avatar-sm">{{ $initials }}</span>
                <span class="d-none d-md-inline text-truncate">{{ $user?->name }}</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header text-truncate">{{ $user?->email }}</h6></li>
                <li>
                    <a class="dropdown-item" href="{{ route('admin.settings.index') }}">
                        <i class="bi bi-gear me-2" aria-hidden="true"></i>Settings
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <button type="submit"
                                class="dropdown-item text-danger"
                                data-confirm="Sign out of your admin session?"
                                data-confirm-form="#logout-form"
                                data-confirm-button="Sign out">
                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
