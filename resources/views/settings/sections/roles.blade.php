<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Roles</h5>
        <p class="text-muted small mb-0">Manage roles and configure which modules each role can access.</p>
    </div>
    <a href="{{ route('settings.crm') }}?section=users" class="btn btn-outline-secondary btn-sm">Manage Users</a>
</div>

<div class="row g-4">
    @foreach ($roles ?? [] as $role)
    <div class="col-md-6 col-lg-4">
        <div class="app-card h-100 overflow-hidden">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold">{{ $role->rolename }}</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary">{{ $role->profiles->count() }} profile(s)</span>
                </div>
                @if ($role->profiles->isNotEmpty())
                    <p class="text-muted small mb-3">Profile: {{ $role->profiles->first()->profilename }}</p>
                    <a href="{{ route('setup.roles.modules', $role->roleid) }}" class="btn btn-primary btn-sm w-100">Configure Modules</a>
                @else
                    <p class="text-warning small mb-0">No profile assigned. Assign a profile in Vtiger first.</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
