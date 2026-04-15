@extends('layouts.app')

@section('title', 'Email & SMS broadcast')

@section('content')
<div class="page-header mb-4">
    <h1 class="page-title">Email & SMS broadcast</h1>
    <p class="page-subtitle mb-0">Send plain-text email or SMS to selected contacts (uses Microsoft Graph / SMTP and Advanta SMS, same as elsewhere).</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (!empty($broadcastLifeSegmentNeedsErp))
    <div class="alert alert-warning">
        Life segments (Group Life, Individual Life, etc.) match <strong>Support → Clients</strong> and require an ERP-backed client list. Set <code>CLIENTS_VIEW_SOURCE</code> to <code>erp_http</code>, <code>erp_sync</code>, or <code>erp</code> (with credentials) in <code>.env</code>, then reload this page.
    </div>
@endif
@if (empty($broadcastHistoryReady))
    <div class="alert alert-info mb-3">
        Run <code>php artisan migrate</code> to enable <strong>broadcast send history</strong> (last email/SMS and duplicate protection).
    </div>
@endif

@php
    $hasListFilters = ($search ?? '') !== '' || ($clientType ?? 'all') !== 'all'
        || !empty($hideListEmailRecent) || !empty($hideListSmsRecent);
@endphp

<form method="GET" action="{{ route('marketing.broadcast') }}" class="mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small text-muted">Search contacts</label>
            <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="Name, email, or phone">
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted">Client segment / CRM source</label>
            <select name="client_type" class="form-select">
                <option value="all" {{ ($clientType ?? 'all') === 'all' ? 'selected' : '' }}>All contacts</option>
                @if (!empty($broadcastUsesErpClients) && !empty($lifeSystemOptions))
                    <optgroup label="Support → Clients (same as pills)">
                        @foreach ($lifeSystemOptions as $opt)
                            <option value="{{ $opt['value'] }}" {{ ($clientType ?? '') === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @foreach ($recordSources ?? [] as $src)
                    @php $sv = 's|' . $src; @endphp
                    <option value="{{ $sv }}" {{ ($clientType ?? '') === $sv ? 'selected' : '' }}>Record source: {{ $src }}</option>
                @endforeach
                @foreach ($contactTypeValues ?? [] as $tv)
                    @php $tvv = 't|' . $tv; @endphp
                    <option value="{{ $tvv }}" {{ ($clientType ?? '') === $tvv ? 'selected' : '' }}>Vtiger field: {{ $tv }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted d-block">&nbsp;</label>
            <button type="submit" class="btn btn-primary-custom">Apply</button>
        </div>
        @if ($hasListFilters)
            <div class="col-auto">
                <label class="form-label small text-muted d-block">&nbsp;</label>
                <a href="{{ route('marketing.broadcast') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        @endif
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <label class="form-label small text-muted mb-1">Avoid duplicates (list)</label>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="hide_list_email_recent" value="1" id="hideListEmailRecent"
                    @checked(!empty($hideListEmailRecent)) @disabled(empty($broadcastHistoryReady))>
                <label class="form-check-label" for="hideListEmailRecent">Hide contacts who already got a <strong>mass email</strong> in the last {{ (int) ($skipRecentDays ?? 14) }} days</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="hide_list_sms_recent" value="1" id="hideListSmsRecent"
                    @checked(!empty($hideListSmsRecent)) @disabled(empty($broadcastHistoryReady))>
                <label class="form-check-label" for="hideListSmsRecent">Hide contacts who already got a <strong>mass SMS</strong> in the last {{ (int) ($skipRecentDays ?? 14) }} days</label>
            </div>
        </div>
    </div>
</form>

@if (empty($contactTypeCf) && count($contactTypeValues ?? []) === 0)
    <p class="small text-muted mb-3">Optional: set <code>BROADCAST_CONTACT_TYPE_CF</code> in <code>.env</code> (e.g. your Vtiger Contacts custom field <code>cf_912</code>) to filter by client type picklist values.</p>
@endif

<p class="text-muted small">Showing up to <strong>{{ $customers->count() }}</strong> contacts (max {{ $maxRecipients ?? 500 }} per send). Select below and/or upload an Excel/CSV list.</p>

<form method="POST" action="{{ route('marketing.broadcast.send') }}" id="broadcastForm" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="search" value="{{ $search ?? '' }}">
    @if (!empty($hideListEmailRecent))
        <input type="hidden" name="hide_list_email_recent" value="1">
    @endif
    @if (!empty($hideListSmsRecent))
        <input type="hidden" name="hide_list_sms_recent" value="1">
    @endif
    <div class="mb-3">
        <label class="form-label small text-muted">Apply client type filter to send</label>
        <select name="client_type" class="form-select" style="max-width:28rem">
            <option value="all" {{ old('client_type', $clientType ?? 'all') === 'all' ? 'selected' : '' }}>All (no extra filter)</option>
            @if (!empty($broadcastUsesErpClients) && !empty($lifeSystemOptions))
                <optgroup label="Support → Clients (same as pills)">
                    @foreach ($lifeSystemOptions as $opt)
                        <option value="{{ $opt['value'] }}" {{ old('client_type', $clientType ?? '') === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                    @endforeach
                </optgroup>
            @endif
            @foreach ($recordSources ?? [] as $src)
                @php $sv = 's|' . $src; @endphp
                <option value="{{ $sv }}" {{ old('client_type', $clientType ?? '') === $sv ? 'selected' : '' }}>Record source: {{ $src }}</option>
            @endforeach
            @foreach ($contactTypeValues ?? [] as $tv)
                @php $tvv = 't|' . $tv; @endphp
                <option value="{{ $tvv }}" {{ old('client_type', $clientType ?? '') === $tvv ? 'selected' : '' }}>Vtiger field: {{ $tv }}</option>
            @endforeach
        </select>
        <small class="text-muted d-block mt-1">Recipients from the table and from your file must match this filter or they are skipped.</small>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-email" data-bs-toggle="tab" data-bs-target="#pane-email" type="button" role="tab" data-channel="email">
                <i class="bi bi-envelope me-1"></i> Mass email
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-sms" data-bs-toggle="tab" data-bs-target="#pane-sms" type="button" role="tab" data-channel="sms">
                <i class="bi bi-chat-dots me-1"></i> Mass SMS
            </button>
        </li>
    </ul>
    <input type="hidden" name="channel" id="broadcastChannel" value="email">

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-email" role="tabpanel">
            <div class="card p-4 mb-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" maxlength="200" placeholder="e.g. Update from Geminia Life">
                        @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="8" placeholder="Plain text only. Placeholders: @{{first_name}}, @{{last_name}}, @{{name}}, @{{email}}">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-sms" role="tabpanel">
            <div class="card p-4 mb-4">
                <label class="form-label">SMS text <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="5" maxlength="1600" placeholder="Max 1600 characters; long messages may split into multiple SMS segments.">{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <p class="small text-muted mt-2 mb-0">Uses Advanta (same as <a href="{{ route('support.sms-notifier') }}">SMS Notifier</a>). Numbers are normalized to 254…</p>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <label class="form-label fw-semibold">Upload recipient list (optional)</label>
            <input type="file" name="recipients_file" class="form-control" accept=".xlsx,.xls,.csv,.txt">
            <small class="text-muted d-block mt-2">
                Excel or CSV: first row = headers. Recognised columns: <strong>Contact ID</strong> (or contactid), <strong>Email</strong>, <strong>Policy</strong> / policy number, <strong>Mobile</strong> or <strong>Phone</strong>.
                Up to {{ $excelMaxRows ?? 5000 }} rows. Merged with any rows you tick in the table.
            </small>
            @error('recipients_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="card mb-4 border-secondary">
        <div class="card-body py-3">
            <input type="hidden" name="skip_recent_sends" value="0">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" name="skip_recent_sends" id="skipRecentSends" value="1"
                    @checked((string) old('skip_recent_sends', '1') !== '0')
                    @disabled(empty($broadcastHistoryReady))>
                <label class="form-check-label" for="skipRecentSends">
                    <strong>Skip duplicate sends</strong> — do not message contacts who already received a mass <span id="skipChannelLabel">email</span> in the last {{ (int) ($skipRecentDays ?? 14) }} days (based on send history).
                </label>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center gap-2 justify-content-between">
            <span class="fw-semibold">Recipients</span>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bcSelectAllEmail">Select all (with email)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bcSelectAllSms">Select all (with phone)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bcSelectNone">Clear</button>
                <span class="small text-muted align-self-center ms-1"><span id="bcCount">0</span> selected</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="sticky-top bg-light">
                        <tr>
                            <th style="width:40px"></th>
                            <th>Name</th>
                            <th>Intermediary (Agent)</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="text-nowrap">Last mass email</th>
                            <th class="text-nowrap">Last mass SMS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers ?? [] as $c)
                            @php
                                $cid = (int) $c->contactid;
                                $em = trim($c->email ?? '');
                                $ph = trim($c->mobile ?? $c->phone ?? '');
                                $hasEm = $em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL);
                                $hasPh = $ph !== '';
                                $interm = trim((string) ($c->intermediary ?? ''));
                                if ($interm !== '') {
                                    $agentLabel = \Illuminate\Support\Str::limit($interm, 25);
                                    $prep = trim((string) ($c->pol_prepared_by ?? ''));
                                    $agentTitle = $prep !== ''
                                        ? 'Intermediary: '.$interm.' · Prepared by: '.$prep
                                        : $interm;
                                } else {
                                    $own = trim(($c->owner_first ?? '').' '.($c->owner_last ?? ''));
                                    $agentLabel = $own !== '' ? $own : (trim((string) ($c->owner_username ?? '')) ?: '—');
                                    $agentTitle = $agentLabel !== '—' ? 'CRM assigned to' : '';
                                }
                                $lb = $lastBroadcastByContact[$cid] ?? ['email' => null, 'sms' => null];
                            @endphp
                            <tr class="bc-row" data-has-email="{{ $hasEm ? '1' : '0' }}" data-has-phone="{{ $hasPh ? '1' : '0' }}">
                                <td>
                                    <input type="checkbox" class="form-check-input bc-check" name="contact_ids[]" value="{{ $c->contactid }}"
                                        data-has-email="{{ $hasEm ? '1' : '0' }}" data-has-phone="{{ $hasPh ? '1' : '0' }}">
                                </td>
                                <td>{{ trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) }}</td>
                                <td class="small" @if ($agentTitle !== '') title="{{ $agentTitle }}" @endif>{{ $agentLabel }}</td>
                                <td><span class="{{ $hasEm ? '' : 'text-muted' }}">{{ $em !== '' ? $em : '—' }}</span></td>
                                <td><span class="{{ $hasPh ? '' : 'text-muted' }}">{{ $ph !== '' ? $ph : '—' }}</span></td>
                                <td class="small">
                                    @if (!empty($broadcastHistoryReady) && !empty($lb['email']))
                                        <span class="text-success" title="{{ $lb['email']->format('Y-m-d H:i') }}">{{ $lb['email']->diffForHumans() }}</span>
                                    @elseif (!empty($broadcastHistoryReady))
                                        <span class="text-muted">—</span>
                                    @else
                                        <span class="text-muted">n/a</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if (!empty($broadcastHistoryReady) && !empty($lb['sms']))
                                        <span class="text-success" title="{{ $lb['sms']->format('Y-m-d H:i') }}">{{ $lb['sms']->diffForHumans() }}</span>
                                    @elseif (!empty($broadcastHistoryReady))
                                        <span class="text-muted">—</span>
                                    @else
                                        <span class="text-muted">n/a</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">
                                @if (!empty($broadcastLifeSegmentNeedsErp))
                                    Enable an ERP-backed Clients source to use life-group filters, or choose &quot;All contacts&quot;.
                                @else
                                    No contacts match your search or no Vtiger match was found for policies in this segment.
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary-custom btn-lg" id="bcSubmit">
        <i class="bi bi-send-fill me-1"></i> Send broadcast
    </button>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var channelInput = document.getElementById('broadcastChannel');
    var tabEmail = document.getElementById('tab-email');
    var tabSms = document.getElementById('tab-sms');
    var checks = document.querySelectorAll('.bc-check');
    var countEl = document.getElementById('bcCount');

    function setChannel(ch) {
        if (channelInput) channelInput.value = ch;
    }
    tabEmail && tabEmail.addEventListener('shown.bs.tab', function() { setChannel('email'); });
    tabSms && tabSms.addEventListener('shown.bs.tab', function() { setChannel('sms'); });
    tabEmail && tabEmail.addEventListener('click', function() { setChannel('email'); });
    tabSms && tabSms.addEventListener('click', function() { setChannel('sms'); });

    var skipChEl = document.getElementById('skipChannelLabel');
    function syncSkipLabel(ch) {
        if (skipChEl) skipChEl.textContent = ch === 'sms' ? 'SMS' : 'email';
    }
    tabEmail && tabEmail.addEventListener('shown.bs.tab', function() { syncSkipLabel('email'); });
    tabSms && tabSms.addEventListener('shown.bs.tab', function() { syncSkipLabel('sms'); });
    tabEmail && tabEmail.addEventListener('click', function() { syncSkipLabel('email'); });
    tabSms && tabSms.addEventListener('click', function() { syncSkipLabel('sms'); });
    syncSkipLabel(channelInput && channelInput.value === 'sms' ? 'sms' : 'email');

    function updateCount() {
        if (!countEl) return;
        var n = document.querySelectorAll('.bc-check:checked').length;
        countEl.textContent = n;
    }

    document.getElementById('bcSelectAllEmail')?.addEventListener('click', function() {
        checks.forEach(function(cb) {
            cb.checked = cb.getAttribute('data-has-email') === '1';
        });
        updateCount();
    });
    document.getElementById('bcSelectAllSms')?.addEventListener('click', function() {
        checks.forEach(function(cb) {
            cb.checked = cb.getAttribute('data-has-phone') === '1';
        });
        updateCount();
    });
    document.getElementById('bcSelectNone')?.addEventListener('click', function() {
        checks.forEach(function(cb) { cb.checked = false; });
        updateCount();
    });
    checks.forEach(function(cb) { cb.addEventListener('change', updateCount); });
    updateCount();

    document.getElementById('broadcastForm')?.addEventListener('submit', function(e) {
        var n = document.querySelectorAll('.bc-check:checked').length;
        var fileInput = document.querySelector('input[name="recipients_file"]');
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (n < 1 && !hasFile) {
            e.preventDefault();
            alert('Select at least one contact or upload a recipient file.');
            return false;
        }
        var ch = channelInput ? channelInput.value : 'email';
        var targetDesc = n > 0 ? n + ' selected contact(s)' : 'recipients from your file';
        if (!confirm('Send ' + ch.toUpperCase() + ' to ' + targetDesc + '?')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
@endpush
@endsection
