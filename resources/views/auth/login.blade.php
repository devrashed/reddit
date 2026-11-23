<x-layouts.auth title="Sign in">
    <div class="auth-card">
        <a href="{{ url('/') }}" class="auth-brand">
            <span class="brand-mark"><i class="bi bi-radar" aria-hidden="true"></i></span>
            <span>{{ config('app.name') }}</span>
        </a>

        <h1 class="h4 fw-bold mb-1">Welcome back</h1>
        <p class="text-muted small mb-4">Sign in to manage Reddit sources, pain points, and business ideas.</p>

        <x-alert :show-errors="false" />

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label fw-semibold small">Email address</label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                    <input type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="you@example.com"
                           required
                           autofocus
                           autocomplete="username">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <input type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           id="password"
                           name="password"
                           placeholder="Enter your password"
                           required
                           autocomplete="current-password">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-toggle-password
                            data-target="#password"
                            aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" data-submit-once>
                <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Sign in
            </button>
        </form>

        <p class="auth-footer-note">Admin access is restricted. Contact the project owner if you need an account.</p>
    </div>
</x-layouts.auth>
