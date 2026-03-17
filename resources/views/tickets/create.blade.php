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
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="contactSearch" class="form-control" placeholder="{{ ($presetContactId ?? null) && ($fromServeClient ?? false) ? 'Client selected' : 'Type to search...' }}" autocomplete="off" value="{{ old('contact_display', $presetContactDisplay ?? '') }}" {{ ($presetContactId ?? null) && ($fromServeClient ?? false) ? 'readonly' : '' }} aria-autocomplete="list" aria-expanded="false" aria-controls="contactDropdown" role="combobox">
                        <input type="hidden" name="contact_id" id="contactId" value="{{ old('contact_id', $presetContactId ?? '') }}" required>
                        @if(($presetContactId ?? null) && ($fromServeClient ?? false))
                        <a href="{{ route('support.serve-client') }}" class="btn btn-outline-secondary" title="Change client">Change</a>
                        @else
                        <button type="button" id="contactClear" class="btn btn-outline-secondary d-none" title="Clear selection"><i class="bi bi-x-lg"></i></button>
                        <a href="{{ route('contacts.create') }}" class="btn btn-outline-secondary" title="Add new client"><i class="bi bi-plus-lg"></i></a>
                        @endif
                    </div>
                    <div id="contactDropdown" class="list-group position-absolute w-100 mt-1 shadow border rounded-2" style="max-height: 220px; overflow-y: auto; display: none; z-index: 1000; border-color:var(--geminia-border)!important;" role="listbox"></div>
                    @error('contact_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">Other</option>
                        @foreach(config('tickets.categories') as $cat)
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
    <input type="hidden" name="ticket_source" value="{{ ($fromServeClient ?? false) ? 'Phone' : (($fromMailManager ?? false) ? 'Email' : 'CRM') }}">

    {{-- More options: Product Line, Policy, Severity (always visible) --}}
    <div class="app-card mb-4">
        <div class="p-4">
            <p class="small fw-semibold text-muted mb-3">Product Line, Policy, Severity</p>
            <div class="row g-4">
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
                    <label class="form-label fw-semibold">Policy Number</label>
                    <input type="text" name="policy_number" class="form-control font-monospace" value="{{ old('policy_number', $presetPolicy ?? '') }}" placeholder="e.g. GEMPPP0334">
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

<script id="clientsData" type="application/json">@json(collect($clients ?? [])->map(fn($c) => ['id' => $c->contactid, 'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Client #' . $c->contactid])->values())</script>
<script>
(function() {
    const initialClients = JSON.parse(document.getElementById('clientsData').textContent || '[]');
    let clients = initialClients.slice();
    const searchInput = document.getElementById('contactSearch');
    const contactIdInput = document.getElementById('contactId');
    const dropdown = document.getElementById('contactDropdown');
    const clearBtn = document.getElementById('contactClear');
    let fetchTimer;
    let highlightedIdx = -1;
    let currentItems = [];

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function showClearBtn(show) {
        if (clearBtn) clearBtn.classList.toggle('d-none', !show);
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
            dropdown.innerHTML = currentItems.slice(0, 20).map((c, i) =>
                `<a href="#" class="list-group-item list-group-item-action" data-id="${c.id}" data-name="${escapeHtml(c.name)}" role="option" data-index="${i}">${escapeHtml(c.name)}</a>`
            ).join('');
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
        searchInput.setAttribute('aria-expanded', dropdown.style.display === 'block');
    }
    function renderFromLocal() {
        const term = (searchInput.value || '').trim().toLowerCase();
        const filtered = term ? clients.filter(c => (c.name || '').toLowerCase().includes(term)) : clients.slice(0, 50);
        renderDropdown(filtered);
    }
    function selectItem(item) {
        if (!item) return;
        contactIdInput.value = item.dataset.id;
        searchInput.value = item.dataset.name;
        dropdown.style.display = 'none';
        searchInput.setAttribute('aria-expanded', 'false');
        showClearBtn(true);
    }
    function fetchContacts() {
        const q = searchInput.value.trim();
        if (q.length < 2) {
            renderFromLocal();
            return;
        }
        renderDropdown(null, true);
        fetch('{{ route("api.tickets.contacts") }}?q=' + encodeURIComponent(q) + '&limit=30', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data) && data.length) renderDropdown(data);
            else renderDropdown([], false, true);
        })
        .catch(() => renderFromLocal());
    }

    searchInput.addEventListener('focus', function() {
        if (this.readOnly) return;
        const q = this.value.trim();
        if (q.length >= 1) {
            clearTimeout(fetchTimer);
            fetchTimer = setTimeout(fetchContacts, 150);
        } else {
            renderFromLocal();
        }
    });
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(fetchTimer);
        showClearBtn(false);
        contactIdInput.value = '';
        if (q.length >= 1) {
            fetchTimer = setTimeout(fetchContacts, 250);
        } else {
            renderFromLocal();
        }
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
            showClearBtn(true);
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            contactIdInput.value = '';
            searchInput.value = '';
            searchInput.focus();
            showClearBtn(false);
            renderFromLocal();
        });
    }

    const oldId = contactIdInput.value;
    if (oldId) {
        const c = clients.find(x => String(x.id) === String(oldId));
        if (c) searchInput.value = c.name;
        showClearBtn(true);
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
