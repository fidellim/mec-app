@extends('layouts.app')

@section('content')
<div class="row justify-content-center align-items-center py-5" style="min-height: 70vh;">
    <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="content-card p-4 p-md-5 shadow-sm text-center">
            <div class="display-5 fw-bold mb-2">419</div>
            <h1 class="h4 mb-2">Your session has expired</h1>
            <p class="text-muted mb-4">
                Please return home and sign in again to continue working securely.
            </p>
            <a class="btn btn-primary" href="{{ route('dashboard') }}">Back to Home</a>
        </div>
    </div>
</div>
@endsection
