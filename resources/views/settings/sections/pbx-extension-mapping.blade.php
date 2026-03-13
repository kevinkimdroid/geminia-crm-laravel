<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">PBX Extension Mapping</h5>
        <p class="text-muted small mb-0">Map PBX extensions to CRM users so the Users column in PBX Manager shows who received each call.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('settings.pbx-extension-mapping.sync') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Sync from vTiger</button>
        </form>
        <button type="button" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px" data-bs-toggle="modal" data-bs-target="#addMappingModal">
            <i class="bi bi-plus-lg me-1"></i> Add Mapping
        </button>
    </div>
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
            <table class="table table-hover align-middle mb-0 settings-table">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em" width="80">Extension</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">User</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em" width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($pbxMappings ?? []) as $m)
                    <tr>
                        <td class="px-4 font-monospace fw-semibold">{{ $m->extension }}</td>
                        <td class="px-4">{{ $m->user_name ?? '—' }}</td>
                        <td class="px-4">
                            <form action="{{ route('settings.pbx-extension-mapping.destroy', $m->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this mapping?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">
                            <i class="bi bi-telephone d-block mb-2" style="font-size:2rem;opacity:.5"></i>
                            No mappings yet. Add extensions or click "Sync from vTiger" to import from user phone preferences.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add Mapping Modal --}}
<div class="modal fade" id="addMappingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.pbx-extension-mapping.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Extension Mapping</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Extension <span class="text-danger">*</span></label>
                        <input type="text" name="extension" class="form-control" placeholder="e.g. 101" required maxlength="32">
                        <small class="text-muted">PBX/SIP extension number (digits only)</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">CRM User <span class="text-danger">*</span></label>
                        <select name="vtiger_user_id" class="form-select" required>
                            <option value="">Select user...</option>
                            @foreach($vtigerUsers ?? [] as $u)
                            <option value="{{ $u->id }}">{{ trim($u->first_name . ' ' . $u->last_name) ?: $u->user_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background:var(--geminia-primary);color:#fff">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
