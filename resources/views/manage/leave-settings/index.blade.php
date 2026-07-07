@extends('layouts.app')

@section('content')
@php
    $regionalGroups = [
        'uae' => [
            'label' => 'UAE leave rules',
            'badge' => 'UAE employees',
            'description' => 'Calendar-day controls for UAE statutory allowances and employee-facing leave balances.',
            'accent' => 'primary',
            'settings' => [
                \App\Models\LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE,
                \App\Models\LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE,
                \App\Models\LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE,
                \App\Models\LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE,
                \App\Models\LeaveSetting::BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE,
                \App\Models\LeaveSetting::BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE,
            ],
        ],
        'philippines' => [
            'label' => 'Philippines leave rules',
            'badge' => 'Philippines employees',
            'description' => 'Policy defaults for active Philippines statutory leave entitlements.',
            'accent' => 'success',
            'settings' => [
                \App\Models\LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_PH,
                \App\Models\LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_PH,
                \App\Models\LeaveSetting::PATERNITY_LEAVE_DEFAULT_DAYS_PH,
                \App\Models\LeaveSetting::VAWC_LEAVE_DEFAULT_DAYS_PH,
                \App\Models\LeaveSetting::SPECIAL_WOMEN_LEAVE_DEFAULT_DAYS_PH,
                \App\Models\LeaveSetting::SERVICE_INCENTIVE_LEAVE_DEFAULT_DAYS_PH,
            ],
        ],
    ];
@endphp

