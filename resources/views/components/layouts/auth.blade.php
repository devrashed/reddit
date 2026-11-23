@props(['title' => 'Sign in'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title.' · '.config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    @stack('styles')
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-panel">
            <div>
                <span class="brand-mark mb-4"><i class="bi bi-radar" aria-hidden="true"></i></span>
                <h1>Turn Reddit conversations into product opportunities.</h1>
                <p class="mt-3">
                    Collect real discussions, surface recurring pain points, and rank business ideas backed by
                    evidence instead of guesswork.
                </p>

                <ul class="auth-feature-list">
                    <li><i class="bi bi-collection" aria-hidden="true"></i>Organized subreddit sources</li>
                    <li><i class="bi bi-chat-square-text" aria-hidden="true"></i>Pain point extraction from real posts</li>
                    <li><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>Scored, prioritized business ideas</li>
                </ul>
            </div>

            <p class="small mb-0">Built with Laravel, MySQL, Bootstrap 5 and jQuery.</p>
        </section>

        <section class="auth-form-panel">
            {{ $slot }}
        </section>
    </main>

    <x-loading />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/admin.js') }}"></script>

    @stack('scripts')
</body>
</html>
