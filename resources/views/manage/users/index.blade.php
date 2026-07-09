@extends('layouts.app')

@section('content')
@php($roleLabels = config('roles.labels'))
@php($genderLabels = ['male' => 'Male', 'female' => 'Female'])
@php($maritalStatusLabels = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated'])
@php($selectedDepartment = filled($selectedDepartmentId) && $selectedDepartmentId !== 'unassigned' ? $departments->firstWhere('id', (int) $selectedDepartmentId) : null)
@php($selectedRoleLabel = filled($selectedRole) ? ($roleLabels[$selectedRole] ?? $selectedRole) : null)
@php($selectedRegionLabel = filled($selectedRegion) ? ($regionLabels[$selectedRegion] ?? $selectedRegion) : null)
@php($hasUserFilters = filled($selectedDepartmentId) || filled($selectedRole) || filled($selectedRegion) || filled($selectedSearchNames))
<style>
    .users-filter-card {
        padding: 1rem;
    }

    .users-filter-card .form-label {
        color: var(--bs-secondary-color);
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .users-filter-card .ts-wrapper.single .ts-control {
        align-items: center;
        flex-wrap: nowrap;
        height: calc(2.25rem + 2px);
        min-height: calc(2.25rem + 2px);
        max-height: calc(2.25rem + 2px);
        overflow: hidden;
    }

    .users-filter-card .ts-wrapper.single .ts-control .item {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .users-filter-card .ts-wrapper.single .ts-control input {
        flex: 0 1 1rem;
        min-width: 0;
        width: 1rem !important;
    }

    .users-name-filter .ts-wrapper.multi .ts-control {
        align-items: center;
        flex-wrap: nowrap;
        height: calc(2.25rem + 2px);
        min-height: calc(2.25rem + 2px);
        max-height: calc(2.25rem + 2px);
        overflow-x: auto;
        overflow-y: hidden;
    }

    .users-name-filter .ts-wrapper.multi .ts-control > div {
        flex: 0 0 auto;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .users-name-filter .ts-wrapper.multi .ts-control input {
        flex: 0 0 13rem;
        min-width: 13rem;
    }

    .users-current-view {
        background: color-mix(in srgb, var(--app-card-bg) 88%, var(--app-muted-bg));
        border: 1px solid var(--app-soft-border);
    }

    .users-table {
        min-width: 760px;
    }

    .users-table thead th {
        padding-top: .85rem;
        padding-bottom: .85rem;
    }

    .users-table tbody td {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .users-identity {
        display: flex;
        align-items: flex-start;
        gap: .85rem;
        min-width: 18rem;
    }

    .users-avatar {
        width: 2.55rem;
        height: 2.55rem;
        flex: 0 0 2.55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid color-mix(in srgb, var(--bs-primary) 26%, var(--app-soft-border));
        border-radius: 50%;
        background: color-mix(in srgb, var(--bs-primary) 10%, var(--app-card-bg));
        color: var(--bs-primary);
        font-size: .82rem;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .users-muted-line {
        color: var(--bs-secondary-color);
        font-size: .86rem;
        line-height: 1.35;
    }

    .users-meta-list {
        display: grid;
        gap: .22rem;
    }

    .users-meta-line {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem .5rem;
        align-items: baseline;
    }

    .users-meta-label {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .users-access-stack {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        align-items: center;
    }

    .users-actions {
        min-width: 11rem;
    }

    .users-delete-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .28rem;
    }

    .users-delete-button-icon {
        width: .95rem;
        height: .95rem;
        flex: 0 0 auto;
        display: inline-block;
    }

    @media (max-width: 767.98px) {
        .users-current-view {
            padding: 1rem !important;
        }

        .users-filter-actions {
            width: 100%;
        }

        .users-filter-actions .btn {
            flex: 1 1 auto;
        }
    }
</style>
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Users</h1>
        <div class="text-muted">
            {{ auth()->user()->role === 'super_admin' ? 'Manage accounts, roles, employee numbers, and access status.' : 'View Admin, HOD, and Employee profiles. Super Admin profiles are hidden.' }}
        </div>
    </div>
    @if(auth()->user()->role === 'super_admin')
        <a class="btn btn-primary" href="{{ route('manage.users.create') }}">New User</a>
    @endif
</div>

<form class="filter-card users-filter-card mb-3" method="get" action="{{ route('manage.users.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-3 users-name-filter">
            <label class="form-label" for="search">Search by name</label>
            <select
                class="form-select @error('search') is-invalid @enderror"
                id="search"
                name="search[]"
                multiple
                data-searchable="false"
                data-user-lookup-url="{{ route('manage.users.index') }}"
                placeholder="Search employees by name"
            >
                @foreach($selectedSearchNames as $selectedSearchName)
                    <option value="{{ $selectedSearchName }}" selected>{{ $selectedSearchName }}</option>
                @endforeach
            </select>
            @error('search')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                <option value="">All departments</option>
                <option value="unassigned" @selected($selectedDepartmentId === 'unassigned')>Unassigned</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label" for="role">Role</label>
            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role">
                <option value="">All roles</option>
                @foreach($visibleRoleLabels as $role => $label)
                    <option value="{{ $role }}" @selected($selectedRole === $role)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label" for="region">Region</label>
            <select class="form-select @error('region') is-invalid @enderror" id="region" name="region">
                <option value="">All regions</option>
                @foreach($regionLabels as $region => $label)
                    <option value="{{ $region }}" @selected($selectedRegion === $region)>{{ $label }}</option>
                @endforeach
            </select>
            @error('region')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-lg-auto">
            <div class="users-filter-actions d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Apply Filters</button>
                @if($hasUserFilters)
                    <a class="btn btn-outline-secondary" href="{{ route('manage.users.index') }}">Clear</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="content-card users-current-view p-3 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="fw-semibold">Current view</div>
            <div class="text-muted small">
                @if($hasUserFilters)
                    Showing users matching the selected filters.
                @else
                    Showing all visible users.
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-start justify-content-lg-end gap-2">
            @if($selectedDepartmentId === 'unassigned')
                <span class="badge filter-summary-badge px-3 py-2">Department: Unassigned</span>
            @elseif(filled($selectedDepartmentId))
                <span class="badge filter-summary-badge px-3 py-2">Department: {{ $selectedDepartment?->name ?? 'Selected department' }}</span>
            @endif
            @if(filled($selectedRole))
                <span class="badge filter-summary-badge px-3 py-2">Role: {{ $selectedRoleLabel }}</span>
            @endif
            @if(filled($selectedRegion))
                <span class="badge filter-summary-badge px-3 py-2">Region: {{ $selectedRegionLabel }}</span>
            @endif
            @foreach($selectedSearchNames as $selectedSearchName)
                <span class="badge filter-summary-badge px-3 py-2">Search: {{ $selectedSearchName }}</span>
            @endforeach
            @unless($hasUserFilters)
                <span class="badge filter-summary-badge px-3 py-2">No filters applied</span>
            @endunless
            <span class="badge filter-summary-badge px-3 py-2">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
        </div>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Employment</th>
                    <th>Access</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    @php($assignedDepartments = $user->primaryDepartments->merge($user->managedDepartments)->unique('id')->values())
                    @php($displayInitials = $user->initials ?: \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->join(''))
                    @php($userRegion = \Illuminate\Support\Str::startsWith((string) $user->employee_code, 'MEC-PHIL-HR-') ? 'ph' : (\Illuminate\Support\Str::startsWith((string) $user->employee_code, ['MEC-HR-', 'MCE-HR-']) ? 'uae' : 'unknown'))
                    <tr>
                        <td>
                            <div class="users-identity">
                                <span class="users-avatar" aria-hidden="true">{{ $displayInitials ?: 'U' }}</span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="users-muted-line text-break">{{ $user->email }}</div>
                                    <div class="users-meta-line mt-1">
                                        <span class="users-meta-label">Employee</span>
                                        <span class="small">{{ $user->employee_code ?: '-' }}</span>
                                        <span class="text-muted small">/</span>
                                        <span class="users-meta-label">Region</span>
                                        <span class="small">{{ $regionLabels[$userRegion] ?? 'Unknown' }}</span>
                                        <span class="text-muted small">/</span>
                                        <span class="users-meta-label">Initials</span>
                                        <span class="small">{{ $user->initials ?: '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="users-meta-list">
                                <div>
                                    <div class="fw-semibold">{{ $user->job_title ?: '-' }}</div>
                                    <div class="users-muted-line">{{ $user->department?->name ?: 'No department assigned' }}</div>
                                </div>
                                <div class="users-meta-line">
                                    <span class="users-meta-label">Joined</span>
                                    <span class="small">{{ $user->joining_date?->format('M d, Y') ?: '-' }}</span>
                                </div>
                                <div class="users-meta-line">
                                    <span class="users-meta-label">Profile</span>
                                    <span class="small">{{ $genderLabels[$user->gender] ?? '-' }} / {{ $maritalStatusLabels[$user->marital_status] ?? '-' }}</span>
                                </div>
                                @if($assignedDepartments->isNotEmpty())
                                    <div class="users-muted-line">HOD for {{ $assignedDepartments->pluck('name')->join(', ') }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="users-access-stack">
                                <span class="badge bg-body-secondary border text-body">{{ $roleLabels[$user->role] ?? $user->role }}</span>
                                <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </td>
                        <td class="text-end users-actions">
                            <div class="action-group justify-content-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('manage.users.show', $user) }}">View</a>
                                @if(auth()->user()->role === 'super_admin' || (auth()->user()->role === 'admin' && in_array($user->role, ['hod', 'employee'], true)))
                                    <a class="btn btn-sm btn-primary" href="{{ route('manage.users.edit', $user) }}">Edit</a>
                                @endif
                                @if(auth()->user()->role === 'super_admin' && (int) $user->id !== (int) auth()->id())
                                    <button type="button" class="btn btn-sm btn-outline-danger users-delete-button" data-bs-toggle="modal" data-bs-target="#deleteUserModal{{ $user->id }}">
                                        <svg class="users-delete-button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M10 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M6 7l1 13h10l1-13" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                            <path d="M9 7V4h6v3" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                        </svg>
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                @endforeach
                @if($users->isEmpty())
                    <tr>
                        <td colspan="4" class="empty-state">No users match the selected filters.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@foreach($users as $user)
    @php($assignedDepartments = $user->primaryDepartments->merge($user->managedDepartments)->unique('id')->values())
    @php($replacementCandidates = $assignedDepartments->isNotEmpty() ? $replacementHods->where('id', '!=', $user->id) : collect())
    @if(auth()->user()->role === 'super_admin' && (int) $user->id !== (int) auth()->id())
        <div class="modal fade" id="deleteUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('manage.users.destroy', $user) }}">
                        @csrf
                        @method('delete')
                        <div class="modal-header">
                            <h5 class="modal-title">Delete user</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Delete <strong>{{ $user->name }}</strong>?</p>
                            <p class="text-muted mb-3">This will permanently remove the user and all timesheets and entries owned by this user.</p>

                            @if($assignedDepartments->isNotEmpty())
                                <div class="alert alert-warning">
                                    This user is assigned as Head of Department for {{ $assignedDepartments->pluck('name')->join(', ') }}. Select a replacement Head of Department before deleting.
                                </div>
                                <label class="form-label">Replacement Head of Department</label>
                                <select class="form-select" name="replacement_hod_id" required data-searchable="false" @disabled($replacementCandidates->isEmpty())>
                                    <option value="">Select replacement</option>
                                    @foreach($replacementCandidates as $replacementHod)
                                        <option value="{{ $replacementHod->id }}">{{ $replacementHod->name }} - {{ $replacementHod->employee_code }}</option>
                                    @endforeach
                                </select>
                                @if($replacementCandidates->isEmpty())
                                    <div class="form-text text-danger">Create or update another active Head of Department before deleting this user.</div>
                                @endif
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger" @disabled($assignedDepartments->isNotEmpty() && $replacementCandidates->isEmpty())>Delete User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

@include('shared.pagination-footer', ['paginator' => $users, 'label' => 'user'])
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userSearchSelect = document.getElementById('search');

        if (! userSearchSelect || ! window.TomSelect || userSearchSelect.tomselect) {
            return;
        }

        const lookupState = {
            query: '',
            page: 0,
            hasMore: true,
            loading: false,
        };

        const fetchUserOptions = async (query, page) => {
            const url = new URL(userSearchSelect.dataset.userLookupUrl, window.location.origin);
            url.searchParams.set('user_lookup', '1');
            url.searchParams.set('page', page);

            if (query) {
                url.searchParams.set('q', query);
            }

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('User lookup failed.');
            }

            return response.json();
        };

        const userTomSelect = new TomSelect(userSearchSelect, {
            allowEmptyOption: true,
            create: false,
            dropdownParent: 'body',
            loadThrottle: 300,
            maxItems: null,
            maxOptions: null,
            plugins: {
                remove_button: {
                    title: 'Remove selected user',
                },
            },
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            sortField: [{ field: '$order' }],
            placeholder: userSearchSelect.getAttribute('placeholder') || 'Search employees by name',
            shouldLoad: function () {
                return true;
            },
            load: function (query, callback) {
                lookupState.query = query;
                lookupState.page = 1;
                lookupState.loading = true;

                fetchUserOptions(query, 1)
                    .then((data) => {
                        lookupState.hasMore = Boolean(data.has_more);
                        callback(data.results || []);
                    })
                    .catch(() => callback())
                    .finally(() => {
                        lookupState.loading = false;
                    });
            },
        });

        const loadNextUserPage = async () => {
            if (lookupState.loading || ! lookupState.hasMore) {
                return;
            }

            lookupState.loading = true;

            try {
                const nextPage = lookupState.page + 1;
                const data = await fetchUserOptions(lookupState.query, nextPage);

                lookupState.page = data.page || nextPage;
                lookupState.hasMore = Boolean(data.has_more);
                (data.results || []).forEach((option) => userTomSelect.addOption(option));
                userTomSelect.refreshOptions(false);
            } catch (error) {
                lookupState.hasMore = false;
            } finally {
                lookupState.loading = false;
            }
        };

        userTomSelect.on('dropdown_open', function () {
            if (lookupState.page === 0 && ! lookupState.loading) {
                userTomSelect.load('');
            }
        });

        userTomSelect.dropdown_content.addEventListener('scroll', function () {
            const distanceFromBottom = this.scrollHeight - this.scrollTop - this.clientHeight;

            if (distanceFromBottom < 80) {
                loadNextUserPage();
            }
        });
    });
</script>
@endpush
