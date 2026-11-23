@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    @stack('styles')
</head>
<body class="admin-body">
    <a class="visually-hidden-focusable skip-link" href="#mainContent">Skip to content</a>

    <x-admin.sidebar />

    <div class="admin-main">
        <x-admin.header :title="$title ?? 'Overview'" />

        <main class="admin-content" id="mainContent">
            <x-alert />

            {{ $slot }}
        </main>

        <x-admin.footer />
    </div>

    <x-confirm-modal />
    <x-loading />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/admin.js') }}"></script>

    @stack('scripts')
</body>
</html>
