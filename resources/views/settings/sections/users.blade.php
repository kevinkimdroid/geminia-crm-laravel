<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--geminia-text)">Users</h5>
        <p class="text-muted small mb-0">Assign users and roles.</p>
    </div>
    <a href="{{ route('settings.crm') }}?section=roles" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px">Manage Roles</a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

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
            @foreach ($users ?? [] as $user)
            <tr>
                <td class="col-user">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:var(--geminia-primary-muted);color:var(--geminia-primary)"><i class="bi bi-person-fill"></i></div>
                        <strong>{{ $user->full_name }}</strong>
                    </div>
                </td>
                <td class="col-email">{{ $user->email1 ?? '—' }}</td>
                <td class="col-username" title="{{ $user->user_name }}"><code class="small">{{ $user->user_name }}</code></td>
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
                    <form action="{{ route('setup.users.update-role') }}" method="POST" class="d-inline-flex align-items-center gap-2">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                        <select name="role_id" class="form-select form-select-sm" style="min-width:200px">
                            @foreach ($roles ?? [] as $r)
                                <option value="{{ $r->roleid }}" {{ (($userRoles ?? [])[$user->id] ?? '') === $r->roleid ? 'selected' : '' }}>{{ $r->rolename }}</option>
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
