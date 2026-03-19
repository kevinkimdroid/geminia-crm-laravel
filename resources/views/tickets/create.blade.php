@extends('layouts.app')

@section('title', 'Creating New Ticket')


@section('content')
<nav class="mb-3">
    @if($fromServeClient ?? false)
    <a href="{{ route('support.serve-client') }}" class="text-muted small text-decoration-none">Serve Client</a>
    <span class="text-muted mx-2">/</span>
    @elseif($fromMailManager ?? false)
    <a href="{{ route('tools.mail-manager') }}" class="text-muted small text-decoration-none">Mail Manager</a>
    <span class="text-muted mx-2">/</span>
    @elseif($fromLead ?? false)
    <a href="{{ route('leads.show', $returnToLead ?? '') }}" class="text-muted small text-decoration-none">Lead</a>
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
@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
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
    @elseif($fromMailManager ?? false)
    <input type="hidden" name="return_to_mail_manager" value="1">
    <input type="hidden" name="email_id" value="{{ $emailId ?? '' }}">
    @elseif(($fromLead ?? false) && ($returnToLead ?? null))
    <input type="hidden" name="return_to_lead" value="{{ $returnToLead }}">
    @elseif($presetContactId ?? null)
    <input type="hidden" name="return_to_contact" value="{{ $presetContactId }}">
    @endif

    {{-- Quick essentials --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">Ticket Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-lg" placeholder="e.g. Policy inquiry, claim follow-up" value="{{ old('title', $presetTitle ?? '') }}" required autofocus>
                    @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Assigned To <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        @foreach ($users ?? [] as $u)
                        <option value="{{ $u->id }}" {{ old('assigned_to', auth()->guard('vtiger')->id()) == $u->id ? 'selected' : '' }}>
                            {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name }}
                        </option>
                        @endforeach
                        @if(empty($users) || ($users ?? collect())->isEmpty())
                        <option value="{{ auth()->guard('vtiger')->id() ?? 1 }}" selected>Current user</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-6 position-relative" id="contactSearchWrapper">
                    <label class="form-label fw-semibold">Contact / Client <span class="text-danger">*</span></label>
                    <div class="input-group contact-select-wrapper">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-person-fill text-muted"></i></span>
                        <input type="text" id="contactSearch" class="form-control contact-select-input" placeholder="Type name or policy number to search" autocomplete="off" value="{{ old('contact_display', $presetContactDisplay ?? '') }}" {{ ($presetContactId ?? null) && ($fromServeClient ?? false) ? 'readonly' : '' }} aria-autocomplete="list" aria-expanded="false" aria-controls="contactDropdown" role="combobox">
                        <input type="hidden" name="contact_id" id="contactId" value="{{ old('contact_id', $presetContactId ?? '') }}" required>
                        @if(($presetContactId ?? null) && ($fromServeClient ?? false))
                        <a href="{{ route('support.serve-client') }}" class="btn btn-outline-secondary" title="Change client">Change</a>
                        @else
                        <button type="button" id="contactBrowse" class="btn btn-outline-primary" title="Browse all clients"><i class="bi bi-list-ul me-1"></i>Browse</button>
                        <button type="button" id="contactChange" class="btn btn-outline-secondary d-none" title="Choose different client"><i class="bi bi-arrow-repeat me-1"></i>Change</button>
                        <a href="{{ route('contacts.create') }}" class="btn btn-outline-secondary" title="Add new client"><i class="bi bi-plus-lg"></i></a>
                        @endif
                    </div>
                    <small class="text-muted d-block mt-1" id="contactSearchHint">Type to search or click Browse to select a client</small>
                    <div id="contactDropdown" class="contact-select-dropdown list-group position-absolute w-100 mt-1 shadow rounded-3" role="listbox"></div>
                    @error('contact_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Policy Number</label>
                    <input type="text" name="policy_number" id="policy_number" class="form-control font-monospace" value="{{ old('policy_number', $presetPolicy ?? '') }}" placeholder="Auto-fills when client selected">
                </div>
                <style>
                .contact-select-dropdown { max-height: 320px; overflow-y: auto; display: none; z-index: 1050; border: 1px solid var(--geminia-border, #e2e8f0); background: #fff; }
                .contact-select-dropdown .contact-option { padding: 0.75rem 1rem; cursor: pointer; border: none; display: flex; align-items: center; }
                .contact-select-dropdown .contact-option:hover, .contact-select-dropdown .contact-option.active { background: var(--geminia-primary-muted, rgba(26,70,138,0.08)); color: var(--geminia-primary); }
                .contact-select-dropdown .contact-option .bi { flex-shrink: 0; }
                .contact-select-dropdown .contact-hint { padding: 0.5rem 1rem; font-size: 0.8rem; color: var(--geminia-text-muted); background: #f8fafc; border-bottom: 1px solid #eee; }
                </style>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">Select an Option</option>
                        @foreach(ticket_categories() as $cat)
                        <option value="{{ $cat }}" {{ old('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief details if needed">{{ old('description', $presetDescription ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="send_email_to_client" id="send_email_to_client" value="1" class="form-check-input" {{ old('send_email_to_client', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="send_email_to_client">Send email notification to client</label>
                    </div>
                    <div class="ms-4" id="client-email-options">
                        <label class="form-label fw-normal text-muted small mb-1">Custom message for client email <span class="text-muted">(optional)</span></label>
                        <textarea name="client_email_message" class="form-control form-control-sm" rows="3" placeholder="e.g. We will process your policy renewal notice within 3 business days." maxlength="2000">{{ old('client_email_message') }}</textarea>
                        <p class="text-muted small mb-0 mt-1">Leave blank for the default message. When provided, this is inserted into the email sent to the client.</p>
                    </div>
                    <p class="text-muted small mb-0 mt-1">When checked, the client will receive an email with the ticket number after creation.</p>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="status" value="Open">
    <input type="hidden" name="priority" value="Normal">
    @php
        $presetSource = ($fromServeClient ?? false) ? 'Phone' : (($fromMailManager ?? false) ? 'Email' : 'CRM');
    @endphp

    {{-- More options: Product Line, Policy, Severity, Ticket Source --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <p class="small fw-semibold text-muted mb-3">Product Line, Policy, Severity, Ticket Source</p>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Ticket Source</label>
                    <select name="ticket_source" class="form-select">
                        <option value="">Select an Option</option>
                        @foreach(ticket_sources() as $src)
                        <option value="{{ $src }}" {{ old('ticket_source', $presetSource ?? '') == $src ? 'selected' : '' }}>{{ $src }}</option>
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
                            $selectedOrg = old('organization_id', $presetOrganizationId ?? null);
                        @endphp
                        @foreach ($productLines as $a)
                        <option value="{{ $a->accountid ?? $a['accountid'] ?? '' }}" {{ $selectedOrg == ($a->accountid ?? $a['accountid'] ?? '') ? 'selected' : '' }}>{{ $a->accountname ?? $a['accountname'] ?? 'Option' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">—</option>
                        <option value="Minor" {{ old('severity') == 'Minor' ? 'selected' : '' }}>Minor</option>
                        <option value="Major" {{ old('severity') == 'Major' ? 'selected' : '' }}>Major</option>
                        <option value="Critical" {{ old('severity') == 'Critical' ? 'selected' : '' }}>Critical</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn app-btn-primary"><i class="bi bi-check-lg me-1"></i> Save Ticket</button>
        @if($fromServeClient ?? false)
        <a href="{{ route('support.serve-client') }}" class="btn btn-outline-secondary">Back to Serve Client</a>
        @elseif($fromMailManager ?? false)
        <a href="{{ route('tools.mail-manager') }}" class="btn btn-outline-secondary">Back to Mail Manager</a>
        @elseif($presetContactId ?? null)
        <a href="{{ route('contacts.show', $presetContactId) }}?tab=tickets" class="btn btn-outline-secondary">Cancel</a>
        @else
        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
        @endif
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
            dropdown.innerHTML = '<div class="list-group-item text-muted py-4 text-center"><span class="spinner-border spinner-border-sm me-2"></span>Loading clients...</div>';
            dropdown.style.display = 'block';
        } else if (noResults || (currentItems.length === 0 && (searchInput.value || '').trim().length >= 1)) {
            dropdown.innerHTML = '<div class="list-group-item text-muted py-4 text-center">No clients found. Try a different search or <a href="{{ route("contacts.create") }}" class="text-primary">add a new client</a>.</div>';
            dropdown.style.display = 'block';
        } else if (currentItems.length) {
            dropdown.innerHTML = currentItems.slice(0, 60).map((c, i) => {
                const policy = String(c.policy || c.policy_number || '').trim();
                return `<a href="#" class="list-group-item list-group-item-action py-2" data-id="${c.id}" data-name="${escapeHtml(c.name)}" data-policy="${escapeHtml(policy)}" role="option" data-index="${i}"><i class="bi bi-person me-2 text-muted"></i>${escapeHtml(c.name)}</a>`;
            }).join('');
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
        searchInput.setAttribute('aria-expanded', dropdown.style.display === 'block');
    }
    function renderFromLocal() {
        const term = (searchInput.value || '').trim().toLowerCase();
        const filtered = term ? clients.filter(c => (c.name || '').toLowerCase().includes(term)) : clients.slice(0, 100);
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
        const url = browseMode || q === '' ? '{{ route("api.tickets.contacts") }}?browse=1&limit=100' : '{{ route("api.tickets.contacts") }}?q=' + encodeURIComponent(q) + '&limit=60';
        renderDropdown(null, true);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data) && data.length) { clients = data; renderDropdown(data); }
            else if (!browseMode && q) renderDropdown([], false, true);
            else renderDropdown([], false, true);
        })
        .catch(() => renderFromLocal());
    }

    searchInput.addEventListener('focus', function() {
        if (this.readOnly) return;
        if (justSelected) { justSelected = false; return; }
        if (contactIdInput.value) return;
        clearTimeout(fetchTimer);
        const q = this.value.trim();
        if (q.length >= 1) fetchTimer = setTimeout(() => fetchContacts(false), 100);
        else fetchContacts(true);
    });
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(fetchTimer);
        setSelectedMode(false);
        contactIdInput.value = '';
        const policyInput = document.getElementById('policy_number') || document.querySelector('input[name="policy_number"]');
        if (policyInput) policyInput.value = '';
        if (q.length >= 1) fetchTimer = setTimeout(() => fetchContacts(false), 200);
        else { clients = initialClients.slice(); renderFromLocal(); }
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
        const policyInput = document.getElementById('policy_number') || document.querySelector('input[name="policy_number"]');
        if (policyInput && (!policyInput.value || !policyInput.value.trim())) {
            fetch('{{ route("api.tickets.contact.policy", ["contact" => ":id"]) }}'.replace(':id', oldId), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json()).then(d => { policyInput.value = (d.policy_number || d.policy || '').trim(); }).catch(() => {});
        }
    }

    // Toggle custom message field visibility based on send-email checkbox
    const sendEmailCb = document.getElementById('send_email_to_client');
    const emailOptions = document.getElementById('client-email-options');
    if (sendEmailCb && emailOptions) {
        function toggleEmailOptions() {
            emailOptions.style.opacity = sendEmailCb.checked ? '1' : '0.5';
            emailOptions.style.pointerEvents = sendEmailCb.checked ? 'auto' : 'none';
        }
        sendEmailCb.addEventListener('change', toggleEmailOptions);
        toggleEmailOptions();
    }
})();
</script>

@endsection
