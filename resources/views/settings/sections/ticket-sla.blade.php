<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h5 class="fw-bold mb-1">Ticket SLA & TAT</h5>
        <p class="text-muted small mb-0">SLA is per department. Ticket <strong>Category</strong> = <strong>Department</strong>. Set TAT (hours) for each department. Used for SLA breach reporting.</p>
    </div>
    <form action="{{ route('settings.ticket-sla.sync-categories') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i> Sync from ticket categories
        </button>
    </form>
</div>

@if(!empty($categoriesWithoutTat))
<div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
    <div>
        <strong>Categories without TAT:</strong> {{ implode(', ', $categoriesWithoutTat) }}
        <p class="mb-0 mt-1 small">Click <strong>Sync from ticket categories</strong> to add these as departments with 24h default TAT, or add them manually below.</p>
    </div>
</div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>{{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Who can close tickets --}}
<div class="app-card overflow-hidden mb-4">
    <div class="card-header bg-transparent border-bottom py-3">
        <h6 class="mb-0 fw-bold">Roles Allowed to Close Tickets</h6>
        <p class="text-muted small mb-0 mt-1">Only users with these roles can set ticket status to Closed.</p>
    </div>
    <div class="card-body">
        <form action="{{ route('settings.ticket-sla.update-roles') }}" method="POST">
            @csrf
            <div class="row g-2">
                @foreach($roles ?? [] as $role)
                <div class="col-md-4 col-lg-3">
                    <div class="form-check">
                        <input type="checkbox" name="roles_can_close[]" value="{{ $role->rolename }}" class="form-check-input" id="role_{{ $role->roleid }}"
                            {{ in_array($role->rolename, $rolesCanClose ?? []) ? 'checked' : '' }}>
                        <label class="form-check-label" for="role_{{ $role->roleid }}">{{ $role->rolename }}</label>
                    </div>
                </div>
                @endforeach
            </div>
            @if(empty($roles))
            <p class="text-muted small mb-0">No roles found. Administrator can close by default.</p>
            @endif
            <button type="submit" class="btn btn-primary mt-3">Save</button>
        </form>
    </div>
</div>

{{-- TAT per department --}}
<div class="app-card overflow-hidden">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold">TAT (Turnaround Time) per Department</h6>
            <p class="text-muted small mb-0 mt-1">Hours allowed to resolve tickets. Used for SLA breach reporting.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="bi bi-plus-lg me-1"></i> Add Department
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Department</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">TAT (hours)</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departmentTat ?? [] as $row)
                    <tr>
                        <td class="px-4 fw-semibold">{{ $row->department }}</td>
                        <td class="px-4">{{ $row->tat_hours }} hours</td>
                        <td class="px-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDeptModal" data-dept="{{ $row->department }}" data-hours="{{ $row->tat_hours }}">Edit</button>
                            <form action="{{ route('settings.ticket-sla.delete-department', $row->department) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this department?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">No departments configured. Add one to set TAT.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('reports.sla-broken') }}" class="btn btn-outline-primary">
        <i class="bi bi-exclamation-triangle me-1"></i> View Broken SLA Report
    </a>
</div>

{{-- Add Department Modal --}}
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.ticket-sla.add-department') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Department TAT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="department" class="form-control" placeholder="e.g. Claims, Underwriting" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">TAT (hours) <span class="text-danger">*</span></label>
                        <input type="number" name="tat_hours" class="form-control" value="24" min="1" max="720" required>
                        <small class="text-muted">Hours allowed to resolve tickets in this department.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Department Modal --}}
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.ticket-sla.update-department') }}" method="POST" id="editDeptForm">
                @csrf
                <input type="hidden" name="department" id="editDeptName">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department TAT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">TAT (hours) <span class="text-danger">*</span></label>
                        <input type="number" name="tat_hours" class="form-control" id="editDeptHours" min="1" max="720" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editDeptModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    if (btn?.dataset?.dept) {
        document.getElementById('editDeptName').value = btn.dataset.dept;
        document.getElementById('editDeptHours').value = btn.dataset.hours || 24;
    }
});
</script>
