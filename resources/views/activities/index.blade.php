@extends('layouts.app')

@section('title', 'Calendar & Activities')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Calendar & Activities</h1>
        <p class="page-subtitle">Schedule meetings, events, and tasks.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('activities.create', ['type' => 'Event']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i> Add Event
        </a>
        <a href="{{ route('activities.create', ['type' => 'Task']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i> Add Task
        </a>
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

<div class="card activities-card">
    <div class="card-body p-0">
        <div class="activities-table-header bg-primary text-white px-3 py-2">
            <div class="row g-0 align-items-center text-uppercase small fw-bold">
                <div class="col"><span class="d-inline-flex align-items-center">Status <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Activity Type <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Subject <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Related To <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Start Date & Time <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Due Date <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Repeat <i class="bi bi-arrow-down-up ms-1"></i></span></div>
                <div class="col"><span class="d-inline-flex align-items-center">Assigned To <i class="bi bi-arrow-down-up ms-1"></i></span></div>
            </div>
        </div>
        <form action="{{ route('activities.index') }}" method="GET" class="p-3 border-bottom bg-light" id="activitiesFilterForm">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Client</label>
                    <select name="contact_id" class="form-select form-select-sm" id="filterContactId">
                        <option value="">— Select Client —</option>
                        @foreach($contacts ?? [] as $c)
                        <option value="{{ $c->contactid }}" {{ ($contactId ?? null) == $c->contactid ? 'selected' : '' }}>
                            {{ trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Contact #' . $c->contactid }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Ticket</label>
                    <select name="ticket_id" class="form-select form-select-sm" id="filterTicketId">
                        <option value="">— All Tickets —</option>
                        @foreach($tickets ?? [] as $t)
                        <option value="{{ $t->ticketid }}" {{ ($ticketId ?? null) == $t->ticketid ? 'selected' : '' }}>
                            {{ $t->ticket_no ?? $t->title ?? 'Ticket #' . $t->ticketid }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="Task" {{ ($activityType ?? '') === 'Task' ? 'selected' : '' }}>Task</option>
                        <option value="Event" {{ ($activityType ?? '') === 'Event' ? 'selected' : '' }}>Event</option>
                        <option value="Meeting" {{ ($activityType ?? '') === 'Meeting' ? 'selected' : '' }}>Meeting</option>
                        <option value="Call" {{ ($activityType ?? '') === 'Call' ? 'selected' : '' }}>Call</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <input type="text" name="status" class="form-control form-control-sm" placeholder="Status" value="{{ $status ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Subject</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Subject" value="{{ $search ?? '' }}">
                </div>
            </div>
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search me-1"></i> Filter</button>
                </div>
                @if($contactId || $ticketId)
                <div class="col-auto">
                    <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
                @endif
            </div>
        </form>
        {{-- Add Event / Add Task bar (below header, above table) --}}
        <div class="quick-create-bar d-flex flex-wrap align-items-center gap-2 p-3 border-bottom bg-white">
            <button type="button" class="btn btn-success btn-sm quick-create-trigger" data-type="Event">
                <i class="bi bi-calendar-event me-1"></i> Add Event
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-create-trigger" data-type="Task">
                <i class="bi bi-check2-square me-1"></i> Add Task
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle activities-table mb-0">
                <tbody>
                    @forelse ($activities as $act)
                    <tr>
                        <td class="activities-td">{{ $act->status ?? '—' }}</td>
                        <td class="activities-td"><span class="badge bg-secondary">{{ $act->activitytype ?? 'Task' }}</span></td>
                        <td class="activities-td fw-semibold">{{ $act->subject ?? 'Untitled' }}</td>
                        <td class="activities-td">
                            @if(!empty(trim($act->related_to_name ?? '')))
                                <a href="{{ route('contacts.show', $act->related_to_id) }}">{{ trim($act->related_to_name) }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="activities-td text-nowrap">{{ $act->date_start ? date('d M Y', strtotime($act->date_start)) . ($act->time_start ? ' ' . $act->time_start : '') : '—' }}</td>
                        <td class="activities-td text-nowrap">{{ $act->due_date ? date('d M Y', strtotime($act->due_date)) : '—' }}</td>
                        <td class="activities-td">{{ $act->recurringtype ?: '—' }}</td>
                        <td class="activities-td">{{ trim($act->assigned_to_name ?? '') ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="activities-empty-state">
                                <div class="activities-empty-icon"><i class="bi bi-calendar3"></i></div>
                                @if(!$contactId && !$ticketId)
                                <h6 class="mt-3 mb-2">Select a client to view activities</h6>
                                <p class="text-muted mb-3">Choose a client (and optionally a ticket) above to see their calendar activities.</p>
                                @else
                                <h6 class="mt-3 mb-2">No activities yet</h6>
                                <p class="text-muted mb-3">Schedule a meeting or task for this client to get started.</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary-custom quick-create-trigger" data-type="Event"><i class="bi bi-plus-lg me-1"></i> Add Event</button>
                                    <button type="button" class="btn btn-outline-secondary quick-create-trigger" data-type="Task"><i class="bi bi-plus-lg me-1"></i> Add Task</button>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- Quick create bar below table --}}
        <div class="quick-create-footer d-flex flex-wrap align-items-center gap-2 p-3 border-top bg-light">
            <span class="text-muted small me-2">Quick add:</span>
            <button type="button" class="btn btn-success btn-sm quick-create-trigger" data-type="Event">
                <i class="bi bi-calendar-event me-1"></i> Add Event
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-create-trigger" data-type="Task">
                <i class="bi bi-check2-square me-1"></i> Add Task
            </button>
            <a href="{{ route('activities.create', ['type' => 'Event']) }}" class="btn btn-link btn-sm text-muted ms-auto">Go to full form</a>
        </div>
    </div>
</div>

{{-- Quick Create Event Modal --}}
<div class="modal fade" id="quickCreateModal" tabindex="-1" aria-labelledby="quickCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="quickCreateModalLabel">Quick Create Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('activities.store') }}" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Event or task subject" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">From</label>
                            <div class="input-group input-group-sm">
                                <input type="date" name="date_start" class="form-control" value="{{ date('Y-m-d') }}" required>
                                <input type="time" name="time_start" class="form-control" value="09:00" placeholder="Time">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">To</label>
                            <div class="input-group input-group-sm">
                                <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d') }}">
                                <input type="time" name="time_end" class="form-control" value="09:30" placeholder="Time">
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Assigned To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select form-select-sm" {{ ($users ?? collect())->isNotEmpty() ? 'required' : '' }}>
                                @if(($users ?? collect())->isEmpty())
                                <option value="{{ auth()->guard('vtiger')->id() ?? 1 }}">{{ auth()->guard('vtiger')->user()->full_name ?? 'Current User' }}</option>
                                @else
                                @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ (auth()->guard('vtiger')->id() ?? null) == $u->id ? 'selected' : '' }}>
                                    {{ $u->full_name }}
                                </option>
                                @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Status</label>
                            <select name="status" class="form-select form-select-sm" id="quickStatus">
                                <option value="Planned">Planned</option>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Pending Input">Pending Input</option>
                                <option value="Deferred">Deferred</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Activity Type</label>
                            <select name="activitytype" class="form-select form-select-sm" id="quickActivityTypeSelect">
                                <option value="Event">Event</option>
                                <option value="Task">Task</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Call">Call</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Related To (Client)</label>
                        <select name="related_to" class="form-select form-select-sm" id="quickRelatedTo">
                            <option value="">— None —</option>
                            @foreach($contacts ?? [] as $c)
                            <option value="{{ $c->contactid }}" {{ ($contactId ?? null) == $c->contactid ? 'selected' : '' }}>
                                {{ trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Contact #' . $c->contactid }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" id="quickTicketWrapper">
                        <label class="form-label fw-semibold small">Ticket</label>
                        <select name="ticket_id" class="form-select form-select-sm" id="quickTicketId">
                            <option value="">— None —</option>
                            @foreach($tickets ?? [] as $t)
                            <option value="{{ $t->ticketid }}" {{ ($ticketId ?? null) == $t->ticketid ? 'selected' : '' }}>
                                {{ $t->ticket_no ?? $t->title ?? 'Ticket #' . $t->ticketid }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="send_notification" value="1" class="form-check-input" id="quickNotify">
                        <label class="form-check-label small" for="quickNotify">Send Notification</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="send_email" value="1" class="form-check-input" id="quickEmail">
                        <label class="form-check-label small" for="quickEmail">Send Email</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 gap-2">
                    <a href="{{ route('activities.create', ['type' => 'Event']) }}" class="btn btn-outline-secondary btn-sm" id="quickFullFormLink">Go to full form</a>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.quick-create-trigger').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        const modal = new bootstrap.Modal(document.getElementById('quickCreateModal'));
        document.getElementById('quickActivityTypeSelect').value = type;
        document.getElementById('quickCreateModalLabel').textContent = 'Quick Create ' + type;
        document.getElementById('quickFullFormLink').href = '{{ url("activities/create") }}?type=' + type;
        if (type === 'Task') {
            document.getElementById('quickStatus').value = 'Not Started';
        } else {
            document.getElementById('quickStatus').value = 'Planned';
        }
        modal.show();
    });
});

// Load tickets when client changes in Quick Create modal
document.getElementById('quickRelatedTo')?.addEventListener('change', function() {
    const contactId = this.value;
    const ticketSelect = document.getElementById('quickTicketId');
    if (!ticketSelect) return;
    ticketSelect.innerHTML = '<option value="">— None —</option>';
    ticketSelect.value = '';
    if (!contactId) return;
    fetch('{{ url("/api/contacts") }}/' + contactId + '/tickets')
        .then(r => r.json())
        .then(tickets => {
            tickets.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.ticketid;
                opt.textContent = t.ticket_no || t.title || 'Ticket #' + t.ticketid;
                ticketSelect.appendChild(opt);
            });
        })
        .catch(() => {});
});

