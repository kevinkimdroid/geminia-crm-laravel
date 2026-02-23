@extends('layouts.app')

@section('title', 'Roles Setup')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h1 class="page-title">Roles Setup</h1>
        <p class="page-subtitle">Manage roles and configure which modules each role can access.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.crm') }}?section=roles" class="btn btn-outline-secondary">Back to Settings</a>
        <a href="{{ route('settings.crm') }}?section=users" class="btn btn-primary-custom">Manage Users</a>
    </div>
</div>

<div class="row g-4">
    @foreach ($roles as $role)
    <div class="col-md-6 col-lg-4">
        <div class="card setup-role-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold">{{ $role->rolename }}</h6>
                    <span class="badge setup-profile-badge">{{ $role->profiles->count() }} profile(s)</span>
                </div>
                @if ($role->profiles->isNotEmpty())
                    <p class="text-muted small mb-3">
                        Profile: {{ $role->profiles->first()->profilename }}
                    </p>
                    <a href="{{ route('setup.roles.modules', $role->roleid) }}" class="btn btn-primary-custom w-100">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Configure Modules
                    </a>
                @else
                    <p class="text-warning small mb-0">No profile assigned. Assign a profile in Vtiger first.</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<style>
.setup-role-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: transform .2s, box-shadow .2s; }
.setup-role-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(14, 67, 133, 0.1); }
.setup-profile-badge { font-size: 0.7rem; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px; background: var(--primary-muted, rgba(14, 67, 133, 0.06)); color: var(--primary, #0E4385); }
</style>
@endsection
