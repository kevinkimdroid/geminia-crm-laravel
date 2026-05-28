@extends('layouts.app')

@section('title', 'ERP Messaging — Sent')

@php
    $counts = $counts ?? [];
    $filter = $filter ?? 'all';
    $rows = $rows ?? collect();
    $filterLinks = [
        'all' => ['label' => 'All', 'icon' => 'bi-collection', 'desc' => 'Show every row in this snapshot'],
        'pending_delivery' => ['label' => 'Awaiting delivery', 'icon' => 'bi-cloud-upload', 'desc' => 'Submitted to SMS gateway, no handset receipt yet'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'bi-check2-circle', 'desc' => 'Marked delivered in CRM'],
        'read' => ['label' => 'Read', 'icon' => 'bi-eye', 'desc' => 'Read timestamp set (if you record it)'],
        'not_read' => ['label' => 'Not read', 'icon' => 'bi-eye-slash', 'desc' => 'Delivered but not marked read'],
    ];
    // Legacy URL ?filter=sent behaves like “all” in the service
    if ($filter === 'sent') {
        $filter = 'all';
    }
    $transport = $erpSmsTransport ?? '';
@endphp

@push('head')
<style>
    .erp-sent-page { max-width: 1400px; }
    .erp-sent-hero {
        background: linear-gradient(135deg, rgba(26, 70, 138, 0.08) 0%, rgba(51, 180, 227, 0.06) 100%);
        border: 1px solid var(--geminia-border);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
    }
    .erp-sent-stat {
        display: block;
        text-decoration: none;
        color: inherit;
        border-radius: 14px;
        border: 1px solid var(--geminia-border);
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        height: 100%;
    }
    .erp-sent-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        border-color: rgba(26, 70, 138, 0.25);
        color: inherit;
    }
    .erp-sent-stat.is-active {
        border-color: var(--geminia-primary);
        box-shadow: 0 0 0 3px var(--geminia-primary-light);
    }
    .erp-sent-stat .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
    }
    .erp-sent-filter-pill {
        border-radius: 999px;
        padding: 0.45rem 0.95rem;
        font-weight: 500;
        font-size: 0.875rem;
        border: 1px solid var(--geminia-border);
        background: #fff;
        color: var(--geminia-text);
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.15s ease;
    }
    .erp-sent-filter-pill:hover {
        border-color: rgba(26, 70, 138, 0.35);
        background: var(--geminia-primary-muted);
        color: inherit;
    }
    .erp-sent-filter-pill.active {
        background: var(--geminia-primary);
        border-color: var(--geminia-primary);
        color: #fff;
    }
    .erp-sent-filter-pill.active i { opacity: 1; }
    .erp-sent-table-wrap {
        border-radius: 0 0 14px 14px;
        max-height: min(70vh, 900px);
        overflow: auto;
    }
    .erp-sent-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        box-shadow: 0 1px 0 var(--geminia-border);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
        color: var(--geminia-text-muted);
        white-space: nowrap;
    }
    .erp-sent-table tbody tr.filtered-out { display: none; }
    .erp-sent-toolbar {
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .erp-sent-search {
        max-width: 320px;
    }
    @media (max-width: 767.98px) {
        .erp-sent-search { max-width: none; flex: 1 1 100%; }
    }
</style>
@endpush

@section('content')
<div class="erp-sent-page">
    <div class="erp-sent-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <nav class="mb-2" aria-label="Breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('tools') }}" class="text-decoration-none text-muted">Tools</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('tools.erp-messaging') }}" class="text-decoration-none text-muted">ERP Messaging</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Sent</li>
                    </ol>
                </nav>
                <h1 class="page-title mb-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="text-dark">Sent SMS &amp; delivery</span>
                    @if ($transport === 'http')
                        <span class="badge rounded-pill text-bg-info fw-normal align-middle">HTTP API</span>
                    @elseif ($transport === 'oracle')
                        <span class="badge rounded-pill text-bg-secondary fw-normal align-middle">Oracle</span>
                    @endif
                </h1>
                <p class="page-subtitle mb-0 text-muted">
                    Rows from ERP status <code class="user-select-all">{{ config('erp.messages_sent_status', 'OK') }}</code>, with delivery/read from CRM <code class="user-select-all">sms_logs</code>.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('tools.erp-messaging') }}" class="btn btn-primary-custom btn-sm shadow-sm">
                    <i class="bi bi-send me-1"></i> Pending queue
                </a>
                <a href="{{ route('tools') }}" class="btn btn-outline-secondary btn-sm">Tools</a>
            </div>
        </div>
    </div>

    @if (!empty($loadError))
        <div class="alert alert-danger border-0 shadow-sm rounded-3" role="alert">
            <div class="d-flex gap-2">
                <i class="bi bi-database-exclamation fs-5"></i>
                <div>
                    <strong>Could not load sent messages.</strong>
                    <p class="mb-0 small mt-1">{{ $loadError }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (empty($loadError))
        <details class="card border mb-4 shadow-sm rounded-3">
            <summary class="list-group-item list-group-item-action border-0 py-3 px-3 rounded-3 small fw-medium cursor-pointer" style="cursor: pointer;">
                <i class="bi bi-info-circle text-primary me-1"></i> How delivery &amp; read work
            </summary>
            <div class="card-body border-top pt-3 small text-muted">
                <ul class="mb-0 ps-3">
                    <li><strong>Advanta status</strong> (Success, Blacklisted, etc.) is loaded from Advanta’s <code>getdlr</code> API — the same source as the Advanta portal.</li>
                    <li>CRM runs <code>advanta:sync-delivery</code> every 10 minutes (or run it manually).</li>
                    <li><strong>Submitted</strong> means accepted by Advanta; refresh after a few minutes for final delivery status.</li>
                </ul>
            </div>
        </details>

        <p class="small text-muted mb-2"><span class="fw-semibold text-body">Quick counts</span> — click a card to apply that filter.</p>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'all']) }}"
                   class="erp-sent-stat p-3 {{ $filter === 'all' ? 'is-active' : '' }}"
                   aria-current="{{ $filter === 'all' ? 'page' : 'false' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon bg-light text-primary"><i class="bi bi-inboxes"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-muted small">All in list</div>
                            <div class="h4 mb-0 fw-semibold">{{ number_format((int) ($counts['total'] ?? 0)) }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'pending_delivery']) }}"
                   class="erp-sent-stat p-3 {{ $filter === 'pending_delivery' ? 'is-active' : '' }}"
                   aria-current="{{ $filter === 'pending_delivery' ? 'page' : 'false' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon" style="background: rgba(234, 179, 8, 0.15); color: #a16207;"><i class="bi bi-hourglass-split"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-muted small">Awaiting delivery</div>
                            <div class="h4 mb-0 fw-semibold">{{ number_format((int) ($counts['pending_delivery'] ?? 0)) }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'delivered']) }}"
                   class="erp-sent-stat p-3 {{ $filter === 'delivered' ? 'is-active' : '' }}"
                   aria-current="{{ $filter === 'delivered' ? 'page' : 'false' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #15803d;"><i class="bi bi-check2-circle"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-muted small">Delivered</div>
                            <div class="h4 mb-0 fw-semibold text-success">{{ number_format((int) ($counts['delivered'] ?? 0)) }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'read']) }}"
                   class="erp-sent-stat p-3 {{ $filter === 'read' ? 'is-active' : '' }}"
                   aria-current="{{ $filter === 'read' ? 'page' : 'false' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.12); color: #1d4ed8;"><i class="bi bi-eye"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-muted small">Read</div>
                            <div class="h4 mb-0 fw-semibold text-primary">{{ number_format((int) ($counts['read'] ?? 0)) }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'not_read']) }}"
                   class="erp-sent-stat p-3 {{ $filter === 'not_read' ? 'is-active' : '' }}"
                   aria-current="{{ $filter === 'not_read' ? 'page' : 'false' }}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #b45309;"><i class="bi bi-eye-slash"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-muted small">Not read</div>
                            <div class="h4 mb-0 fw-semibold text-warning">{{ number_format((int) ($counts['not_read'] ?? 0)) }}</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-2">
            <div class="card-body py-3 border-bottom bg-white">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-2 small text-uppercase text-muted letter-spacing-1">Filter</div>
                        <div class="d-flex flex-wrap gap-2" role="tablist" aria-label="Filter messages">
                            @foreach ($filterLinks as $key => $meta)
                                <a href="{{ route('tools.erp-messaging.sent', ['filter' => $key]) }}"
                                   class="erp-sent-filter-pill {{ $filter === $key ? 'active' : '' }}"
                                   title="{{ $meta['desc'] }}"
                                   role="tab"
                                   aria-selected="{{ $filter === $key ? 'true' : 'false' }}">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                    {{ $meta['label'] }}
                                </a>
                            @endforeach
                        </div>
                        <div class="d-lg-none mt-3">
                            <label for="erp-sent-filter-select" class="form-label small text-muted mb-1">Jump to filter (mobile)</label>
                            <select id="erp-sent-filter-select" class="form-select form-select-sm">
                                @foreach ($filterLinks as $key => $meta)
                                    <option value="{{ route('tools.erp-messaging.sent', ['filter' => $key]) }}" @selected($filter === $key)>
                                        {{ $meta['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <span class="d-none d-md-inline">Bookmark:</span>
                        <code class="user-select-all small d-none d-md-inline">{{ route('tools.erp-messaging.sent', ['filter' => $filter]) }}</code>
                    </div>
                </div>
            </div>

            <div class="card-header bg-white border-0 py-3 px-3 d-flex erp-sent-toolbar justify-content-between align-items-center flex-wrap">
                <div class="fw-semibold">
                    <i class="bi bi-table me-1 text-muted"></i> Messages
                    <span class="fw-normal text-muted small ms-1">(<span id="erp-sent-visible-count">{{ $rows->count() }}</span> of {{ $rows->count() }})</span>
                </div>
                <div class="input-group input-group-sm erp-sent-search">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search"
                           id="erp-sent-search"
                           class="form-control border-start-0"
                           placeholder="Search phone, policy, ID, text…"
                           autocomplete="off"
                           aria-label="Search table">
                    <button type="button" class="btn btn-outline-secondary" id="erp-sent-search-clear" title="Clear search">Clear</button>
                </div>
            </div>

            <div class="erp-sent-table-wrap">
                <table class="table table-hover align-middle mb-0 erp-sent-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Message ID</th>
                            <th scope="col">Policy</th>
                            <th scope="col">Phone</th>
                            <th scope="col">ERP sent</th>
                            <th scope="col">CRM sent</th>
                            <th scope="col">Advanta status</th>
                            <th scope="col">Read</th>
                            <th scope="col" class="d-none d-md-table-cell">Module</th>
                            <th scope="col">Message</th>
                        </tr>
                    </thead>
                    <tbody id="erp-sent-tbody">
                        @forelse($rows as $message)
                        @php
                            $deliveryState = $message->delivery_state ?? 'unknown';
                            $readState = $message->read_state ?? 'unknown';
                            $deliveryBadge = match ($deliveryState) {
                                'delivered' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'secondary',
                            };
                            $displayLabel = trim((string) ($message->advanta_status ?? '')) !== ''
                                ? $message->advanta_status
                                : $deliveryLabel;
                            $readBadge = match ($readState) {
                                'read' => 'primary',
                                'not_read' => 'warning',
                                default => 'secondary',
                            };
                            $readLabel = match ($readState) {
                                'read' => 'Read',
                                'not_read' => 'Not read',
                                default => 'N/A',
                            };
                            $deliveryLabel = match ($deliveryState) {
                                'delivered' => 'Delivered',
                                'pending' => 'Submitted',
                                default => 'Unknown',
                            };
                            $searchBlob = strtolower(implode(' ', array_filter([
                                $message->message_id,
                                $message->policy_no,
                                $message->phone,
                                $message->message_body,
                                $message->sys_module,
                            ])));
                        @endphp
                        <tr class="erp-sent-row" data-search="{{ e($searchBlob) }}">
                            <td class="font-monospace small">{{ $message->message_id ?: '—' }}</td>
                            <td class="small">{{ $message->policy_no ?: '—' }}</td>
                            <td class="text-nowrap small">{{ $message->phone ?: '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ $message->sent_date ?: '—' }}</td>
                            <td class="text-nowrap text-muted small">
                                {{ $message->crm_sent_at ? $message->crm_sent_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $deliveryBadge }}">{{ $displayLabel }}</span>
                                @if (!empty($message->advanta_message_id))
                                    <div class="text-muted small mt-1 font-monospace">ID {{ $message->advanta_message_id }}</div>
                                @endif
                                @if (!empty($message->delivered_at))
                                    <div class="text-muted small mt-1">{{ $message->delivered_at->format('Y-m-d H:i') }}</div>
                                @endif
                                @if (!empty($message->advanta_delivery_tat))
                                    <div class="text-muted small">{{ $message->advanta_delivery_tat }} after send</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $readBadge }}">{{ $readLabel }}</span>
                                @if (!empty($message->read_at))
                                    <div class="text-muted small mt-1">{{ $message->read_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell"><span class="badge text-bg-light border">{{ $message->sys_module ?: '—' }}</span></td>
                            <td style="max-width: 280px;">
                                <div class="erp-sent-msg-preview small text-truncate" title="{{ e($message->message_body) }}">{{ $message->message_body }}</div>
                                @if (strlen((string) $message->message_body) > 80)
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none erp-sent-toggle-msg">Show full</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-6 d-block mb-3 opacity-50"></i>
                                <p class="mb-1 fw-medium text-body">No messages for this filter</p>
                                <p class="small mb-0">Try <a href="{{ route('tools.erp-messaging.sent', ['filter' => 'all']) }}">All</a> or check ERP / API connectivity.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('erp-sent-filter-select');
    if (sel) {
        sel.addEventListener('change', function () {
            if (this.value) window.location.href = this.value;
        });
    }

    var searchInput = document.getElementById('erp-sent-search');
    var clearBtn = document.getElementById('erp-sent-search-clear');
    var tbody = document.getElementById('erp-sent-tbody');
    var visibleEl = document.getElementById('erp-sent-visible-count');
    if (!searchInput || !tbody) return;

    var rows = tbody.querySelectorAll('tr.erp-sent-row');
    var total = rows.length;

    function runFilter() {
        var q = (searchInput.value || '').trim().toLowerCase();
        var n = 0;
        rows.forEach(function (tr) {
            var hay = tr.getAttribute('data-search') || '';
            var show = !q || hay.indexOf(q) !== -1;
            tr.classList.toggle('filtered-out', !show);
            if (show) n++;
        });
        if (visibleEl) visibleEl.textContent = String(n);
    }

    searchInput.addEventListener('input', runFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            runFilter();
            searchInput.focus();
        });
    }

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.erp-sent-toggle-msg');
        if (!btn) return;
        var cell = btn.closest('td');
        if (!cell) return;
        var preview = cell.querySelector('.erp-sent-msg-preview');
        if (!preview) return;
        var full = preview.classList.toggle('text-truncate') === false;
        preview.style.whiteSpace = full ? 'normal' : '';
        preview.style.maxWidth = full ? 'none' : '';
        btn.textContent = full ? 'Show less' : 'Show full';
    });
});
</script>
@endpush
