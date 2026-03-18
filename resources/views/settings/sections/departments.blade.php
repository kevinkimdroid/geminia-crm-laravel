<nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
    <a href="{{ route('settings.crm') }}" class="text-muted small text-decoration-none">Settings</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-muted small">People</span>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Departments</span>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--geminia-text)">Departments</h5>
        <p class="text-muted small mb-0">Add and manage departments. Assign users to departments from the Users section.</p>
    </div>
    <button type="button" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px" data-bs-toggle="modal" data-bs-target="#addDeptModal">
        <i class="bi bi-plus-lg me-1"></i> Add Department
    </button>
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

<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 settings-table">
        <thead>
            <tr>
                <th>Name</th>
                <th class="text-center">Users</th>
                <th class="text-end" style="min-width:100px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($departments ?? []) as $dept)
            <tr>
                <td class="fw-semibold">{{ $dept->name }}</td>
                <td class="text-center">
                    @if (isset($userCounts[$dept->name]))
                        <a href="{{ route('settings.crm') }}?section=users&department={{ urlencode($dept->name) }}" class="text-decoration-none">{{ $userCounts[$dept->name] }}</a>
                    @else
                        0
                    @endif
                </td>
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <a href="{{ route('settings.crm') }}?section=departments&edit={{ $dept->id }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('settings.departments.destroy', $dept) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this department?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                {{ (($userCounts ?? [])[$dept->name] ?? 0) > 0 ? 'disabled' : '' }}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center py-5 text-muted">No departments yet. Add one to get started.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Department Modal --}}
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.departments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Customer Service" required maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background:var(--geminia-primary);color:#fff;">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Department Modal --}}
@if(($editDepartment ?? null))
<div class="modal fade show d-block" id="editDeptModal" tabindex="-1" style="background: rgba(0,0,0,0.5)">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.departments.update', $editDepartment) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <a href="{{ route('settings.crm') }}?section=departments" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $editDepartment->name }}" required maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('settings.crm') }}?section=departments" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn" style="background:var(--geminia-primary);color:#fff;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
