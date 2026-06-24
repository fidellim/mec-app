@extends('layouts.app')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-6 col-lg-5">
        <div class="content-card p-4 shadow-sm">
            <div class="text-center mb-4">
                <img class="login-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
            </div>
            <h1 class="h4 mb-1">Create new password</h1>
            <p class="text-muted mb-4">Choose a new password for your MEC Group Portal account.</p>
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <div class="input-group">
                        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" minlength="10" maxlength="64" required autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Show new password">Show</button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Confirm new password</label>
                    <div class="input-group">
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation">Show</button>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary">Reset password</button>
                    <a class="btn btn-outline-secondary" href="{{ route('login') }}">Back to login</a>
                </div>
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
