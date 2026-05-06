@extends('layouts.app')

@section('title', 'Ticket Reassignment Audit')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Reassignment Audit</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Ticket Audit Trail</h1>
            <p class="reports-audit-subtitle mb-0">Audit trail for CRM tickets and work tickets for compliance and accountability.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <form action="{{ route('reports.reassignment-audit') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="ticket" value="{{ $ticket ?? '' }}" class="form-control form-control-sm" style="width: 200px;" placeholder="Ticket e.g. TT123 or WT-202605-0001">
                <label class="text-muted small mb-0">Show</label>
                <select name="limit" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="50" {{ ($limitRaw ?? (string) $limit) === '50' ? 'selected' : '' }}>50 recent</option>
                    <option value="200" {{ ($limitRaw ?? (string) $limit) === '200' ? 'selected' : '' }}>200 recent</option>
                    <option value="500" {{ ($limitRaw ?? (string) $limit) === '500' ? 'selected' : '' }}>500 recent</option>
                    <option value="1000" {{ ($limitRaw ?? (string) $limit) === '1000' ? 'selected' : '' }}>1000 recent</option>
                    <option value="all" {{ ($fetchAll ?? false) ? 'selected' : '' }}>All tickets</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button>
            </form>
            <a href="{{ route('reports.export.reassignment-audit', ['limit' => $limit, 'ticket' => $ticket ?? '', 'format' => 'xlsx']) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </a>
            <a href="{{ route('reports.export.reassignment-audit', ['limit' => $limit, 'ticket' => $ticket ?? '']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Print report">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    @php
        $trailRows = collect($reassignments ?? [])
            ->groupBy(function ($r) {
                $module = (string) ($r->module_type ?? 'ticket');
                $ticketNumber = trim((string) ($r->ticket_number ?? ''));
                $ticketId = trim((string) ($r->ticket_id ?? ''));
                $fallback = $module === 'work-ticket' ? ('WT-' . $ticketId) : ('TT' . $ticketId);
                return $module . '|' . ($ticketNumber !== '' ? $ticketNumber : $fallback);
            })
            ->map(function ($group) {
                $ordered = $group->sortBy(function ($r) {
                    if (!empty($r->created_at) && $r->created_at instanceof \Carbon\CarbonInterface) {
                        return $r->created_at->timestamp;
                    }
                    $ts = strtotime((string) ($r->created_at ?? ''));
                    return $ts !== false ? $ts : 0;
                })->values();

                $first = $ordered->first();
                $last = $ordered->last();
                $events = $ordered->map(function ($r) {
                    $event = trim((string) ($r->event_type ?? 'Reassigned'));
                    $from = trim((string) ($r->from_user_name ?? '—'));
                    $to = trim((string) ($r->to_user_name ?? '—'));
                    $actor = trim((string) ($r->reassigned_by_name ?? '—'));
                    $stamp = '';
                    if (!empty($r->created_at)) {
                        if ($r->created_at instanceof \Carbon\CarbonInterface) {
                            $stamp = $r->created_at->format('d M H:i');
                        } else {
                            $ts = strtotime((string) $r->created_at);
                            if ($ts !== false) {
                                $stamp = date('d M H:i', $ts);
                            }
                        }
                    }
                    return trim($event . ': ' . ($from !== '' ? $from : '—') . ' -> ' . ($to !== '' ? $to : '—') . ' by ' . ($actor !== '' ? $actor : '—') . ($stamp !== '' ? (' [' . $stamp . ']') : ''));
                })->implode(' | ');

                return (object) [
                    'module_type' => $first->module_type ?? 'ticket',
                    'ticket_id' => $first->ticket_id ?? null,
                    'ticket_number' => $first->ticket_number ?? null,
                    'movement_count' => $ordered->count(),
                    'movement_trail' => $events,
                    'created_by' => trim((string) ($ordered->first()->reassigned_by_name ?? '')) ?: '—',
                    'checked_by' => trim((string) ($ordered->first()->to_user_name ?? '')) ?: '—',
                    'authorized_by' => trim((string) ($ordered->get(2)->to_user_name ?? '')) ?: '—',
                    'closed_by' => trim((string) ($ordered->last()->to_user_name ?? '')) ?: '—',
                    'last_created_at' => $last->created_at ?? null,
                ];
            })
            ->sortByDesc(function ($row) {
                if (!empty($row->last_created_at) && $row->last_created_at instanceof \Carbon\CarbonInterface) {
                    return $row->last_created_at->timestamp;
                }
                $ts = strtotime((string) ($row->last_created_at ?? ''));
                return $ts !== false ? $ts : 0;
            })
            ->values();
    @endphp

    <div class="reports-table-card mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Ticket</th>
                        <th>Movements</th>
                        <th>Created By</th>
                        <th>Checked By</th>
                        <th>Authorized By</th>
                        <th>Closed By</th>
                        <th>Trail (all movements, one row)</th>
                        <th>Latest</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trailRows as $t)
                    <tr>
                        <td>{{ ($t->module_type ?? 'ticket') === 'work-ticket' ? 'Work Ticket' : 'Ticket' }}</td>
                        <td>
                            @if(($t->module_type ?? 'ticket') === 'work-ticket')
                                <a href="{{ route('work-tickets.show', $t->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                    {{ $t->ticket_number ?? ('WT-' . $t->ticket_id) }}
                                </a>
                            @else
                                <a href="{{ route('tickets.show', $t->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                    {{ $t->ticket_number ?? ('TT' . $t->ticket_id) }}
                                </a>
                            @endif
                        </td>
                        <td>{{ (int) ($t->movement_count ?? 0) }}</td>
                        <td>{{ $t->created_by ?? '—' }}</td>
                        <td>{{ $t->checked_by ?? '—' }}</td>
                        <td>{{ $t->authorized_by ?? '—' }}</td>
                        <td>{{ $t->closed_by ?? '—' }}</td>
                        <td class="small">{{ $t->movement_trail ?: '—' }}</td>
                        <td class="text-nowrap">
                            @php
                                $lastLabel = '—';
                                if (!empty($t->last_created_at)) {
                                    if ($t->last_created_at instanceof \Carbon\CarbonInterface) {
                                        $lastLabel = $t->last_created_at->format('d M Y H:i');
                                    } else {
                                        $ts = strtotime((string) $t->last_created_at);
                                        if ($ts !== false) {
                                            $lastLabel = date('d M Y H:i', $ts);
                                        }
                                    }
                                }
                            @endphp
                            {{ $lastLabel }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No ticket movement summaries found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="reports-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Ticket</th>
                        <th>Event</th>
                        <th>From</th>
                        <th>From Dept</th>
                        <th>To</th>
                        <th>To Dept</th>
                        <th>Reassigned By</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reassignments ?? [] as $r)
                    <tr>
                        <td>
                            {{ ($r->module_type ?? 'ticket') === 'work-ticket' ? 'Work Ticket' : 'Ticket' }}
                        </td>
                        <td>
                            @if(($r->module_type ?? 'ticket') === 'work-ticket')
                                <a href="{{ route('work-tickets.show', $r->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                    {{ $r->ticket_number ?? ('WT-' . $r->ticket_id) }}
                                </a>
                            @else
                                <a href="{{ route('tickets.show', $r->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                    {{ $r->ticket_number ?? ('TT' . $r->ticket_id) }}
                                </a>
                            @endif
                        </td>
                        <td>{{ e($r->event_type ?? 'Reassigned') }}</td>
                        <td>{{ e($r->from_user_name ?? 'Unassigned') }}</td>
                        <td>{{ e($r->from_user_department ?? '—') }}</td>
                        <td class="fw-semibold">{{ e($r->to_user_name ?? '—') }}</td>
                        <td>{{ e($r->to_user_department ?? '—') }}</td>
                        <td>{{ e($r->reassigned_by_name ?? '—') }}</td>
                        @php
                            $createdAtLabel = '—';
                            if (!empty($r->created_at)) {
                                if ($r->created_at instanceof \Carbon\CarbonInterface) {
                                    $createdAtLabel = $r->created_at->format('d M Y H:i');
                                } elseif (is_string($r->created_at)) {
                                    $ts = strtotime($r->created_at);
                                    if ($ts !== false) {
                                        $createdAtLabel = date('d M Y H:i', $ts);
                                    }
                                }
                            }
                        @endphp
                        <td class="text-nowrap">{{ $createdAtLabel }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge display-6 d-block mb-2 text-muted"></i>
                            No ticket audit records yet. Reassignments and work-ticket updates are logged automatically.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="reports-meta text-muted small mt-3 py-2">
        <i class="bi bi-clock me-1"></i>Report generated: {{ now()->format('l, F j, Y \a\t g:i A') }}
    </div>
</div>
@endsection
