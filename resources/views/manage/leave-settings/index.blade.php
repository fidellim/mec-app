@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Settings</h1>
        <div class="text-muted">Manage leave policy allowances by region.</div>
    </div>
</div>

<form class="content-card p-3" method="post" action="{{ route('manage.leave-settings.update') }}">
    @csrf
    @method('patch')
    <div class="row g-3">
        @foreach($settingDefinitions as $key => $definition)
            <div class="col-md-6">
                <label class="form-label" for="{{ $key }}">{{ $definition['name'] }}</label>
                <input class="form-control @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="0.5" value="{{ old($key, $settings[$key]->decimal_value) }}" required>
                <div class="form-text">{{ $definition['description'] }}</div>
                @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="col-12">
            <div class="alert alert-info mb-0">
                Annual and sick leave balances are shown to users. Maternity, parental, and bereavement / compassionate leave are enforced at submission, but remaining balances are hidden because eligibility is reviewed manually.
            </div>
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary">Save Settings</button>
    </div>
</form>
@endsection
