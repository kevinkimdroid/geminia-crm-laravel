@extends('layouts.app')

@section('title', 'SMS Notifier')

@section('content')
<div class="page-header sms-page-header d-flex flex-wrap align-items-center gap-4 mb-4">
    <div class="sms-header-icon">
        <i class="bi bi-chat-dots-fill"></i>
    </div>
    <div>
        <h1 class="page-title mb-1">SMS Notifier</h1>
        <p class="page-subtitle mb-0">Send bulk SMS to clients via Advanta API. Numbers auto-format to Kenya (254).</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show sms-alert" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show sms-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show sms-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (!($smsConfigured ?? false))
    <div class="card sms-config-card mb-4">
        <div class="card-body p-4 d-flex align-items-start gap-4">
            <div class="sms-config-icon"><i class="bi bi-gear-fill"></i></div>
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-2">Advanta SMS not configured</h6>
                <p class="text-muted small mb-2">Add these to your <code>.env</code> file to enable SMS sending:</p>
                <pre class="sms-config-pre mb-3">ADVANTA_API_KEY=your_api_key
ADVANTA_PARTNER_ID=your_partner_id
ADVANTA_SHORTCODE=your_shortcode</pre>
                <a href="https://www.advantasms.com" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Get credentials <i class="bi bi-box-arrow-up-right ms-1"></i></a>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card sms-send-card">
            <div class="card-header sms-card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-send-fill me-2"></i>Compose & Send</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('support.sms-notifier.send') }}" id="smsForm">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-uppercase small" style="letter-spacing: 0.06em; color: var(--primary);">Recipients</label>
                        @if($presetContact ?? null)
                        @php $phone = trim($presetContact->mobile ?? $presetContact->phone ?? ''); @endphp
                        @if ($phone !== '')
                        <div class="d-flex align-items-center gap-2 p-3 rounded-2 bg-light border" style="border-color: var(--card-border)!important">
                            <i class="bi bi-person-fill text-primary"></i>
                            <div>
                                <span class="fw-semibold">{{ trim(($presetContact->firstname ?? '') . ' ' . ($presetContact->lastname ?? '')) ?: 'Client' }}</span>
                                <span class="text-muted ms-2 font-monospace">{{ $phone }}</span>
                            </div>
                            <input type="hidden" name="recipients[]" value="{{ $phone }}">
                            <a href="{{ route('support.sms-notifier') }}" class="btn btn-sm btn-outline-secondary ms-auto">Change recipient</a>
                        </div>
                        <p class="small text-muted mt-1 mb-0">Sending to this client only.</p>
                        @else
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>This client has no phone number. <a href="{{ route('contacts.edit', $presetContact->contactid ?? 0) }}">Add one</a> or <a href="{{ route('support.sms-notifier') }}">choose another recipient</a>.
                        </div>
                        @endif
                        @else
                        <div class="position-relative mb-3">
                            <div class="input-group">
                                <span class="input-group-text sms-input-prefix"><i class="bi bi-search"></i></span>
                                <input type="text" id="quickPhone" class="form-control" placeholder="Type name or number to search, then pick from list..." autocomplete="off">
                            </div>
                            <div id="smsTypeahead" class="sms-typeahead-dropdown" style="display:none"></div>
                        </div>
                        <div class="sms-recipients-box">
                            @forelse ($customers ?? [] as $c)
                                @php $p = trim($c->mobile ?? $c->phone ?? ''); @endphp
                                @if ($p !== '')
                                <label class="sms-recipient-item" for="r{{ $c->contactid }}" data-phone="{{ preg_replace('/\D/', '', $p) }}" data-name="{{ strtolower(trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? ''))) }}">
                                    <input class="form-check-input recipient-check" type="checkbox" name="recipients[]" value="{{ $p }}" id="r{{ $c->contactid }}">
                                    <span class="sms-recipient-name">{{ trim($c->firstname ?? '') }} {{ trim($c->lastname ?? '') }}</span>
                                    <span class="sms-recipient-phone">{{ $p }}</span>
                                </label>
                                @endif
                            @empty
                                <div class="sms-recipients-empty">
                                    <i class="bi bi-people text-muted mb-2"></i>
                                    <p class="mb-0 small text-muted">No clients with phone numbers.</p>
                                    <a href="{{ route('contacts.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Add contacts</a>
                                </div>
                            @endforelse
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNone">Clear</button>
                            <span class="ms-auto align-self-center small text-muted" id="recipientCount">0 selected</span>
                        </div>
                        @endif
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-uppercase small" style="letter-spacing: 0.06em; color: var(--primary);">Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control sms-textarea" rows="5" required maxlength="1600" placeholder="Type your message here...">{{ old('message') }}</textarea>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted"><span id="charCount">0</span>/1600 chars · <span id="segmentCount">1</span> segment(s)</small>
                        </div>
                    </div>
                    @php
                        $canSend = ($smsConfigured ?? false) && (!($presetContact ?? null) || trim($presetContact->mobile ?? $presetContact->phone ?? '') !== '');
                    @endphp
                    <button type="submit" class="btn btn-primary-custom btn-lg px-4" {{ !$canSend ? 'disabled' : '' }}>
                        <i class="bi bi-send-fill me-2"></i>Send SMS
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card sms-sidebar-card h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="sms-sidebar-icon"><i class="bi bi-info-circle-fill"></i></div>
                    <h6 class="mb-0 fw-semibold">How it works</h6>
                </div>
                <ul class="sms-tips-list">
                    <li><i class="bi bi-check2 text-success me-2"></i>Numbers auto-convert to 254 format</li>
                    <li><i class="bi bi-check2 text-success me-2"></i>160 chars = 1 SMS segment</li>
                    <li><i class="bi bi-check2 text-success me-2"></i>Powered by Advanta bulk API</li>
                </ul>
                <hr class="my-4">
                <a href="{{ route('support.customers') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-people me-2"></i>View Clients
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.sms-page-header { margin-bottom: 1.5rem !important; }
.sms-header-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.75rem;
    box-shadow: 0 8px 24px rgba(14, 67, 133, 0.25);
}
.sms-alert { border-radius: 12px; border: none; }
.sms-config-card { border-left: 4px solid var(--warning); }
.sms-config-icon { width: 48px; height: 48px; background: rgba(217, 119, 6, 0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--warning); font-size: 1.5rem; flex-shrink: 0; }
.sms-config-pre { background: #1e293b; color: #e2e8f0; padding: 1rem 1.25rem; border-radius: 12px; font-size: 0.8rem; margin: 0; }
.sms-send-card .card-header { background: transparent; border-bottom: 1px solid var(--card-border); padding: 1rem 1.5rem; }
.sms-card-header { font-size: 1rem; }
.sms-input-prefix { background: var(--primary-muted); border-color: var(--card-border); color: var(--primary); border-radius: 12px 0 0 12px; }
.input-group .form-control:last-child { border-radius: 0 12px 12px 0; }
.sms-recipients-box {
    max-height: 220px; overflow-y: auto;
    background: var(--primary-muted, rgba(14, 67, 133, 0.04));
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 0.75rem;
}
.sms-recipient-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    transition: background .15s;
    margin-bottom: 2px;
}
.sms-recipient-item:hover { background: rgba(255,255,255,.8); }
.sms-recipient-item:last-child { margin-bottom: 0; }
.sms-recipient-name { font-weight: 500; color: var(--text); flex: 1; }
.sms-recipient-phone { font-size: 0.85rem; color: var(--text-muted); font-family: ui-monospace, monospace; }
.sms-recipients-empty { text-align: center; padding: 2rem 1rem; }
.sms-textarea { border-radius: 12px; resize: vertical; min-height: 120px; }
.sms-sidebar-card { border-left: 3px solid var(--primary); }
.sms-sidebar-icon { width: 40px; height: 40px; background: var(--primary-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.2rem; }
.sms-tips-list { list-style: none; padding: 0; margin: 0; }
.sms-tips-list li { padding: 0.35rem 0; font-size: 0.9rem; color: var(--text-muted); }
.sms-typeahead-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; margin-top: 4px;
    background: #fff; border: 1px solid var(--card-border); border-radius: 12px;
    box-shadow: 0 10px 40px rgba(14, 67, 133, 0.15);
    max-height: 240px; overflow-y: auto; z-index: 100;
}
.sms-typeahead-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.6rem 1rem; cursor: pointer;
    border-bottom: 1px solid rgba(14, 67, 133, 0.06);
    transition: background .15s;
}
.sms-typeahead-item:last-child { border-bottom: none; }
.sms-typeahead-item:hover { background: var(--primary-muted); }
.sms-typeahead-item .name { font-weight: 500; color: var(--text); }
.sms-typeahead-item .phone { font-size: 0.85rem; color: var(--text-muted); font-family: ui-monospace, monospace; }
.sms-typeahead-empty { padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
.sms-typeahead-hint { padding: 0.5rem 1rem; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid rgba(14, 67, 133, 0.06); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const t = document.querySelector('textarea[name="message"]');
    const charCount = document.getElementById('charCount');
    const segmentCount = document.getElementById('segmentCount');
    const recipientCount = document.getElementById('recipientCount');
    const checks = document.querySelectorAll('.recipient-check');

    function updateSegmentCount(len) {
        if (!segmentCount) return;
        segmentCount.textContent = len <= 160 ? 1 : Math.ceil(len / 153);
    }
    function updateRecipientCount() {
        if (!recipientCount) return;
        const n = document.querySelectorAll('.recipient-check:checked').length + (document.querySelectorAll('input[name="recipients[]"][type="hidden"]')?.length || 0);
        recipientCount.textContent = n + ' selected';
    }

    if (t && charCount) {
        t.addEventListener('input', function() {
            const len = t.value.length;
            charCount.textContent = len;
            updateSegmentCount(len);
        });
        charCount.textContent = t.value.length;
        updateSegmentCount(t.value.length);
    }
    document.getElementById('selectAll')?.addEventListener('click', function() {
        checks.forEach(c => c.checked = true);
        updateRecipientCount();
    });
    document.getElementById('selectNone')?.addEventListener('click', function() {
        checks.forEach(c => c.checked = false);
        document.querySelectorAll('input[name="recipients[]"][type="hidden"]').forEach(i => i.remove());
        updateRecipientCount();
    });
    checks.forEach(c => c.addEventListener('change', updateRecipientCount));
    updateRecipientCount();

    const quick = document.getElementById('quickPhone');
    const form = document.getElementById('smsForm');
    const typeahead = document.getElementById('smsTypeahead');
    const recipientItems = document.querySelectorAll('.sms-recipient-item');

    function showTypeahead(query) {
        const q = (query || '').trim().toLowerCase();
        const digits = q.replace(/\D/g, '');
        if (q.length < 1) {
            typeahead.style.display = 'none';
            return;
        }
        const matches = [];
        recipientItems.forEach(function(item) {
            const phone = (item.dataset.phone || '').replace(/\D/g, '');
            const name = (item.dataset.name || '');
            const match = (digits && phone.indexOf(digits) !== -1) || (q && name.indexOf(q) !== -1);
            if (match) matches.push(item);
        });
        if (matches.length === 0) {
            typeahead.innerHTML = '<div class="sms-typeahead-empty">No matching contacts. Paste numbers (comma-separated) and send.</div>';
        } else {
            typeahead.innerHTML = matches.slice(0, 10).map(function(item) {
                const cb = item.querySelector('.recipient-check');
                const checkId = cb ? cb.id : '';
                const phone = cb ? cb.value : '';
                const name = (item.querySelector('.sms-recipient-name') || {}).textContent || '';
                const esc = function(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); };
                return '<div class="sms-typeahead-item" data-check-id="' + esc(checkId) + '">' +
                    '<span class="name">' + esc(name) + '</span>' +
                    '<span class="phone">' + esc(phone) + '</span></div>';
            }).join('') + (matches.length > 10 ? '<div class="sms-typeahead-hint">+ ' + (matches.length - 10) + ' more. Type to narrow.</div>' : '<div class="sms-typeahead-hint">Click to add</div>');
            typeahead.querySelectorAll('.sms-typeahead-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    const cb = document.getElementById(el.dataset.checkId);
                    if (cb) {
                        cb.checked = true;
                        updateRecipientCount();
                    }
                    quick.value = '';
                    typeahead.style.display = 'none';
                    quick.focus();
                });
            });
        }
        typeahead.style.display = 'block';
    }

    if (quick && typeahead) {
        quick.addEventListener('input', function() { showTypeahead(quick.value); });
        quick.addEventListener('focus', function() { if (quick.value.trim()) showTypeahead(quick.value); });
        quick.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { typeahead.style.display = 'none'; quick.blur(); }
        });
        document.addEventListener('click', function(e) {
            if (!quick.contains(e.target) && !typeahead.contains(e.target)) typeahead.style.display = 'none';
        });
    }

    if (quick && form) {
        form.addEventListener('submit', function(e) {
            const val = quick.value.trim();
            if (val) {
                const phones = val.split(/[,;\s]+/).map(function(p) { return p.trim(); }).filter(Boolean);
                checks.forEach(function(c) { c.checked = false; });
                form.querySelectorAll('input[name="recipients[]"][type="hidden"]').forEach(function(i) { i.remove(); });
                phones.forEach(function(phone) {
                    const existing = document.querySelector('.recipient-check[value="' + phone.replace(/"/g, '&quot;') + '"]');
                    if (existing) existing.checked = true;
                    else {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'recipients[]';
                        input.value = phone;
                        form.appendChild(input);
                    }
                });
                updateRecipientCount();
            }
        });
    }
});
</script>
@endsection
