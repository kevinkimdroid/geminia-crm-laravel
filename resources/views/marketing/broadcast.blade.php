@extends('layouts.app')

@section('title', 'Email & SMS broadcast')

@section('content')
<div class="page-header mb-4">
    <h1 class="page-title">Email & SMS broadcast</h1>
    <p class="page-subtitle mb-0">Send plain-text email or SMS to selected contacts (Microsoft Graph / SMTP and Advanta SMS). Reusable ad copy lives in <a href="{{ route('tools.email-templates') }}">Tools → Email templates</a> (modules <strong>Broadcast</strong>, <strong>Marketing</strong>, or <strong>Broadcast SMS</strong> for texts).</p>
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
            <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="Name, policy number, email, or phone">
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
    <p class="small text-muted mb-3">You can also filter contacts by client type. If this filter is missing, ask your system administrator to enable it.</p>
@endif

<p class="text-muted small">
    Showing up to <strong>{{ $customers->count() }}</strong> unique contacts (max {{ $maxRecipients ?? 500 }} per send). Select below and/or upload an Excel/CSV list.
    @if (!empty($duplicatesCollapsed))
        <span class="d-block mt-1">Merged <strong>{{ (int) $duplicatesCollapsed }}</strong> duplicate row(s) that had the same name and matching email/phone.</span>
    @endif
</p>

<div class="alert alert-info py-2 px-3 small">
    <strong>Bulk send guide:</strong> For large campaigns (e.g. 700 clients), use <strong>Select all (with email)</strong> or upload a file, pick a saved template (or the quick pension snippet), attach your circular, and send.
    If you exceed the current max ({{ $maxRecipients ?? 500 }}), split into batches and keep <strong>Skip duplicate sends</strong> enabled.
