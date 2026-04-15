@extends('layouts.app')

@section('title', 'New Work Ticket')

@section('content')
<div class="page-header">
    <nav class="mb-2">
        <a href="{{ route('work-tickets.index') }}" class="text-muted small">Work Tickets</a>
        <span class="text-muted mx-1">/</span>
        <span class="text-dark">New Ticket</span>
    </nav>
    <h1 class="page-title">Create Work Ticket</h1>
    <p class="page-subtitle">Create a ticket-style daily activity item for remote or office work tracking.</p>
</div>

<div class="card border-0 shadow-sm" style="max-width: 900px;">
    <div class="card-body p-4">
        <form action="{{ route('work-tickets.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" class="form-control" placeholder="What work needs to be done?" required>
                    @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control">
                    @error('due_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach(['Open', 'In Progress', 'Blocked', 'Done', 'Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'Open') === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        @foreach(['Low', 'Medium', 'High', 'Urgent'] as $priority)
                        <option value="{{ $priority }}" {{ old('priority', 'Medium') === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                        @endforeach
                    </select>
                    @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Assignee <span class="text-danger">*</span></label>
                    <select name="assignee_id" class="form-select" required>
                        <option value="">Select assignee</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}"
                                data-manager-id="{{ (int) ($managerMap[$u->id] ?? 0) }}"
                                {{ (string) old('assignee_id', auth()->guard('vtiger')->id()) === (string) $u->id ? 'selected' : '' }}>
                            {{ $u->full_name }}
                        </option>
                        @endforeach
                    </select>
                    @error('assignee_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Reporting Manager (Auto)</label>
                    <input type="text" id="reportingManagerDisplay" class="form-control" value="" readonly>
                    <input type="hidden" name="reporting_manager_id" id="reportingManagerId" value="{{ old('reporting_manager_id') }}">
                    @error('reporting_manager_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="small text-muted mt-2">
                Manager is assigned automatically from reporting lines.
                @if($canManageReportingLines ?? false)
                Admin can update mappings in <a href="{{ route('setup.users') }}">Setup Users</a>.
                @endif
            </div>

            <div class="mt-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" rows="4" class="form-control" placeholder="Add details and expected output...">{{ old('description') }}</textarea>
                @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="mt-3">
                <label class="form-label fw-semibold">Initial Daily Update (optional)</label>
                <textarea name="initial_update" rows="3" class="form-control" placeholder="e.g. Started at 9:00am, collecting data from client.">{{ old('initial_update') }}</textarea>
                @error('initial_update')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="bi bi-check2-circle me-1"></i> Create Ticket
                </button>
                <a href="{{ route('work-tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const assignee = document.querySelector('select[name="assignee_id"]');
    const managerIdInput = document.getElementById('reportingManagerId');
    const managerDisplay = document.getElementById('reportingManagerDisplay');
    const userNamesById = @json($userNamesById ?? []);

    function updateManagerFromAssignee() {
        if (!assignee || !managerIdInput || !managerDisplay) return;
        const selected = assignee.options[assignee.selectedIndex];
        const managerId = selected ? parseInt(selected.getAttribute('data-manager-id') || '0', 10) : 0;
        if (managerId > 0) {
            managerIdInput.value = managerId;
            managerDisplay.value = userNamesById[managerId] || ('User #' + managerId);
        } else {
            managerIdInput.value = '';
            managerDisplay.value = 'Not configured';
        }
    }

    assignee?.addEventListener('change', updateManagerFromAssignee);
    updateManagerFromAssignee();
})();
</script>
@endsection
