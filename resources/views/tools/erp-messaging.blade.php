@extends('layouts.app')

@section('title', 'ERP Messaging')

@php
    $previewCount = (int) ($previewCount ?? 0);
    $totalPending = $totalPending ?? null;
    $previewLimit = (int) ($previewLimit ?? 50);
    $canLoad = $canLoad ?? false;
    $canSendLive = $canSendLive ?? false;
    $autoSendEnabled = $autoSendEnabled ?? false;
    $summary = session('erp_sms_summary');
    $hasMoreInErp = $totalPending !== null && $totalPending > $previewCount;
@endphp

@push('head')
<style>
    .erp-pending-page { max-width: 1400px; }
    .erp-pending-hero {
        background: linear-gradient(135deg, rgba(26, 70, 138, 0.08) 0%, rgba(51, 180, 227, 0.06) 100%);
        border: 1px solid var(--geminia-border);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
    }
    .erp-status-tile {
        border-radius: 14px;
        border: 1px solid var(--geminia-border);
        background: #fff;
        padding: 1rem 1.15rem;
        height: 100%;
    }
    .erp-status-tile.is-ok { border-color: rgba(34, 197, 94, 0.45); background: rgba(34, 197, 94, 0.04); }
    .erp-status-tile.is-warn { border-color: rgba(234, 179, 8, 0.5); background: rgba(234, 179, 8, 0.06); }
    .erp-status-tile.is-bad { border-color: rgba(239, 68, 68, 0.45); background: rgba(239, 68, 68, 0.04); }
    .erp-status-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .erp-step-list { counter-reset: erp-step; list-style: none; padding: 0; margin: 0; }
    .erp-step-list li {
        counter-increment: erp-step;
        position: relative;
        padding-left: 2.25rem;
        margin-bottom: 0.65rem;
        font-size: 0.9rem;
        color: var(--geminia-text-muted);
    }
    .erp-step-list li::before {
        content: counter(erp-step);
        position: absolute;
        left: 0;
        top: 0.05rem;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        background: var(--geminia-primary-muted);
        color: var(--geminia-primary);
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .erp-send-panel {
        border-radius: 14px;
        border: 1px solid var(--geminia-border);
        background: #fff;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
    }
    .erp-dry-run-box {
        border-radius: 10px;
        border: 1px dashed rgba(234, 179, 8, 0.6);
        background: rgba(234, 179, 8, 0.06);
        padding: 0.75rem 1rem;
    }
    .erp-result-stat {
        text-align: center;
        padding: 0.75rem;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--geminia-border);
    }
    .erp-pending-table-wrap {
        border-radius: 0 0 14px 14px;
        max-height: min(65vh, 800px);
        overflow: auto;
    }
    .erp-pending-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
        color: var(--geminia-text-muted);
        white-space: nowrap;
        box-shadow: 0 1px 0 var(--geminia-border);
    }
    .erp-pending-table tbody tr.filtered-out { display: none; }
</style>
@endpush

