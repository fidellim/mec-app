@extends('layouts.app')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-5 col-lg-4">
        <div class="content-card p-4 shadow-sm">
            <div class="text-center mb-4">
                <img class="login-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
            </div>
            <h1 class="h4 mb-1">Sign in</h1>
            <form method="post" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input class="form-control" id="password" name="password" type="password" required>
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a class="small fw-semibold text-decoration-none" href="{{ route('password.request') }}">Forgot password?</a>
                </div>
                <button class="btn btn-primary w-100">Login</button>
            </form>
        </div>
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
</script>
@endpush