</div>

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
            <div class="card bc-composer-card border-0 shadow-sm mb-4 overflow-hidden">
                <div class="row g-0">
                    <div class="col-lg-4 bc-tpl-sidebar text-white p-4 d-flex flex-column">
                        <div class="mb-2">
                            <span class="text-uppercase small fw-bold opacity-75">Template library</span>
                            <h2 class="h6 text-white mb-0 mt-1">Saved advertisement copy</h2>
                        </div>
                            <p class="small opacity-90 mb-3 mb-lg-4">Choose a row from <strong>Email templates</strong> (modules Broadcast or Marketing), then apply it. Tokens like <code class="text-white text-opacity-75">@{{firstname}}</code> are filled per recipient when you send.</p>
                        @php $emailTplList = $emailAdvertTemplates ?? collect(); @endphp
                        @if ($emailTplList->isEmpty())
                            <div class="rounded-3 small mb-3 p-3" style="background: rgba(255,255,255,.12);">No templates yet. Add some under Tools with module <strong>Broadcast</strong> or <strong>Marketing</strong>.</div>
                        @endif
                        <label class="form-label small mb-1 opacity-75" for="bcEmailTemplateSelect">Template</label>
                        <select id="bcEmailTemplateSelect" class="form-select form-select-sm mb-2" @disabled($emailTplList->isEmpty())>
                            <option value="">Choose…</option>
                            @foreach ($emailTplList as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->template_name }} — {{ $tpl->module_name }}</option>
                            @endforeach
                        </select>
                        <p class="small opacity-90 mb-3 flex-grow-1" id="bcEmailTemplateHint">Select a template to see its description here.</p>
                        <div class="d-grid gap-2 mt-auto">
                            <button type="button" class="btn btn-light btn-sm fw-semibold" id="bcApplyEmailTemplate" @disabled($emailTplList->isEmpty())>
                                <i class="bi bi-arrow-down-circle me-1"></i> Apply to subject &amp; message
                            </button>
                            <a href="{{ route('tools.email-templates', ['module' => 'Broadcast']) }}" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-collection me-1"></i> Manage email templates
                            </a>
                        </div>
                        <hr class="border-white opacity-25 my-3">
                        <span class="small text-uppercase opacity-75">Quick start</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-light" id="loadPensionTemplate">2025 Pension</button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="clearEmailTemplate">Clear</button>
                        </div>
                    </div>
                    <div class="col-lg-8 p-4 bg-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <span class="fw-semibold">Email content</span>
                            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary">Plain text</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="broadcastSubject" class="form-control" value="{{ old('subject') }}" maxlength="200" placeholder="e.g. Update from Geminia Life">
                                @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea name="body" id="broadcastBody" class="form-control" rows="10" placeholder="Plain text. Placeholders: @{{first_name}}, @{{firstname}}, @{{last_name}}, @{{name}}, @{{email}}">{{ old('body') }}</textarea>
                                @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Attachment (optional)</label>
                                <input type="file" name="email_attachment" id="emailAttachmentInput" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx">
                                <small class="text-muted d-block mt-1">Attached to every email recipient. Max 10MB. Allowed: PDF, Word, Excel, CSV, TXT, PowerPoint.</small>
                                @error('email_attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-sms" role="tabpanel">
            @php $smsTplList = $smsAdvertTemplates ?? collect(); @endphp
            <div class="card bc-composer-card bc-composer-sms border-0 shadow-sm mb-4 overflow-hidden">
                <div class="row g-0">
                    <div class="col-lg-4 bc-tpl-sidebar bc-tpl-sidebar-sms text-white p-4 d-flex flex-column">
                        <div class="mb-2">
                            <span class="text-uppercase small fw-bold opacity-75">SMS snippets</span>
                            <h2 class="h6 text-white mb-0 mt-1">Saved text messages</h2>
                        </div>
                        <p class="small opacity-90 mb-3 mb-lg-4">Templates whose module is <strong>Broadcast SMS</strong> load the message body only (subject is ignored here).</p>
                        @if ($smsTplList->isEmpty())
                            <div class="rounded-3 small mb-3 p-3" style="background: rgba(255,255,255,.12);">No SMS templates yet. Create one in Tools with module <strong>Broadcast SMS</strong>.</div>
                        @endif
                        <label class="form-label small mb-1 opacity-75" for="bcSmsTemplateSelect">Template</label>
                        <select id="bcSmsTemplateSelect" class="form-select form-select-sm mb-2" @disabled($smsTplList->isEmpty())>
                            <option value="">Choose…</option>
                            @foreach ($smsTplList as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->template_name }}</option>
                            @endforeach
                        </select>
                        <p class="small opacity-90 mb-3 flex-grow-1" id="bcSmsTemplateHint">Select a template to see its description here.</p>
                        <div class="d-grid gap-2 mt-auto">
                            <button type="button" class="btn btn-light btn-sm fw-semibold" id="bcApplySmsTemplate" @disabled($smsTplList->isEmpty())>
                                <i class="bi bi-arrow-down-circle me-1"></i> Apply to SMS text
                            </button>
                            <a href="{{ route('tools.email-templates', ['module' => 'Broadcast SMS']) }}" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-collection me-1"></i> Manage SMS templates
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-8 p-4 bg-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <label class="form-label fw-semibold mb-0" for="broadcastSmsMessage">SMS text <span class="text-danger">*</span></label>
                            <span class="small text-muted" id="bcSmsCharCount">0 / 1600</span>
                        </div>
                        <textarea name="message" id="broadcastSmsMessage" class="form-control" rows="8" maxlength="1600" placeholder="Max 1600 characters; long messages may split into multiple SMS segments.">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <p class="small text-muted mt-2 mb-0">Uses Advanta (same as <a href="{{ route('support.sms-notifier') }}">SMS Notifier</a>). Numbers are normalized to 254…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <label class="form-label fw-semibold mb-0">Upload recipient list (optional)</label>
                <a href="{{ route('marketing.broadcast.template') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Download Excel template
                </a>
            </div>
            <input type="file" name="recipients_file" class="form-control" accept=".xlsx,.xls,.csv,.txt">
            <small class="text-muted d-block mt-2">
                Excel or CSV: first row = headers. Recognised columns: <strong>Contact ID</strong> (or contactid), <strong>Email</strong>, <strong>Policy</strong> / policy number, <strong>Mobile</strong> or <strong>Phone</strong>.
                Up to {{ $excelMaxRows ?? 5000 }} rows. Merged with any rows you tick in the table.
            </small>
            <small class="text-muted d-block mt-1">
                Tip: if Contact ID is unknown, leave it blank and provide Email/Policy/Mobile. Invalid Contact IDs are skipped automatically.
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
                            <th>Policy</th>
                            <th>Product</th>
                            <th>System</th>
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
                                $fullName = trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? ''));
                                if ($fullName === '') {
                                    $fullName = 'Contact #' . $cid;
                                }
                                $policyNo = trim((string) ($c->policy_number ?? $c->policy_no ?? ''));
                                $product = trim((string) ($c->product ?? ''));
                                $lifeSystem = trim((string) ($c->life_system ?? ''));
                                $lifeSystemLabel = match ($lifeSystem) {
                                    'group' => 'Group Life',
                                    'individual' => 'Individual Life',
                                    'mortgage' => 'Mortgage',
                                    'group_pension' => 'Group Pension',
                                    default => '—',
                                };
                                $emCandidates = [
                                    trim((string) ($c->email ?? '')),
                                    trim((string) ($c->otheremail ?? '')),
                                    trim((string) ($c->secondaryemail ?? '')),
                                    trim((string) ($c->email_adr ?? '')),
                                    trim((string) ($c->client_email ?? '')),
                                    trim((string) ($c->mem_email ?? '')),
                                ];
                                $em = '';
                                foreach ($emCandidates as $cand) {
                                    if ($cand === '') {
                                        continue;
                                    }
                                    if (filter_var($cand, FILTER_VALIDATE_EMAIL)) {
                                        $em = $cand;
                                        break;
                                    }
                                    $parts = preg_split('/[;,\\s]+/', $cand, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                    foreach ($parts as $part) {
                                        $candidate = trim((string) $part);
                                        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                                            $em = $candidate;
                                            break 2;
                                        }
                                    }
                                }
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
                                } elseif (trim((string) ($c->pol_prepared_by ?? '')) !== '') {
                                    $prepOnly = trim((string) ($c->pol_prepared_by ?? ''));
                                    $agentLabel = \Illuminate\Support\Str::limit($prepOnly, 25);
                                    $agentTitle = 'Prepared by: ' . $prepOnly;
                                } else {
                                    // Do not fallback to CRM owner in this column; it must represent ERP intermediary.
                                    $agentLabel = '—';
                                    $agentTitle = '';
                                }
                                $lb = $lastBroadcastByContact[$cid] ?? ['email' => null, 'sms' => null];
                            @endphp
                            <tr class="bc-row" data-has-email="{{ $hasEm ? '1' : '0' }}" data-has-phone="{{ $hasPh ? '1' : '0' }}">
                                <td>
                                    <input type="checkbox" class="form-check-input bc-check" name="contact_ids[]" value="{{ $c->contactid }}"
                                        data-has-email="{{ $hasEm ? '1' : '0' }}" data-has-phone="{{ $hasPh ? '1' : '0' }}">
                                </td>
                                <td>
                                    {{ $fullName }}
                                    @if ((int) ($c->duplicate_count ?? 0) > 0)
                                        <span class="badge bg-light text-dark border ms-1" title="Duplicate rows merged in this list view">+{{ (int) $c->duplicate_count }}</span>
                                    @endif
                                </td>
                                <td class="small">{{ $policyNo !== '' ? $policyNo : '—' }}</td>
                                <td class="small">{{ $product !== '' ? $product : '—' }}</td>
                                <td class="small">{{ $lifeSystemLabel }}</td>
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
                            <tr><td colspan="10" class="text-center text-muted py-4">
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

