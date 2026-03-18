@extends('layouts.app')

@section('title', 'Ticket Reassignment Audit')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav class="mb-2">
            <a href="{{ route('reports') }}" class="text-muted small">Reports</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">Reassignment Audit</span>
        </nav>
        <h1 class="page-title mb-1">Ticket Reassignment Audit</h1>
        <p class="page-subtitle mb-0">Audit trail of all ticket reassignments for compliance and accountability.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form action="{{ route('reports.reassignment-audit') }}" method="GET" class="d-flex gap-2 align-items-center">
            <label class="text-muted small mb-0">Show</label>
            <select name="limit" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50 recent</option>
                <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200 recent</option>
                <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500 recent</option>
                <option value="1000" {{ $limit == 1000 ? 'selected' : '' }}>1000 recent</option>
            </select>
        </form>
        <a href="{{ route('reports.export.reassignment-audit', ['limit' => $limit, 'format' => 'xlsx']) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
        </a>
        <a href="{{ route('reports.export.reassignment-audit', ['limit' => $limit]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Ticket</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">From</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">To</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Reassigned By</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reassignments ?? [] as $r)
                    <tr>
                        <td class="px-4">
                            <a href="{{ route('tickets.show', $r->ticket_id) }}" class="fw-semibold text-primary text-decoration-none font-monospace">
                                TT{{ $r->ticket_id }}
                            </a>
                        </td>
                        <td class="px-4">{{ e($r->from_user_name ?? 'Unassigned') }}</td>
                        <td class="px-4 fw-semibold">{{ e($r->to_user_name ?? '—') }}</td>
                        <td class="px-4">{{ e($r->reassigned_by_name ?? '—') }}</td>
                        <td class="px-4 text-nowrap">{{ $r->created_at?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge display-6 d-block mb-2 text-muted"></i>
                            No reassignments recorded yet. Changes are logged when tickets are reassigned.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
