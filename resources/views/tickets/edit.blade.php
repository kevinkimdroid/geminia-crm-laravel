@extends('layouts.app')

@section('title', 'Edit Ticket — ' . ($ticket->title ?? 'Ticket'))

@push('head')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
@endpush

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('tickets.index') }}" class="text-muted small text-decoration-none">Tickets</a>
    <span class="text-muted mx-2">/</span>
    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="text-muted small text-decoration-none">{{ Str::limit($ticket->title ?? 'Ticket', 30) }}</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Edit</span>
</nav>
<h1 class="app-page-title mb-4">Edit Ticket</h1>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('tickets.update', $ticket->ticketid) }}">
    @csrf
    @method('PUT')

    {{-- Ticket Information --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Information</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Assigned To <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        @foreach ($users ?? [] as $u)
                        <option value="{{ $u->id }}" {{ old('assigned_to', $ticket->smownerid ?? '') == $u->id ? 'selected' : '' }}>
                            {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name }}
                        </option>
                        @endforeach
                        @if(empty($users) || $users->isEmpty())
                        <option value="{{ $ticket->assigned_to ?? $ticket->smownerid ?? '' }}" selected>Current assignee</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $ticket->title) }}" required>
                    @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" {{ !($canCloseTickets ?? true) && ($ticket->status ?? '') === 'Closed' ? 'disabled' : '' }}>
                        <option value="Open" {{ old('status', $ticket->status) == 'Open' ? 'selected' : '' }}>Open</option>
                        <option value="In Progress" {{ old('status', $ticket->status) == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="Wait For Response" {{ old('status', $ticket->status) == 'Wait For Response' ? 'selected' : '' }}>Wait For Response</option>
                        @if(($canCloseTickets ?? true) || ($ticket->status ?? '') === 'Closed')
                        <option value="Closed" {{ old('status', $ticket->status) == 'Closed' ? 'selected' : '' }}>Closed</option>
                        @endif
                        <option value="Inactive" {{ old('status', $ticket->status) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @if(!($canCloseTickets ?? true) && ($ticket->status ?? '') === 'Closed')
                    <input type="hidden" name="status" value="Closed">
                    @endif
                    @if(!($canCloseTickets ?? true) && ($ticket->status ?? '') !== 'Closed')
                    <small class="text-muted">Only certain roles can close tickets.</small>
                    @endif
                </div>
                <div class="col-md-6 position-relative">
                    <label class="form-label fw-semibold">Client / Contact <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="contactSearch" class="form-control" placeholder="Type to search clients" autocomplete="off" value="{{ old('contact_display', $contactDisplay ?? '') }}">
                        <input type="hidden" name="contact_id" id="contactId" value="{{ old('contact_id', $ticket->contact_id ?? '') }}" required>
                        <a href="{{ route('contacts.create') }}" class="btn btn-outline-secondary" title="Add client"><i class="bi bi-plus-lg"></i></a>
                    </div>
                    <div id="contactDropdown" class="list-group position-absolute w-100 mt-1 shadow border rounded-2" style="max-height: 200px; overflow-y: auto; display: none; z-index: 1000; border-color:var(--geminia-border)!important;"></div>
                    @error('contact_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="Low" {{ old('priority', $ticket->priority) == 'Low' ? 'selected' : '' }}>Low</option>
                        <option value="Normal" {{ old('priority', $ticket->priority) == 'Normal' ? 'selected' : '' }}>Normal</option>
                        <option value="High" {{ old('priority', $ticket->priority) == 'High' ? 'selected' : '' }}>High</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">Select an Option</option>
                        <option value="Minor" {{ old('severity', $ticket->severity) == 'Minor' ? 'selected' : '' }}>Minor</option>
                        <option value="Major" {{ old('severity', $ticket->severity) == 'Major' ? 'selected' : '' }}>Major</option>
                        <option value="Critical" {{ old('severity', $ticket->severity) == 'Critical' ? 'selected' : '' }}>Critical</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">Select an Option</option>
                        @foreach(config('tickets.categories') as $cat)
                        <option value="{{ $cat }}" {{ old('category', $ticket->category) == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Ticket Source</label>
                    <select name="ticket_source" class="form-select">
                        <option value="">Select an Option</option>
                        <option value="CRM" {{ old('ticket_source', $ticket->source ?? '') == 'CRM' ? 'selected' : '' }}>CRM</option>
                        <option value="Email" {{ old('ticket_source', $ticket->source ?? '') == 'Email' ? 'selected' : '' }}>Email</option>
                        <option value="Web" {{ old('ticket_source', $ticket->source ?? '') == 'Web' ? 'selected' : '' }}>Web</option>
                        <option value="Phone" {{ old('ticket_source', $ticket->source ?? '') == 'Phone' ? 'selected' : '' }}>Phone</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Product Line / Account</label>
                    <select name="organization_id" id="organizationSelect" class="form-select">
                        <option value="">— Select Product Line —</option>
                        @php
                            $productLines = collect($accounts ?? []);
                            if ($productLines->isEmpty()) {
                                $productLines = collect([
                                    (object)['accountid' => 'line:Individual Life', 'accountname' => 'Individual Life'],
                                    (object)['accountid' => 'line:Group Life', 'accountname' => 'Group Life'],
                                    (object)['accountid' => 'line:Credit Life', 'accountname' => 'Credit Life'],
                                    (object)['accountid' => 'line:Mortgage', 'accountname' => 'Mortgage'],
                                    (object)['accountid' => 'line:Group Last Expense', 'accountname' => 'Group Last Expense'],
                                ]);
                            }
                            $selectedOrg = old('organization_id', $presetOrganizationId ?? $ticket->parent_id ?? null);
                        @endphp
                        @foreach ($productLines as $a)
                        <option value="{{ $a->accountid ?? $a['accountid'] ?? '' }}" {{ $selectedOrg == ($a->accountid ?? $a['accountid'] ?? '') ? 'selected' : '' }}>{{ $a->accountname ?? $a['accountname'] ?? 'Option' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Hours</label>
                    <input type="text" name="hours" class="form-control" placeholder="SLA hours" value="{{ old('hours', $ticket->hours) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Days</label>
                    <input type="text" name="days" class="form-control" placeholder="SLA days" value="{{ old('days', $ticket->days) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- Description Details --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Description Details</h6>
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Enter ticket description">{{ old('description', $ticket->description) }}</textarea>
            @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- Ticket Resolution --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Resolution</h6>
            <label class="form-label fw-semibold">Solution</label>
            <textarea name="solution" class="form-control" rows="5" placeholder="Enter resolution when ticket is resolved">{{ old('solution', $ticket->solution ?? '') }}</textarea>
            <small class="text-muted">Add details of how the issue was resolved. Required when closing a ticket.</small>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn app-btn-primary"><i class="bi bi-check-lg me-1"></i> Update Ticket</button>
        <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script id="clientsData" type="application/json">@json(collect($clients ?? [])->map(fn($c) => ['id' => $c->contactid, 'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Client #' . $c->contactid])->values())</script>
<script>
(function() {
    const clients = JSON.parse(document.getElementById('clientsData').textContent || '[]');
    const searchInput = document.getElementById('contactSearch');
    const contactIdInput = document.getElementById('contactId');
    const dropdown = document.getElementById('contactDropdown');

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function renderDropdown(filter) {
        const term = (filter || '').toLowerCase();
        const filtered = term ? clients.filter(c => c.name.toLowerCase().includes(term)) : clients.slice(0, 50);
        dropdown.innerHTML = filtered.slice(0, 20).map(c => `<a href="#" class="list-group-item list-group-item-action" data-id="${c.id}" data-name="${escapeHtml(c.name)}">${escapeHtml(c.name)}</a>`).join('');
        dropdown.style.display = filtered.length ? 'block' : 'none';
    }

    searchInput.addEventListener('focus', () => renderDropdown(searchInput.value));
    searchInput.addEventListener('input', () => renderDropdown(searchInput.value));
    searchInput.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 200));

    dropdown.addEventListener('click', (e) => {
        const item = e.target.closest('[data-id]');
        if (item) {
            e.preventDefault();
            contactIdInput.value = item.dataset.id;
            searchInput.value = item.dataset.name;
            dropdown.style.display = 'none';
        }
    });

    const oldId = contactIdInput.value;
    if (oldId) {
        const c = clients.find(x => String(x.id) === String(oldId));
        if (c) searchInput.value = c.name;
    }
})();
</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function() {
    const orgEl = document.getElementById('organizationSelect');
    if (typeof TomSelect === 'undefined') return;

    if (orgEl) {
        new TomSelect(orgEl, {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Select an Option',
            maxOptions: 100,
            load: function(q, callback) {
                const url = '{{ route("api.tickets.accounts") }}?q=' + encodeURIComponent(q) + '&limit=50';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(function(data) { callback(data || []); })
                    .catch(function() { callback([]); });
            },
            preload: 'focus'
        });
    }
})();
</script>
@endpush
@endsection
