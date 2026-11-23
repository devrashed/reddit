<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>403 · Access denied · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="error-body">
    <main class="error-card">
        <span class="error-code">403</span>
        <h1 class="h3 fw-bold mb-2">Administrator access required</h1>
        <p class="text-muted mb-4">
            Your account does not have permission to open the admin workspace. Contact an administrator if you
            believe this is a mistake.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Sign out
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
            @endauth
        </div>
    </main>
</body>
</html>
