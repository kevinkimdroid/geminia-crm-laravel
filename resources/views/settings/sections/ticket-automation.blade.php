<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Ticket Assignment Rules</h5>
        <p class="text-muted small mb-0">Automatically assign tickets to users when title or description contains certain keywords.</p>
    </div>
    <button type="button" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px" data-bs-toggle="modal" data-bs-target="#addRuleModal">
        <i class="bi bi-plus-lg me-1"></i> Add Rule
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
            <table class="table table-hover align-middle mb-0 settings-table">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em" width="100">Actions</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">Name</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">Keywords</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">Assign To</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">Priority</th>
                        <th class="text-uppercase small fw-bold py-3 px-4" style="color:var(--geminia-text-muted);letter-spacing:0.05em">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($automationRules ?? []) as $rule)
                    <tr>
                        <td class="px-4">
                            <div class="d-flex gap-1">
                                <a href="{{ route('settings.crm') }}?section=ticket-automation&edit={{ $rule->id }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('settings.ticket-automation.destroy', $rule->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 fw-semibold">{{ $rule->name ?? '—' }}</td>
                        <td class="px-4 text-muted small">{{ Str::limit($rule->keywords ?? '—', 50) }}</td>
                        <td class="px-4">{{ trim(($rule->first_name ?? '') . ' ' . ($rule->last_name ?? '')) ?: ($rule->user_name ?? '—') }}</td>
                        <td class="px-4">{{ $rule->priority ?? 0 }}</td>
                        <td class="px-4">
                            @if($rule->is_active ?? true)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No rules yet. Add a rule to auto-assign tickets by keywords.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add Rule Modal --}}
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.ticket-automation.store') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('settings.crm') }}?section=ticket-automation">
                <div class="modal-header">
                    <h5 class="modal-title">Add Assignment Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rule Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Claims keywords" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keywords <span class="text-danger">*</span></label>
                        <input type="text" name="keywords" class="form-control" placeholder="claims, claim, policy claim (comma-separated)" required>
                        <small class="text-muted">Comma-separated. Ticket title/description containing any keyword will trigger assignment.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assign To <span class="text-danger">*</span></label>
                        <select name="assign_to_user_id" class="form-select" required>
                            <option value="">Select user...</option>
                            @foreach($users ?? [] as $u)
                                <option value="{{ $u->id }}">{{ trim($u->first_name . ' ' . $u->last_name) ?: $u->user_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <input type="number" name="priority" class="form-control" value="0" min="0">
                        <small class="text-muted">Higher = checked first. Use to order rules.</small>
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="addRuleActive" checked>
                            <label class="form-check-label" for="addRuleActive">Active</label>
                        </div>
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

{{-- Edit Rule Modal --}}
@if(($editRule ?? null))
<div class="modal fade show d-block" id="editRuleModal" tabindex="-1" style="background: rgba(0,0,0,0.5)">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.ticket-automation.update', $editRule->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect" value="{{ route('settings.crm') }}?section=ticket-automation">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Assignment Rule</h5>
                    <a href="{{ route('settings.crm') }}?section=ticket-automation" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rule Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $editRule->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keywords <span class="text-danger">*</span></label>
                        <input type="text" name="keywords" class="form-control" value="{{ $editRule->keywords }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assign To <span class="text-danger">*</span></label>
                        <select name="assign_to_user_id" class="form-select" required>
                            @foreach($users ?? [] as $u)
                                <option value="{{ $u->id }}" {{ $editRule->assign_to_user_id == $u->id ? 'selected' : '' }}>{{ trim($u->first_name . ' ' . $u->last_name) ?: $u->user_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <input type="number" name="priority" class="form-control" value="{{ $editRule->priority ?? 0 }}" min="0">
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="editRuleActive" {{ ($editRule->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="editRuleActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('settings.crm') }}?section=ticket-automation" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