// Load tickets in filter when client changes (no page reload)
document.getElementById('filterContactId')?.addEventListener('change', function() {
    const contactId = this.value;
    const ticketSelect = document.getElementById('filterTicketId');
    if (!ticketSelect) return;
    ticketSelect.innerHTML = '<option value="">— All Tickets —</option>';
    ticketSelect.value = '';
    if (!contactId) return;
    fetch('{{ url("/api/contacts") }}/' + contactId + '/tickets')
        .then(r => r.json())
        .then(tickets => {
            tickets.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.ticketid;
                opt.textContent = t.ticket_no || t.title || 'Ticket #' + t.ticketid;
                ticketSelect.appendChild(opt);
            });
        })
        .catch(() => {});
});
</script>

<style>
.activities-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); overflow: hidden; }
.quick-create-bar { border-bottom: 1px solid rgba(14, 67, 133, 0.08); }
.activities-table-header { font-size: 0.7rem; letter-spacing: 0.08em; }
.activities-td { padding: 0.75rem 1rem; vertical-align: middle; }
.activities-table tbody tr:hover { background: var(--primary-muted, rgba(14, 67, 133, 0.06)); }
.activities-empty-icon { width: 80px; height: 80px; margin: 0 auto; background: var(--primary-light, rgba(14, 67, 133, 0.12)); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary, #0E4385); }
#quickCreateModal .modal-content { border-radius: 16px; }
</style>
@endsection
