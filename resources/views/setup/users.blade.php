@extends('layouts.app')

@section('title', 'User Setup')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1">User Setup</h1>
        <p class="app-page-sub mb-0">Assign users and roles.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.crm') }}?section=users" class="btn btn-outline-secondary btn-sm">Back to CRM Settings</a>
        <a href="{{ route('setup.roles') }}" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">Manage Roles</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="app-card overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 settings-table">
                <thead>
                    <tr>
                        <th class="col-user">User</th>
                        <th class="col-email">Email</th>
                        <th class="col-username">Username</th>
                        <th class="col-role">Current Role</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                    <tr>
                        <td class="col-user">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:var(--geminia-primary-muted);color:var(--geminia-primary)"><i class="bi bi-person-fill"></i></div>
                                <strong>{{ $user->full_name }}</strong>
                            </div>
                        </td>
                        <td class="col-email">{{ $user->email1 ?? '—' }}</td>
                        <td class="col-username"><code class="small">{{ $user->user_name }}</code></td>
                        <td class="col-role">
                            @php $currentRoleId = $userRoles[$user->id] ?? null; $role = $currentRoleId ? $roles->firstWhere('roleid', $currentRoleId) : null; $roleDisplay = $role ? $role->rolename : ($currentRoleId ?? 'No role'); @endphp
                            @if ($currentRoleId)
                                <span class="badge" style="background:var(--geminia-primary-muted);color:var(--geminia-primary)">{{ $roleDisplay }}</span>
                            @else
                                <span class="text-muted">No role</span>
                            @endif
                        </td>
                        <td class="col-action">
                            <form action="{{ route('setup.users.update-role') }}" method="POST" class="d-inline-flex align-items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <select name="role_id" class="form-select form-select-sm" style="min-width:200px">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->roleid }}" {{ ($userRoles[$user->id] ?? '') === $role->roleid ? 'selected' : '' }}>{{ $role->rolename }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">Save</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.settings-table { width: 100%; border-collapse: collapse; }
.settings-table thead th { background: #f8fafc; border-bottom: 1px solid var(--geminia-border); padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--geminia-text-muted); }
.settings-table tbody td { padding: 0.75rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.settings-table tbody tr:hover { background: var(--geminia-primary-muted); }
.settings-table tbody tr:last-child td { border-bottom: none; }
.settings-table { table-layout: auto; min-width: 800px; }
.settings-table .col-user { min-width: 200px; }
.settings-table .col-email { min-width: 220px; }
.settings-table .col-username { min-width: 120px; }
.settings-table .col-role { min-width: 240px; }
.settings-table .col-action { min-width: 320px; }
</style>
@endsection