@section('content')
<div class="erp-pending-page">
    <div class="erp-pending-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <nav class="mb-2" aria-label="Breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('tools') }}" class="text-decoration-none text-muted">Tools</a></li>
                        <li class="breadcrumb-item active" aria-current="page">ERP Messaging</li>
                    </ol>
                </nav>
                <h1 class="page-title mb-2">Draft SMS</h1>
                <p class="page-subtitle mb-0 text-muted">
                    Only <strong>draft</strong> messages in ERP are sent from here. Already-sent rows are not resent.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('tools.erp-messaging.sent') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> View sent &amp; delivery
                </a>
                <a href="{{ route('tools') }}" class="btn btn-outline-secondary btn-sm">Tools</a>
            </div>
        </div>
    </div>

    @if (session('success') || session('warning') || session('error'))
        <div class="alert alert-{{ session('error') ? 'danger' : (session('warning') ? 'warning' : 'success') }} border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex gap-2 align-items-start">
                <i class="bi bi-{{ session('error') ? 'exclamation-octagon' : (session('warning') ? 'exclamation-triangle' : 'check-circle') }}-fill fs-5"></i>
                <div class="flex-grow-1">
                    <strong>{{ session('error') ? 'Send failed' : (session('warning') ? 'Completed with issues' : 'Send completed') }}</strong>
                    <p class="mb-0 small mt-1">{{ session('success') ?? session('warning') ?? session('error') }}</p>
                    @if (is_array($summary))
                        <div class="row g-2 mt-3">
                            <div class="col-6 col-md-3">
                                <div class="erp-result-stat">
                                    <div class="text-muted small">Processed</div>
                                    <div class="h4 mb-0 fw-semibold">{{ (int) ($summary['processed'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="erp-result-stat border-success" style="background: rgba(34,197,94,0.06);">
                                    <div class="text-muted small">Sent via SMS</div>
                                    <div class="h4 mb-0 fw-semibold text-success">{{ (int) ($summary['sent'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="erp-result-stat border-danger" style="background: rgba(239,68,68,0.05);">
                                    <div class="text-muted small">Failed</div>
                                    <div class="h4 mb-0 fw-semibold text-danger">{{ (int) ($summary['failed'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="erp-result-stat">
                                    <div class="text-muted small">Skipped</div>
                                    <div class="h4 mb-0 fw-semibold">{{ (int) ($summary['skipped'] ?? 0) }}</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">e.g. dry run or invalid row</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    {{-- Readiness --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="erp-status-tile {{ $canLoad ? 'is-ok' : 'is-bad' }}">
                <div class="d-flex gap-3 align-items-start">
                    <div class="erp-status-icon" style="background: {{ $canLoad ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.12)' }}; color: {{ $canLoad ? '#15803d' : '#b91c1c' }};">
                        <i class="bi bi-{{ $canLoad ? 'database-check' : 'database-x' }}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">ERP draft queue</div>
                        <div class="small text-muted">
                            @if ($canLoad && empty($loadError))
                                Connected — showing draft messages only
                            @elseif (!empty($loadError))
                                Cannot load queue
                            @else
                                Not configured
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="erp-status-tile {{ $canSendLive ? 'is-ok' : 'is-warn' }}">
                <div class="d-flex gap-3 align-items-start">
                    <div class="erp-status-icon" style="background: {{ $canSendLive ? 'rgba(34,197,94,0.15)' : 'rgba(234,179,8,0.15)' }}; color: {{ $canSendLive ? '#15803d' : '#a16207' }};">
                        <i class="bi bi-{{ $canSendLive ? 'chat-dots-fill' : 'chat-dots' }}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">SMS gateway (Advanta)</div>
                        <div class="small text-muted">
                            @if ($canSendLive)
                                Ready to send live SMS
                            @else
                                Not set up — contact IT to configure the SMS provider
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="erp-status-tile {{ $autoSendEnabled ? 'is-ok' : '' }}">
                <div class="d-flex gap-3 align-items-start">
                    <div class="erp-status-icon bg-light text-primary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Automatic sending</div>
                        <div class="small text-muted">
                            @if ($autoSendEnabled)
                                On — new ERP drafts are sent automatically (nothing already sent)
                            @else
                                Off — use <strong>Send draft SMS</strong> below when drafts are ready
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($loadError))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4" role="alert">
            <i class="bi bi-database-exclamation me-2"></i>
            <strong>Could not load the pending queue.</strong>
            <p class="mb-0 small mt-1">{{ $loadError }}</p>
        </div>
    @endif

    <div class="row g-4 mb-4">
        {{-- How it works --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-signpost-split text-primary me-1"></i> How this page works</h2>
                    <ol class="erp-step-list">
                        <li><strong class="text-body">Preview</strong> — draft rows waiting in ERP (up to {{ $previewLimit }}).</li>
                        <li><strong class="text-body">Send draft SMS</strong> — sends each draft once, then marks it sent in ERP.</li>
                        <li><strong class="text-body">Track</strong> — open <a href="{{ route('tools.erp-messaging.sent') }}">Sent &amp; delivery</a> to see delivery status.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Send panel --}}
        <div class="col-lg-7">
            <div class="erp-send-panel p-4 h-100">
                <h2 class="h6 fw-semibold mb-1"><i class="bi bi-send-fill text-primary me-1"></i> Send draft SMS</h2>
                <p class="small text-muted mb-3">
                    @if ($canLoad && $totalPending !== null)
                        <span class="fw-semibold text-body">{{ number_format($totalPending) }}</span>
                        draft message{{ $totalPending === 1 ? '' : 's' }} in ERP
                        @if ($hasMoreInErp)
                            (table shows oldest {{ $previewLimit }})
                        @endif
                    @elseif ($canLoad)
                        Queue loaded — use the table below to review before sending.
                    @else
                        Fix ERP connection above before sending.
                    @endif
                </p>

                <form action="{{ route('tools.erp-messaging.send') }}" method="POST" id="erp-send-form">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-5 col-md-4">
                            <label for="erp-send-limit" class="form-label small fw-semibold mb-1">How many to process</label>
                            <input type="number"
                                   id="erp-send-limit"
                                   name="limit"
                                   min="1"
                                   max="500"
                                   value="{{ old('limit', min(50, max(1, (int) ($totalPending ?? 50)))) }}"
                                   class="form-control"
                                   {{ !$canSendLive ? 'disabled' : '' }}>
                            @error('limit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            <div class="form-text">Max 500 per run. Oldest pending rows first.</div>
                        </div>
                        <div class="col-sm-7 col-md-8">
                            <div class="erp-dry-run-box">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" value="1" id="dry_run" name="dry_run" {{ !$canSendLive ? 'disabled' : '' }}>
                                    <label class="form-check-label fw-semibold" for="dry_run">
                                        Test only (dry run)
                                    </label>
                                </div>
                                <p class="small text-muted mb-0 mt-1">
                                    When checked, <strong>no SMS is sent</strong> and ERP is not updated — use this to verify the queue without contacting customers.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-4 align-items-center">
                        <button type="submit"
                                class="btn btn-primary-custom"
                                id="erp-send-btn"
                                {{ !$canSendLive ? 'disabled' : '' }}>
                            <i class="bi bi-send me-1"></i> Send draft SMS
                        </button>
                        @if (!$canSendLive && $canLoad)
                            <span class="small text-warning"><i class="bi bi-lock me-1"></i> Configure Advanta SMS to unlock</span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Queue table --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-semibold"><i class="bi bi-inbox me-1 text-muted"></i> Draft queue</span>
                @if ($canLoad && empty($loadError))
                    <span class="badge rounded-pill text-bg-warning ms-2">Draft</span>
                    <span class="text-muted small ms-1">
                        {{ $previewCount }} shown
                        @if ($totalPending !== null && $totalPending !== $previewCount)
                            · {{ number_format($totalPending) }} total in ERP
                        @endif
                    </span>
                @endif
            </div>
            @if ($canLoad && $previewCount > 0)
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" id="erp-pending-search" class="form-control" placeholder="Search phone, policy, ID…" autocomplete="off" aria-label="Search queue">
                </div>
            @endif
        </div>
        <div class="erp-pending-table-wrap">
            <table class="table table-hover align-middle mb-0 erp-pending-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Message ID</th>
                        <th scope="col">Policy</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Module</th>
                        <th scope="col">Queued in ERP</th>
                        <th scope="col">SMS text</th>
                    </tr>
                </thead>
                <tbody id="erp-pending-tbody">
                    @forelse(($pending ?? collect()) as $message)
                    @php
                        $searchBlob = strtolower(implode(' ', array_filter([
                            $message->message_id,
                            $message->policy_no,
                            $message->phone,
                            $message->message_body,
                            $message->sys_module,
                        ])));
                    @endphp
                    <tr class="erp-pending-row" data-search="{{ e($searchBlob) }}">
                        <td class="font-monospace small">{{ $message->message_id }}</td>
                        <td class="small">{{ $message->policy_no ?: '—' }}</td>
                        <td class="text-nowrap small font-monospace">{{ $message->phone ?: '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ $message->sys_module ?: '—' }}</span></td>
                        <td class="text-nowrap text-muted small">{{ $message->created_date ?: '—' }}</td>
                        <td style="max-width: 360px;">
                            <div class="small erp-msg-preview text-truncate" title="{{ e($message->message_body) }}">{{ $message->message_body }}</div>
                            @if (strlen((string) $message->message_body) > 60)
                                <button type="button" class="btn btn-link btn-sm p-0 erp-toggle-msg">Show full</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            @if (!empty($loadError))
                                <i class="bi bi-database-x display-6 d-block mb-3 opacity-50"></i>
                                <p class="mb-0 fw-medium text-body">Queue unavailable</p>
                            @else
                                <i class="bi bi-check-circle display-6 d-block mb-3 text-success opacity-75"></i>
                                <p class="mb-1 fw-medium text-body">No draft messages</p>
                                <p class="small mb-0">All caught up — no SMS waiting as draft in ERP.</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($hasMoreInErp)
            <div class="card-footer bg-light small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                ERP has more than {{ $previewLimit }} pending rows. Increase <strong>How many to process</strong> or run send again until the count reaches zero.
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('erp-send-form');
    var dryRun = document.getElementById('dry_run');
    var btn = document.getElementById('erp-send-btn');
    if (form && dryRun && btn) {
        form.addEventListener('submit', function (e) {
            if (!dryRun.checked) {
                var n = document.getElementById('erp-send-limit');
                var count = n ? parseInt(n.value, 10) || 0 : 0;
                var msg = 'Send up to ' + count + ' draft SMS now? Each draft is sent once, then marked sent in ERP.';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Running test…';
        });
    }

    var searchInput = document.getElementById('erp-pending-search');
    var tbody = document.getElementById('erp-pending-tbody');
    if (searchInput && tbody) {
        var rows = tbody.querySelectorAll('tr.erp-pending-row');
        searchInput.addEventListener('input', function () {
            var q = (this.value || '').trim().toLowerCase();
            rows.forEach(function (tr) {
                var hay = tr.getAttribute('data-search') || '';
                tr.classList.toggle('filtered-out', q && hay.indexOf(q) === -1);
            });
        });
    }

    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.erp-toggle-msg');
            if (!btn) return;
            var preview = btn.closest('td').querySelector('.erp-msg-preview');
            if (!preview) return;
            var expanded = preview.classList.toggle('text-truncate') === false;
            preview.style.whiteSpace = expanded ? 'normal' : '';
            btn.textContent = expanded ? 'Show less' : 'Show full';
        });
    }
});
</script>
@endpush
