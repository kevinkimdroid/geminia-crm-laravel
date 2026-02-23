<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Groups</h5>
        <p class="text-muted small mb-0">Manage user groups and team assignments.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-lg me-1"></i> Add Group
    </button>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="app-card overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="100">Actions</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Name</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($groups ?? []) as $group)
                    <tr>
                        <td class="px-4">
                            <div class="d-flex gap-1">
                                <a href="{{ route('settings.crm') }}?section=groups&edit={{ $group->groupid }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('settings.groups.destroy', $group->groupid) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this group?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 fw-semibold">{{ $group->groupname ?? '—' }}</td>
                        <td class="px-4 text-muted">{{ $group->description ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">No groups found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(($groupsTotal ?? 0) > 0)
        <div class="card-footer bg-transparent border-top d-flex justify-content-between align-items-center py-3 px-4">
            @php $count = count($groups ?? []); $from = (($groupsPage ?? 1) - 1) * ($groupsPerPage ?? 50) + 1; @endphp
            <span class="text-muted small">{{ $from }} to {{ $from + $count - 1 }} of {{ number_format($groupsTotal ?? 0) }}</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ ($groupsPage ?? 1) <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('settings.crm') }}?section=groups&page={{ max(1, ($groupsPage ?? 1) - 1) }}"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <li class="page-item {{ ($groupsPage ?? 1) * ($groupsPerPage ?? 50) >= ($groupsTotal ?? 0) ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('settings.crm') }}?section=groups&page={{ ($groupsPage ?? 1) + 1 }}"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        @endif
    </div>
</div>

{{-- Add Group Modal --}}
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.groups.store') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('settings.crm') }}?section=groups">
                <div class="modal-header">
                    <h5 class="modal-title">Add Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="groupname" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
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

{{-- Edit Group Modal (shown when ?edit=id) --}}
@if(($editGroup ?? null))
<div class="modal fade show d-block" id="editGroupModal" tabindex="-1" style="background: rgba(0,0,0,0.5)">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.groups.update', $editGroup->groupid) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect" value="{{ route('settings.crm') }}?section=groups">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Group</h5>
                    <a href="{{ route('settings.crm') }}?section=groups" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="groupname" class="form-control" value="{{ $editGroup->groupname }}" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ $editGroup->description ?? '' }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('settings.crm') }}?section=groups" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
