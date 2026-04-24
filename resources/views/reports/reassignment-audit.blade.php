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
            <h1 class="reports-audit-title mb-1">Ticket Reassignment Audit</h1>
            <p class="reports-audit-subtitle mb-0">Audit trail of all ticket reassignments for compliance and accountability.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <form action="{{ route('reports.reassignment-audit') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="ticket" value="{{ $ticket ?? '' }}" class="form-control form-control-sm" style="width: 180px;" placeholder="Ticket e.g. TT2025809937">
                <label class="text-muted small mb-0">Show</label>
                <select name="limit" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50 recent</option>
                    <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200 recent</option>
                    <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500 recent</option>
                    <option value="1000" {{ $limit == 1000 ? 'selected' : '' }}>1000 recent</option>
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

    <div class="reports-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th>
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
                            <a href="{{ route('tickets.show', $r->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                TT{{ $r->ticket_id }}
                            </a>
                        </td>
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
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge display-6 d-block mb-2 text-muted"></i>
                            No reassignments recorded yet. Changes are logged when tickets are reassigned.
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