<style>
    .leave-settings-shell {
        display: grid;
        gap: 1rem;
    }

    .leave-settings-form {
        overflow: hidden;
    }

    .leave-settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .leave-region-panel {
        --region-accent: var(--bs-primary);
        --region-accent-subtle: var(--bs-primary-bg-subtle);
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        border: 1px solid color-mix(in srgb, var(--region-accent) 24%, var(--app-border));
        border-radius: .75rem;
        background:
            linear-gradient(180deg, color-mix(in srgb, var(--region-accent-subtle) 42%, transparent), transparent 14rem),
            var(--app-card-bg);
        box-shadow: var(--app-shadow-sm);
        overflow: hidden;
    }

    .leave-region-panel[data-region-accent="success"] {
        --region-accent: var(--bs-success);
        --region-accent-subtle: var(--bs-success-bg-subtle);
    }

    .leave-region-panel::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: .3rem;
        background: linear-gradient(180deg, var(--region-accent), color-mix(in srgb, var(--region-accent) 42%, transparent));
    }

    .leave-region-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.15rem .9rem 1.35rem;
        border-bottom: 1px solid color-mix(in srgb, var(--region-accent) 18%, var(--app-soft-border));
    }

    .leave-region-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .leave-region-description {
        max-width: 34rem;
        margin-top: .28rem;
        color: var(--bs-secondary-color);
        font-size: .9rem;
    }

    .leave-region-badge {
        flex: 0 0 auto;
        max-width: 12rem;
        border: 1px solid color-mix(in srgb, var(--region-accent) 26%, var(--app-border));
        border-radius: 999px;
        padding: .34rem .65rem;
        background: color-mix(in srgb, var(--region-accent-subtle) 54%, var(--app-card-bg));
        color: color-mix(in srgb, var(--region-accent) 72%, var(--bs-body-color));
        font-size: .75rem;
        font-weight: 800;
        line-height: 1.15;
        text-align: center;
    }

    .leave-region-fields {
        display: grid;
        gap: .85rem;
        padding: 1rem 1.15rem 1.15rem 1.35rem;
    }

    .leave-setting-field {
        display: grid;
        gap: .45rem;
        min-width: 0;
        padding: .9rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .7rem;
        background: color-mix(in srgb, var(--app-card-bg) 84%, var(--app-muted-bg));
    }

    .leave-setting-field .form-label {
        margin-bottom: 0;
        font-weight: 750;
        line-height: 1.25;
    }

    .leave-setting-control {
        display: grid;
        grid-template-columns: minmax(7.5rem, 10rem) minmax(0, 1fr);
        gap: .85rem;
        align-items: start;
    }

    .leave-setting-control .form-control {
        font-weight: 700;
    }

    .leave-setting-unit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: calc(1.5em + .75rem + calc(var(--bs-border-width) * 2));
        border: 1px solid var(--app-border);
        border-radius: .55rem;
        background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
        color: var(--bs-secondary-color);
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .leave-setting-field .form-text {
        margin-top: 0;
        color: var(--bs-secondary-color);
    }

    .leave-settings-guidance {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: center;
        margin-top: 1rem;
        padding: 1rem 1.15rem;
        border: 1px solid color-mix(in srgb, var(--bs-info) 26%, var(--app-border));
        border-radius: .75rem;
        background:
            linear-gradient(90deg, color-mix(in srgb, var(--bs-info-bg-subtle) 58%, transparent), transparent 70%),
            color-mix(in srgb, var(--app-card-bg) 88%, var(--app-muted-bg));
    }

    .leave-settings-guidance-title {
        margin-bottom: .25rem;
        font-weight: 800;
    }

    .leave-settings-guidance .alert {
        border: 0;
        border-radius: 0;
        padding: 0;
        background: transparent;
        box-shadow: none;
        color: var(--bs-secondary-color);
    }

    .leave-settings-count {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 5.5rem;
        min-height: 5.5rem;
        border: 1px solid color-mix(in srgb, var(--bs-info) 24%, var(--app-border));
        border-radius: .75rem;
        background: color-mix(in srgb, var(--app-card-bg) 76%, var(--bs-info-bg-subtle));
        text-align: center;
    }

    .leave-settings-count strong {
        font-size: 1.65rem;
        line-height: 1;
    }

    .leave-settings-count span {
        margin-top: .25rem;
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    @media (max-width: 1199.98px) {
        .leave-settings-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .leave-region-header,
        .leave-settings-guidance {
            grid-template-columns: 1fr;
        }

        .leave-region-header {
            flex-direction: column;
        }

        .leave-region-badge {
            max-width: 100%;
            text-align: left;
        }

        .leave-setting-control {
            grid-template-columns: 1fr;
            gap: .5rem;
        }

        .leave-setting-unit {
            justify-content: flex-start;
            min-height: 2.25rem;
            padding-inline: .7rem;
        }

        .leave-settings-count {
            width: 100%;
            min-height: auto;
            padding: .75rem;
        }
    }
</style>

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Settings</h1>
        <div class="text-muted">Manage leave policy allowances and maximum claimable calendar-day limits by region.</div>
    </div>
</div>

<form class="content-card leave-settings-form" method="post" action="{{ route('manage.leave-settings.update') }}">
    @csrf
    @method('patch')
    <div class="content-card-body leave-settings-shell">
        <div class="leave-settings-grid">
            @foreach($regionalGroups as $region => $group)
                <section class="leave-region-panel" data-region="{{ $region }}" data-region-accent="{{ $group['accent'] }}" aria-labelledby="leave-region-{{ $region }}">
                    <div class="leave-region-header">
                        <div>
                            <h2 class="leave-region-title" id="leave-region-{{ $region }}">{{ $group['label'] }}</h2>
                            <div class="leave-region-description">{{ $group['description'] }}</div>
                        </div>
                        <div class="leave-region-badge">{{ $group['badge'] }}</div>
                    </div>

                    <div class="leave-region-fields">
                        @foreach($group['settings'] as $key)
                            @php($definition = $settingDefinitions[$key])
                            <div class="leave-setting-field">
                                <label class="form-label" for="{{ $key }}">{{ $definition['name'] }}</label>
                                <div class="leave-setting-control">
                                    <div>
                                        <input class="form-control @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="0.5" value="{{ old($key, $settings[$key]->decimal_value) }}" required>
                                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="leave-setting-unit">Days</div>
                                </div>
                                <div class="form-text">{{ $definition['description'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="leave-settings-guidance">
            <div>
                <div class="leave-settings-guidance-title">How these rules are applied</div>
                <div class="alert alert-info mb-0">
                    Eligible entitlement balances are shown to users. UAE sick and maternity balances show the full-pay allowance, while these settings keep the maximum claimable calendar-day limits for validation. UAE bereavement spouse and immediate-family allowances are tracked separately by calendar year after HR eligibility approval on the user profile. Maternity leave appears only when gender is Female, and parental leave appears only after HR eligibility approval.
                </div>
            </div>
            <div class="leave-settings-count" aria-label="{{ collect($regionalGroups)->sum(fn ($group) => count($group['settings'])) }} configured leave rules">
                <strong>{{ collect($regionalGroups)->sum(fn ($group) => count($group['settings'])) }}</strong>
                <span>Rules</span>
            </div>
        </div>
    </div>

    <div class="sticky-actions d-flex justify-content-end p-3">
        <button class="btn btn-primary">Save Settings</button>
    </div>
</form>
@endsection
