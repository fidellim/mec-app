@extends('layouts.app')

@section('handles-validation-errors', '1')

@section('content')
<style>
    .login-page {
        display: grid;
        place-items: center;
        min-height: 80svh;
    }
    .login-page .login-card {
        width: 100%;
        max-width: 28rem;
        padding: clamp(1.25rem, 4vw, 2rem);
    }
    .login-page .login-logo { max-width: 100%; }
    .login-page .form-control,
    .login-page .btn { min-height: 2.75rem; }
    .login-page .form-control:not(.is-invalid),
    .login-page .form-check-input:not(:checked) {
        border-color: color-mix(in srgb, var(--bs-secondary-color) 80%, var(--app-card-bg));
    }
    .login-page .btn-outline-secondary {
        --bs-btn-color: var(--bs-secondary-color);
        --bs-btn-border-color: var(--bs-secondary-color);
        --bs-btn-hover-color: var(--bs-body-color);
        --bs-btn-hover-bg: var(--bs-secondary-bg);
        --bs-btn-hover-border-color: var(--bs-secondary-color);
        --bs-btn-active-color: var(--bs-body-color);
        --bs-btn-active-bg: var(--bs-secondary-bg);
        --bs-btn-active-border-color: var(--bs-secondary-color);
    }
    .login-page .input-group > .form-control {
        border-start-end-radius: 0;
        border-end-end-radius: 0;
    }
    .login-page .input-group > button {
        border-start-start-radius: 0;
        border-end-start-radius: 0;
    }
    .login-page :is(input, button, a):focus-visible {
        outline: 2px solid var(--bs-primary-text-emphasis);
        outline-offset: 3px;
    }
    .login-page .login-options {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: .25rem 1rem;
    }
    .login-page .login-remember,
    .login-page .login-recovery {
        display: inline-flex;
        align-items: center;
        min-height: 2.75rem;
    }
    .login-page .login-remember { gap: .5rem; cursor: pointer; }
    .login-page .login-remember input { margin: 0; flex-shrink: 0; }
    .login-page .login-recovery {
        color: var(--bs-primary-text-emphasis);
        text-underline-offset: .2em;
    }
    .login-page .alert { overflow-wrap: anywhere; }
</style>
<div class="login-page">
    <div class="content-card login-card">
        <div class="text-center mb-4">
            <img class="login-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
        </div>
        <h1 class="h3 page-heading mb-3">Sign in</h1>
        @if(session('status'))
            <div class="alert alert-info" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" id="login-errors" tabindex="-1" role="alert">
                <p class="fw-semibold mb-1">Unable to sign in</p>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="{{ route('login') }}" id="login-form">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email (required)</label>
                <input class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" type="email" value="{{ old('email') }}"
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       required @if(! $errors->any()) autofocus @endif
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password (required)</label>
                <div class="input-group has-validation">
                    <input class="form-control @error('password') is-invalid @enderror"
                           id="password" name="password" type="password" autocomplete="current-password" required
                           @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-controls="password" aria-label="Show password">Show</button>
                    @error('password')
                        <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="login-options mb-3">
                <label class="login-remember" for="remember">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                    <span>Remember me</span>
                </label>
                <a class="login-recovery small fw-semibold" href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn btn-primary w-100" type="submit" id="login-submit">Login</button>
        </form>
        <div class="visually-hidden" role="status" aria-live="polite" id="login-progress"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        button.textContent = isHidden ? 'Hide' : 'Show';
        button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
});

const loginForm = document.getElementById('login-form');
const loginSubmit = document.getElementById('login-submit');
const loginProgress = document.getElementById('login-progress');
loginForm.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    loginSubmit.disabled = true;
    loginSubmit.textContent = 'Signing in…';
    loginForm.setAttribute('aria-busy', 'true');
    loginProgress.textContent = 'Signing in…';
});
window.addEventListener('pageshow', () => {
    loginSubmit.disabled = false;
    loginSubmit.textContent = 'Login';
    loginForm.removeAttribute('aria-busy');
    loginProgress.textContent = '';
});
document.getElementById('login-errors')?.focus();
</script>
@endpush
