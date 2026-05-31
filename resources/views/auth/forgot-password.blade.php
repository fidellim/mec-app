@extends('layouts.app')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-6 col-lg-5">
        <div class="content-card p-4 shadow-sm">
            <div class="text-center mb-4">
                <img class="login-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
            </div>
            <h1 class="h4 mb-1">Reset password</h1>
            <p class="text-muted mb-4">Enter your account email and we will send a password reset link that expires in 1 hour.</p>
            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary">Email reset link</button>
                    <a class="btn btn-outline-secondary" href="{{ route('login') }}">Back to login</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