@php
    $emailTemplatesById = [];
    foreach (($emailAdvertTemplates ?? collect()) as $tpl) {
        $emailTemplatesById[$tpl->id] = [
            'subject' => (string) ($tpl->subject ?? ''),
            'body' => (string) ($tpl->body ?? ''),
            'description' => (string) ($tpl->description ?? ''),
        ];
    }

    $smsTemplatesById = [];
    foreach (($smsAdvertTemplates ?? collect()) as $tpl) {
        $smsTemplatesById[$tpl->id] = [
            'body' => (string) ($tpl->body ?? ''),
            'description' => (string) ($tpl->description ?? ''),
        ];
    }
@endphp
<script type="application/json" id="bcEmailTemplatesById">@json($emailTemplatesById)</script>
<script type="application/json" id="bcSmsTemplatesById">@json($smsTemplatesById)</script>

<style>
.bc-composer-card { border-radius: 16px; border: 1px solid rgba(14, 67, 133, 0.1); }
.bc-tpl-sidebar {
    background: linear-gradient(165deg, #0b3569 0%, #0E4385 45%, #1560a8 100%);
}
.bc-tpl-sidebar-sms {
    background: linear-gradient(165deg, #064032 0%, #0d5c4a 50%, #13806a 100%);
}
.bc-composer-sms { border-color: rgba(13, 92, 74, 0.15) !important; }
.bc-tpl-sidebar .form-select { border: none; }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var channelInput = document.getElementById('broadcastChannel');
    var tabEmail = document.getElementById('tab-email');
    var tabSms = document.getElementById('tab-sms');
    var checks = document.querySelectorAll('.bc-check');
    var countEl = document.getElementById('bcCount');
    var attachmentInput = document.getElementById('emailAttachmentInput');
    var subjectInput = document.getElementById('broadcastSubject');
    var bodyInput = document.getElementById('broadcastBody');
    var smsMessage = document.getElementById('broadcastSmsMessage');
    var smsCharEl = document.getElementById('bcSmsCharCount');

    function parseJsonScript(id) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) return {};
        try { return JSON.parse(el.textContent); } catch (e) { return {}; }
    }
    var emailTemplatesById = parseJsonScript('bcEmailTemplatesById');
    var smsTemplatesById = parseJsonScript('bcSmsTemplatesById');

    var emailTplSelect = document.getElementById('bcEmailTemplateSelect');
    var emailTplHint = document.getElementById('bcEmailTemplateHint');
    function syncEmailTplHint() {
        if (!emailTplHint || !emailTplSelect) return;
        var id = emailTplSelect.value;
        if (!id || !emailTemplatesById[id]) {
            emailTplHint.textContent = 'Select a template to see its description here.';
            return;
        }
        var d = emailTemplatesById[id].description || '';
        emailTplHint.textContent = d || 'No description for this template.';
    }
    emailTplSelect && emailTplSelect.addEventListener('change', syncEmailTplHint);
    syncEmailTplHint();

    document.getElementById('bcApplyEmailTemplate')?.addEventListener('click', function() {
        var id = emailTplSelect && emailTplSelect.value;
        if (!id || !emailTemplatesById[id]) {
            alert('Choose a template from the list first.');
            return;
        }
        var t = emailTemplatesById[id];
        if (subjectInput) subjectInput.value = t.subject || '';
        if (bodyInput) bodyInput.value = t.body || '';
        if (bodyInput) bodyInput.focus();
    });

    var smsTplSelect = document.getElementById('bcSmsTemplateSelect');
    var smsTplHint = document.getElementById('bcSmsTemplateHint');
    function syncSmsTplHint() {
        if (!smsTplHint || !smsTplSelect) return;
        var id = smsTplSelect.value;
        if (!id || !smsTemplatesById[id]) {
            smsTplHint.textContent = 'Select a template to see its description here.';
            return;
        }
        var d = smsTemplatesById[id].description || '';
        smsTplHint.textContent = d || 'No description for this template.';
    }
    smsTplSelect && smsTplSelect.addEventListener('change', syncSmsTplHint);
    syncSmsTplHint();

    document.getElementById('bcApplySmsTemplate')?.addEventListener('click', function() {
        var id = smsTplSelect && smsTplSelect.value;
        if (!id || !smsTemplatesById[id]) {
            alert('Choose a template from the list first.');
            return;
        }
        var t = smsTemplatesById[id];
        if (smsMessage) smsMessage.value = t.body || '';
        if (smsMessage) smsMessage.focus();
        updateSmsCharCount();
    });

    function updateSmsCharCount() {
        if (!smsCharEl || !smsMessage) return;
        smsCharEl.textContent = (smsMessage.value || '').length + ' / 1600';
    }
    smsMessage && smsMessage.addEventListener('input', updateSmsCharCount);
    updateSmsCharCount();

    function setChannel(ch) {
        if (channelInput) channelInput.value = ch;
        if (attachmentInput) attachmentInput.disabled = ch === 'sms';
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
    setChannel(channelInput && channelInput.value === 'sms' ? 'sms' : 'email');
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

    document.getElementById('loadPensionTemplate')?.addEventListener('click', function() {
        if (subjectInput) {
            subjectInput.value = '2025 Pension Declared Rate of Return';
        }
        if (bodyInput) {
            bodyInput.value =
                'Dear @{{first_name}},\n\n' +
                'We are pleased to inform you that your pension contributions earned a return of 12.25% in 2025, up from 11.5% in 2024.\n\n' +
                'Please find the official communication on the declared rate of return attached for your records.\n\n' +
                'A detailed breakdown, including how this rate has been applied and its impact on your accumulated funds, will be provided in your Member Statement in due course.\n\n' +
                'For enquiries, please call 0709 551 150 or email life@geminialife.co.ke.\n\n' +
                'Thank you.\n' +
                'GEMINIA LIFE INSURANCE CO. LTD';
            bodyInput.focus();
        }
    });
    document.getElementById('clearEmailTemplate')?.addEventListener('click', function() {
        if (subjectInput) subjectInput.value = '';
        if (bodyInput) bodyInput.value = '';
    });

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
