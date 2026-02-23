@extends('layouts.app')

@section('title', 'Creating New Ticket')

@push('head')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
@endpush

@section('content')
<nav class="mb-3">
    @if($fromServeClient ?? false)
    <a href="{{ route('support.serve-client') }}" class="text-muted small text-decoration-none">Serve Client</a>
    <span class="text-muted mx-2">/</span>
    @endif
    <a href="{{ route('tickets.index') }}" class="text-muted small text-decoration-none">Tickets</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">New Ticket</span>
</nav>
<h1 class="app-page-title mb-4">Create Ticket</h1>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('tickets.store') }}">
    @csrf
    @if($fromServeClient ?? false)
    <input type="hidden" name="return_to_serve_client" value="1">
    @elseif($presetContactId ?? null)
    <input type="hidden" name="return_to_contact" value="{{ $presetContactId }}">
    @endif

    {{-- Ticket Information --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Information</h6>
            <div class="row g-4">
                {{-- Left column --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Assigned To <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        @foreach ($users ?? [] as $u)
                        <option value="{{ $u->id }}" {{ old('assigned_to', auth()->guard('vtiger')->id()) == $u->id ? 'selected' : '' }}>
                            {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name }}
                        </option>
                        @endforeach
                        @if(empty($users) || $users->isEmpty())
                        <option value="{{ auth()->guard('vtiger')->id() ?? '' }}" selected>{{ $currentUserName ?? 'Current User' }}</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Ticket Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Policy inquiry, claim follow-up, document request" value="{{ old('title') }}" required>
                    @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <option value="Low" {{ old('priority') == 'Low' ? 'selected' : '' }}>Low</option>
                        <option value="Normal" {{ old('priority', 'Normal') == 'Normal' ? 'selected' : '' }}>Normal</option>
                        <option value="High" {{ old('priority') == 'High' ? 'selected' : '' }}>High</option>
                    </select>
                </div>
                <div class="col-md-6 position-relative">
                    <label class="form-label fw-semibold">Contact / Client <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="contactSearch" class="form-control" placeholder="{{ ($presetContactId ?? null) && ($fromServeClient ?? false) ? 'Client selected from Serve Client' : 'Type to search clients...' }}" autocomplete="off" value="{{ old('contact_display', $presetContactDisplay ?? '') }}" {{ ($presetContactId ?? null) && ($fromServeClient ?? false) ? 'readonly' : '' }}>
                        <input type="hidden" name="contact_id" id="contactId" value="{{ old('contact_id', $presetContactId ?? '') }}" required>
                        @if(($presetContactId ?? null) && ($fromServeClient ?? false))
                        <a href="{{ route('support.serve-client') }}" class="btn btn-outline-secondary" title="Change client">Change</a>
                        @else
                        <a href="{{ route('contacts.create') }}" class="btn btn-outline-secondary" title="Add client"><i class="bi bi-plus-lg"></i></a>
                        @endif
                    </div>
                    @if(($presetContactId ?? null) && ($fromServeClient ?? false) && ($presetContactDisplay ?? ''))
                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Client pre-selected.</small>
                    @elseif(!($presetContactDisplay ?? ''))
                    <small class="text-muted">Search and select the client this ticket is for.</small>
                    @endif
                    <div id="contactDropdown" class="list-group position-absolute w-100 mt-1 shadow border rounded-2" style="max-height: 200px; overflow-y: auto; display: none; z-index: 1000; border-color:var(--geminia-border)!important;"></div>
                    @error('contact_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">Select an Option</option>
                        <option value="Minor" {{ old('severity') == 'Minor' ? 'selected' : '' }}>Minor</option>
                        <option value="Major" {{ old('severity') == 'Major' ? 'selected' : '' }}>Major</option>
                        <option value="Critical" {{ old('severity') == 'Critical' ? 'selected' : '' }}>Critical</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Product Name</label>
                    <select name="product_id" id="productSelect" class="form-select">
                        <option value="">Type to search</option>
                        @foreach ($products ?? [] as $p)
                        <option value="{{ $p->productid }}" {{ old('product_id') == $p->productid ? 'selected' : '' }}>{{ $p->productname ?? 'Product #' . $p->productid }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="Open" {{ old('status', 'Open') == 'Open' ? 'selected' : '' }}>Open</option>
                        <option value="In Progress" {{ old('status') == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="Wait For Response" {{ old('status') == 'Wait For Response' ? 'selected' : '' }}>Wait For Response</option>
                        @if($canCloseTickets ?? true)
                        <option value="Closed" {{ old('status') == 'Closed' ? 'selected' : '' }}>Closed</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Days</label>
                    <input type="text" name="days" class="form-control" placeholder="SLA days" value="{{ old('days') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Ticket Source <span class="text-danger">*</span></label>
                    <select name="ticket_source" class="form-select" required>
                        <option value="">Select an Option</option>
                        <option value="CRM" {{ old('ticket_source', ($fromServeClient ?? false) ? 'Phone' : 'CRM') == 'CRM' ? 'selected' : '' }}>CRM</option>
                        <option value="Email" {{ old('ticket_source') == 'Email' ? 'selected' : '' }}>Email</option>
                        <option value="Web" {{ old('ticket_source') == 'Web' ? 'selected' : '' }}>Web</option>
                        <option value="Phone" {{ old('ticket_source', ($fromServeClient ?? false) ? 'Phone' : '') == 'Phone' ? 'selected' : '' }}>Phone</option>
                    </select>
                    @if($fromServeClient ?? false)
                    <small class="text-muted">Default: Phone (from Serve Client)</small>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Hours</label>
                    <input type="text" name="hours" class="form-control" placeholder="SLA hours" value="{{ old('hours') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="">Select an Option</option>
                        @foreach(config('tickets.categories') as $cat)
                        <option value="{{ $cat }}" {{ old('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Product Line / Account</label>
                    <select name="organization_id" id="organizationSelect" class="form-select">
                        <option value="">Select an Option</option>
                        @foreach ($accounts ?? [] as $a)
                        <option value="{{ $a->accountid }}" {{ old('organization_id') == $a->accountid ? 'selected' : '' }}>{{ $a->accountname ?? 'Account #' . $a->accountid }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Optional — e.g. Credit Life, Group Life</small>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Policy Number</label>
                    @if($presetPolicy ?? null)
                    <input type="text" name="policy_number" class="form-control font-monospace" placeholder="Linked to this policy" value="{{ old('policy_number', $presetPolicy) }}">
                    <small class="text-muted">Pre-filled from client. Used for reference in the ticket.</small>
                    @else
                    <input type="text" name="policy_number" class="form-control font-monospace" placeholder="Optional — add if ticket relates to a policy" value="{{ old('policy_number') }}">
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Description Details --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Description Details</h6>
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Enter ticket description">{{ old('description') }}</textarea>
            @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- Ticket Resolution --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Resolution</h6>
            <label class="form-label fw-semibold">Solution</label>
            <textarea name="solution" class="form-control" rows="5" placeholder="Enter resolution when ticket is resolved">{{ old('solution') }}</textarea>
            <small class="text-muted">Required when closing a ticket. Add details of how the issue was resolved.</small>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn app-btn-primary"><i class="bi bi-check-lg me-1"></i> Save Ticket</button>
        @if($fromServeClient ?? false)
        <a href="{{ route('support.serve-client') }}" class="btn btn-outline-secondary">Back to Serve Client</a>
        @elseif($presetContactId ?? null)
        <a href="{{ route('contacts.show', $presetContactId) }}?tab=tickets" class="btn btn-outline-secondary">Cancel</a>
        @else
        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
        @endif
    </div>
</form>

<script id="clientsData" type="application/json">@json(collect($clients ?? [])->map(fn($c) => ['id' => $c->contactid, 'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Client #' . $c->contactid])->values())</script>
<script>
(function() {
    const initialClients = JSON.parse(document.getElementById('clientsData').textContent || '[]');
    let clients = initialClients.slice();
    const searchInput = document.getElementById('contactSearch');
    const contactIdInput = document.getElementById('contactId');
    const dropdown = document.getElementById('contactDropdown');
    let fetchTimer;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function renderDropdown(items) {
        const max = 20;
        dropdown.innerHTML = (items || clients).slice(0, max).map(c => `<a href="#" class="list-group-item list-group-item-action" data-id="${c.id}" data-name="${escapeHtml(c.name)}">${escapeHtml(c.name)}</a>`).join('');
        dropdown.style.display = (items || clients).length ? 'block' : 'none';
    }
    function renderFromLocal() {
        const term = (searchInput.value || '').trim().toLowerCase();
        const filtered = term ? clients.filter(c => (c.name || '').toLowerCase().includes(term)) : clients.slice(0, 50);
        renderDropdown(filtered);
    }
    function fetchContacts() {
        const q = searchInput.value.trim();
        if (q.length < 2) {
            renderFromLocal();
            return;
        }
        fetch('{{ route("api.tickets.contacts") }}?q=' + encodeURIComponent(q) + '&limit=30', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data) && data.length) renderDropdown(data);
            else renderFromLocal();
        })
        .catch(() => renderFromLocal());
    }

    searchInput.addEventListener('focus', function() {
        const q = this.value.trim();
        if (q.length >= 2) {
            clearTimeout(fetchTimer);
            fetchTimer = setTimeout(fetchContacts, 150);
        } else {
            renderFromLocal();
        }
    });
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(fetchTimer);
        if (q.length >= 2) {
            fetchTimer = setTimeout(fetchContacts, 250);
        } else {
            renderFromLocal();
        }
    });
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
    const productEl = document.getElementById('productSelect');
    const orgEl = document.getElementById('organizationSelect');
    if (typeof TomSelect === 'undefined') return;

    if (productEl) {
        new TomSelect(productEl, {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Type to search',
            maxOptions: 100,
            load: function(q, callback) {
                const url = '{{ route("api.tickets.products") }}?q=' + encodeURIComponent(q) + '&limit=50';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(function(data) {
                        callback(data || []);
                    })
                    .catch(function() { callback([]); });
            },
            preload: 'focus'
        });
    }
    if (orgEl) {
        new TomSelect(orgEl, {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Select an Option',
            maxOptions: null
        });
    }
})();
</script>
@endpush
@endsection
