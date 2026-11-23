@props(['dismissible' => true, 'showErrors' => true])

@php
    $icons = [
        'success' => 'bi-check-circle-fill',
        'danger' => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info' => 'bi-info-circle-fill',
    ];

    $alerts = [];

    foreach (['success', 'danger', 'warning', 'info'] as $type) {
        if (session()->has($type)) {
            $alerts[] = ['type' => $type, 'message' => session($type)];
        }
    }

    if (session()->has('status')) {
        $alerts[] = ['type' => 'info', 'message' => session('status')];
    }
@endphp

@foreach ($alerts as $alert)
    <div class="alert alert-{{ $alert['type'] }} alert-dismissible fade show admin-alert"
         role="alert"
         data-auto-dismiss>
        <div class="d-flex gap-2">
            <i class="bi {{ $icons[$alert['type']] }}" aria-hidden="true"></i>
            <div>{{ $alert['message'] }}</div>
        </div>

        @if ($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endif
    </div>
@endforeach

@if ($showErrors && isset($errors) && $errors->any())
    <div class="alert alert-danger admin-alert" role="alert">
        <div class="d-flex gap-2">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <div>
                <strong>Please review the highlighted fields.</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
