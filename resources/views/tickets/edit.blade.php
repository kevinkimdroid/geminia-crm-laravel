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
                <div class="col-md-6 position-relative" id="contactSearchWrapper">
                    <label class="form-label fw-semibold">Client / Contact <span class="text-danger">*</span></label>
                    <div class="input-group contact-search-input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-person-fill text-muted"></i></span>
                        <input type="text" id="contactSearch" class="form-control" placeholder="Type name or policy number to search" autocomplete="off" value="{{ old('contact_display', $contactDisplay ?? '') }}" aria-autocomplete="list" aria-expanded="false" aria-controls="contactDropdown" role="combobox">
                        <input type="hidden" name="contact_id" id="contactId" value="{{ old('contact_id', $ticket->contact_id ?? '') }}" required>
                        <button type="button" id="contactBrowse" class="btn btn-outline-primary" title="Browse all clients"><i class="bi bi-list-ul me-1"></i>Browse</button>
                        <button type="button" id="contactChange" class="btn btn-outline-secondary d-none" title="Choose different client"><i class="bi bi-arrow-repeat me-1"></i>Change</button>
                        <a href="{{ route('contacts.create') }}" class="btn btn-outline-secondary" title="Add new client"><i class="bi bi-plus-lg"></i></a>
                    </div>
                    <small class="text-muted d-block mt-1" id="contactSearchHint">Type to search or click Browse to select a client</small>
                    <div id="contactDropdown" class="contact-dropdown position-absolute w-100 mt-1 shadow border rounded-3 overflow-hidden" style="display: none; z-index: 1050; max-height: 320px; overflow-y: auto; background: #fff; border: 1px solid var(--geminia-border, #e2e8f0) !important;" role="listbox"></div>
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
                        @foreach(ticket_categories() as $cat)
                        <option value="{{ $cat }}" {{ old('category', $ticket->category) == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Ticket Source</label>
                    <select name="ticket_source" class="form-select">
                        <option value="">Select an Option</option>
                        @foreach(ticket_sources() as $src)
                        <option value="{{ $src }}" {{ old('ticket_source', $ticket->source ?? '') == $src ? 'selected' : '' }}>{{ $src }}</option>
                        @endforeach
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
                    <label class="form-label fw-semibold">Policy Number</label>
                    <input type="text" name="policy_number" id="policy_number" class="form-control font-monospace" value="{{ old('policy_number', $editPolicy ?? '') }}" placeholder="e.g. GEMPPP0334">
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

<script id="clientsData" type="application/json">@json(collect($clients ?? [])->map(fn($c) => ['id' => $c->contactid, 'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Client #' . $c->contactid, 'policy' => $c->policy_number ?? ''])->values())</script>
<script>
(function() {
    const initialClients = JSON.parse(document.getElementById('clientsData').textContent || '[]');
    let clients = initialClients.slice();
    const searchInput = document.getElementById('contactSearch');
    const contactIdInput = document.getElementById('contactId');
    const dropdown = document.getElementById('contactDropdown');
    const browseBtn = document.getElementById('contactBrowse');
    const changeBtn = document.getElementById('contactChange');
    const hintEl = document.getElementById('contactSearchHint');
    let fetchTimer;
    let highlightedIdx = -1;
    let currentItems = [];
    let justSelected = false;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function setSelectedMode(selected) {
        if (browseBtn) browseBtn.classList.toggle('d-none', selected);
        if (changeBtn) changeBtn.classList.toggle('d-none', !selected);
        if (hintEl) hintEl.textContent = selected ? 'Client selected. Click Change to pick a different one.' : 'Type to search or click Browse to select a client';
        searchInput.readOnly = selected;
    }
    function renderDropdown(items, isLoading, noResults) {
        currentItems = items || [];
        highlightedIdx = -1;
        if (isLoading) {
            dropdown.innerHTML = '<div class="list-group-item text-muted py-3 text-center"><span class="spinner-border spinner-border-sm me-2"></span>Searching...</div>';
            dropdown.style.display = 'block';
        } else if (noResults || (currentItems.length === 0 && (searchInput.value || '').trim().length >= 1)) {
            dropdown.innerHTML = '<div class="list-group-item text-muted py-3 text-center">No contacts found. Try a different search or <a href="{{ route("contacts.create") }}">add a new client</a>.</div>';
            dropdown.style.display = 'block';
        } else if (currentItems.length) {
            dropdown.innerHTML = currentItems.slice(0, 60).map((c, i) => {
                const policy = String(c.policy || c.policy_number || '').trim();
                return `<a href="#" class="list-group-item list-group-item-action py-2 contact-option" data-id="${c.id}" data-name="${escapeHtml(c.name)}" data-policy="${escapeHtml(policy)}" role="option" data-index="${i}"><i class="bi bi-person me-2 text-muted"></i>${escapeHtml(c.name)}</a>`;
            }).join('');
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
        searchInput.setAttribute('aria-expanded', dropdown.style.display === 'block');
    }
    function renderFromLocal() {
        const term = (searchInput.value || '').trim().toLowerCase();
        const filtered = term ? clients.filter(c => (c.name || '').toLowerCase().includes(term)) : clients.slice(0, 80);
        renderDropdown(filtered);
    }
    function selectItem(item) {
        if (!item) return;
        justSelected = true;
        contactIdInput.value = item.dataset.id;
        searchInput.value = item.dataset.name;
        const policyInput = document.getElementById('policy_number') || document.querySelector('input[name="policy_number"]');
        if (policyInput) {
            const policy = (item.dataset.policy || '').trim();
            policyInput.value = policy;
            fetch('{{ route("api.tickets.contact.policy", ["contact" => ":id"]) }}'.replace(':id', item.dataset.id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json()).then(d => { policyInput.value = (d.policy_number || d.policy || policy || '').trim(); }).catch(() => {});
        }
        dropdown.style.display = 'none';
        searchInput.setAttribute('aria-expanded', 'false');
        setSelectedMode(true);
    }
    function fetchContacts(browseMode) {
        const q = searchInput.value.trim();
        const shouldBrowse = browseMode || q.length === 0;
        const url = '{{ route("api.tickets.contacts") }}?limit=80' + (shouldBrowse ? '&browse=1' : '&q=' + encodeURIComponent(q));
        renderDropdown(null, true);
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data) && data.length) renderDropdown(data);
            else if (!shouldBrowse) renderDropdown([], false, true);
            else renderFromLocal();
        })
        .catch(() => renderFromLocal());
    }

    searchInput.addEventListener('focus', function() {
        if (justSelected) { justSelected = false; return; }
        if (contactIdInput.value) return;
        const q = this.value.trim();
        clearTimeout(fetchTimer);
        fetchContacts(q.length === 0);
    });
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(fetchTimer);
        setSelectedMode(false);
        contactIdInput.value = '';
        const policyInput = document.getElementById('policy_number') || document.querySelector('input[name="policy_number"]');
        if (policyInput) policyInput.value = '';
        fetchTimer = setTimeout(() => fetchContacts(false), q.length ? 200 : 0);
    });
    searchInput.addEventListener('keydown', function(e) {
        if (dropdown.style.display !== 'block' || !currentItems.length) return;
        const opts = dropdown.querySelectorAll('[data-id]');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIdx = Math.min(highlightedIdx + 1, opts.length - 1);
            opts[highlightedIdx]?.scrollIntoView({ block: 'nearest' });
            opts.forEach((o, i) => o.classList.toggle('active', i === highlightedIdx));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIdx = Math.max(highlightedIdx - 1, 0);
            opts[highlightedIdx]?.scrollIntoView({ block: 'nearest' });
            opts.forEach((o, i) => o.classList.toggle('active', i === highlightedIdx));
        } else if (e.key === 'Enter' && highlightedIdx >= 0 && opts[highlightedIdx]) {
            e.preventDefault();
            selectItem(opts[highlightedIdx]);
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
            searchInput.setAttribute('aria-expanded', 'false');
        }
    });
    searchInput.addEventListener('blur', () => setTimeout(() => {
        if (document.activeElement?.closest('#contactSearchWrapper')) return;
        dropdown.style.display = 'none';
        searchInput.setAttribute('aria-expanded', 'false');
    }, 180));

    dropdown.addEventListener('mousedown', (e) => {
        const item = e.target.closest('[data-id]');
        if (item) {
            e.preventDefault();
            selectItem(item);
            setSelectedMode(true);
        }
    });

    if (browseBtn) {
        browseBtn.addEventListener('click', function() {
            searchInput.focus();
            fetchContacts(true);
        });
    }
    if (changeBtn) {
        changeBtn.addEventListener('click', function() {
            contactIdInput.value = '';
            searchInput.value = '';
            const policyInput = document.getElementById('policy_number') || document.querySelector('input[name="policy_number"]');
            if (policyInput) policyInput.value = '';
            setSelectedMode(false);
            searchInput.focus();
            fetchContacts(true);
        });
    }

    const oldId = contactIdInput.value;
    if (oldId) {
        const c = clients.find(x => String(x.id) === String(oldId));
        if (c) searchInput.value = c.name;
        setSelectedMode(true);
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
