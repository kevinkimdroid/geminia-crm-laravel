<nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
    <a href="{{ route('settings.crm') }}" class="text-muted small text-decoration-none">Settings</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-muted small">User Management</span>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Users</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--geminia-text)">Users</h5>
        <p class="text-muted small mb-0">Assign users and roles.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('settings.users.create') }}" class="btn btn-sm btn-outline-primary" style="border-radius:8px">
            <i class="bi bi-person-plus me-1"></i>Add user
        </a>
        <a href="{{ route('setup.users') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">
            <i class="bi bi-diagram-3 me-1"></i>Reporting lines
        </a>
        <a href="{{ route('settings.crm') }}?section=roles" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">Manage Roles</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Search and filters --}}
<form method="GET" action="{{ route('settings.crm') }}" class="mb-4" id="usersSearchForm">
    <input type="hidden" name="section" value="users">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="usersSearch" class="form-label small text-muted mb-1">Search users</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="usersSearch" name="search" class="form-control" placeholder="Name, email, or username..." value="{{ $usersSearch ?? '' }}" aria-label="Search users">
            </div>
        </div>
        <div class="col-md-2">
            <label for="usersStatusFilter" class="form-label small text-muted mb-1">Status</label>
            <select id="usersStatusFilter" name="status" class="form-select">
                <option value="active" {{ ($usersStatusFilter ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ ($usersStatusFilter ?? '') === 'inactive' ? 'selected' : '' }}>Left</option>
                <option value="all" {{ ($usersStatusFilter ?? '') === 'all' ? 'selected' : '' }}>All</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="usersRoleFilter" class="form-label small text-muted mb-1">Role</label>
            <select id="usersRoleFilter" name="role" class="form-select">
                <option value="">All roles</option>
                @foreach ($roles ?? [] as $r)
                    <option value="{{ $r->roleid }}" {{ ($usersRoleFilter ?? '') == $r->roleid ? 'selected' : '' }}>{{ $r->rolename }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="usersDeptFilter" class="form-label small text-muted mb-1">Department</label>
            <select id="usersDeptFilter" name="department" class="form-select">
                <option value="">All</option>
                @foreach($departmentsList ?? [] as $d)
                    <option value="{{ $d }}" {{ ($usersDeptFilter ?? '') === $d ? 'selected' : '' }}>{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">
                <i class="bi bi-search me-1"></i>Search
            </button>
            @if ($usersSearch ?? $usersRoleFilter ?? $usersDeptFilter ?? ($usersStatusFilter ?? 'active') !== 'active')
                <a href="{{ route('settings.crm') }}?section=users" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>
            @endif
        </div>
    </div>
</form>

<p class="text-muted small mb-3">
    <strong>{{ count($users ?? []) }}</strong> user{{ count($users ?? []) !== 1 ? 's' : '' }} found
    @if ($usersSearch ?? $usersRoleFilter ?? $usersDeptFilter ?? '')
        <span class="ms-2">— <a href="{{ route('settings.crm') }}?section=users" class="text-decoration-none">Show all</a></span>
    @endif
</p>

<div class="table-responsive users-table-wrapper">
    <table class="table table-hover align-middle mb-0 settings-table">
        <thead>
            <tr>
                <th class="col-user">User</th>
                <th class="col-email">Email</th>
                <th class="col-username">Username</th>
                <th class="col-dept">Department</th>
                <th class="col-reporting">Reports To</th>
                <th class="col-role">Current Role</th>
                <th class="col-action">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users ?? [] as $user)
            @php $isInactive = ($user->status ?? '') === 'Inactive'; @endphp
            <tr class="{{ $isInactive ? 'table-secondary' : '' }}">
                <td class="col-user">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:var(--geminia-primary-muted);color:var(--geminia-primary)"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <strong>{{ $user->full_name }}</strong>
                            @if ($isInactive)
                                <span class="badge bg-secondary ms-1">Left</span>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="col-email">{{ $user->email1 ?? '—' }}</td>
                <td class="col-username" title="{{ $user->user_name }}"><code class="small">{{ $user->user_name }}</code></td>
                <td class="col-dept">
                    @if (!$isInactive)
                    <form id="dept-form-{{ $user->id }}" action="{{ route('settings.users.update-department', $user->id) }}" method="POST" class="d-inline-flex align-items-center gap-2" style="flex-wrap:nowrap">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                        <select name="department" class="form-select form-select-sm" style="min-width:140px">
                            <option value="">— Not set —</option>
                            @foreach($departmentsList ?? [] as $d)
                                <option value="{{ $d }}" {{ (($userDepartments ?? [])[$user->id] ?? '') === $d ? 'selected' : '' }}>{{ $d }}</option>
                            @endforeach
                        </select>
                        <button type="submit" form="dept-form-{{ $user->id }}" name="save_dept" value="1" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px;padding:.25rem .65rem;white-space:nowrap">Save</button>
                    </form>
                    @else
                        <span class="text-muted">{{ ($userDepartments ?? [])[$user->id] ?? '—' }}</span>
                    @endif
                </td>
                <td class="col-reporting">
                    @if (!$isInactive)
                    <form id="reporting-form-{{ $user->id }}" action="{{ route('settings.users.update-reporting-manager', $user->id) }}" method="POST" class="d-inline-flex align-items-center gap-2" style="flex-wrap:nowrap">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                        <select name="manager_id" class="form-select form-select-sm" style="min-width:170px">
                            <option value="">— Not set —</option>
                            @foreach(($reportingManagerOptions ?? []) as $mgr)
                                @continue((int) $mgr->id === (int) $user->id)
                                <option value="{{ $mgr->id }}" {{ (int) (($reportingLines ?? [])[$user->id] ?? 0) === (int) $mgr->id ? 'selected' : '' }}>
                                    {{ trim(($mgr->first_name ?? '') . ' ' . ($mgr->last_name ?? '')) ?: ($mgr->user_name ?? ('User #' . $mgr->id)) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" form="reporting-form-{{ $user->id }}" name="save_reporting" value="1" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px;padding:.25rem .65rem;white-space:nowrap">Save</button>
                    </form>
                    @else
                        @php $rid = (int) (($reportingLines ?? [])[$user->id] ?? 0); @endphp
                        @if($rid > 0)
                            <span class="text-muted">User #{{ $rid }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    @endif
                </td>
                @php
                    $currentRoleId = ($userRoles ?? [])[$user->id] ?? null;
                    $role = $currentRoleId ? (($roles ?? collect())->firstWhere('roleid', $currentRoleId)) : null;
                    $roleDisplay = $role ? $role->rolename : ($currentRoleId ?? 'No role');
                @endphp
                <td class="col-role" title="{{ $roleDisplay }}">
                    @if ($currentRoleId)
                        <span class="badge" style="background:var(--geminia-primary-muted);color:var(--geminia-primary)">{{ $roleDisplay }}</span>
                    @else
                        <span class="text-muted">No role</span>
                    @endif
                </td>
                <td class="col-action">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if ($isInactive)
                            <form action="{{ route('settings.users.reactivate', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reactivate {{ $user->full_name }}? They will be able to sign in again.');">
                                @csrf
                                <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                                <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius:8px" title="Reactivate user">
                                    <i class="bi bi-person-check"></i> Reactivate
                                </button>
                            </form>
                        @else
                            <a href="{{ route('settings.users.edit', $user->id) }}?redirect={{ urlencode(request()->fullUrl()) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px" title="Edit user details">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form action="{{ route('settings.users.send-reset-link', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send a password reset link to {{ $user->email1 ?? $user->user_name }}?');">
                                @csrf
                                <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:8px" title="Send password reset email" {{ empty(trim($user->email1 ?? '')) ? 'disabled' : '' }}>
                                    <i class="bi bi-key"></i> Reset
                                </button>
                            </form>
                            <form id="role-form-{{ $user->id }}" action="{{ route('setup.users.update-role') }}" method="POST" class="d-inline-flex align-items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                                <select name="role_id" class="form-select form-select-sm" style="min-width:160px">
                                    @foreach ($roles ?? [] as $r)
                                        <option value="{{ $r->roleid }}" {{ (($userRoles ?? [])[$user->id] ?? '') === $r->roleid ? 'selected' : '' }}>{{ $r->rolename }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" form="role-form-{{ $user->id }}" name="save_role" value="1" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">Save</button>
                            </form>
                            @if ($user->id !== (auth()->guard('vtiger')->id()))
                            <a href="{{ route('settings.users.offboard', $user->id) }}?redirect={{ urlencode(request()->fullUrl()) }}" class="btn btn-sm btn-outline-danger" style="border-radius:8px" title="Offboard user (reassign records, then deactivate)">
                                <i class="bi bi-person-x"></i> Offboard
                            </a>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people display-6 d-block mb-2"></i>
                    No users found. Try different search terms or clear filters.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<style>
.users-table-wrapper { max-height: calc(100vh - 380px); overflow-y: auto; }
.users-table-wrapper thead th { position: sticky; top: 0; background: #f8fafc !important; z-index: 1; box-shadow: 0 1px 0 var(--geminia-border); }
.col-dept { min-width: 220px; }
.col-reporting { min-width: 250px; }
</style>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var roleFilter = document.getElementById('usersRoleFilter');
    if (roleFilter) roleFilter.addEventListener('change', function() { document.getElementById('usersSearchForm')?.submit(); });
});
</script>
@endpush
