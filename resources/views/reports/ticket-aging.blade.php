@extends('layouts.app')

@section('title', 'Ticket Aging Report')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Ticket Aging</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Ticket Aging Report</h1>
            <p class="reports-audit-subtitle mb-0">Open tickets older than {{ $days }} days.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <form action="{{ route('reports.ticket-aging') }}" method="GET" class="d-flex gap-2 align-items-center">
                <select name="days" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="3" {{ $days == 3 ? 'selected' : '' }}>3+ days old</option>
                    <option value="7" {{ $days == 7 ? 'selected' : '' }}>7+ days old</option>
                    <option value="14" {{ $days == 14 ? 'selected' : '' }}>14+ days old</option>
                    <option value="30" {{ $days == 30 ? 'selected' : '' }}>30+ days old</option>
                </select>
            </form>
            <a href="{{ route('reports.export.ticket-aging', ['days' => $days, 'format' => 'xlsx']) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </a>
            <a href="{{ route('reports.export.ticket-aging', ['days' => $days]) }}" class="btn btn-outline-secondary btn-sm">
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
                        <th>Title</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th>Contact</th>
                        <th>Created</th>
                        <th>Assigned To</th>
                        <th>User Dept</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets ?? [] as $t)
                    <tr>
                        <td>
                            <a href="{{ route('tickets.show', $t->ticketid) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $t->ticket_no ?? 'TT' . $t->ticketid }}
                            </a>
                        </td>
                        <td>{{ Str::limit($t->title ?? '—', 50) }}</td>
                        <td><span class="badge bg-warning">{{ $t->status ?? '—' }}</span></td>
                        <td>{{ $t->category ?? 'General' }}</td>
                        <td>{{ trim(($t->firstname ?? '') . ' ' . ($t->lastname ?? '')) ?: '—' }}</td>
                        <td class="text-nowrap">{{ $t->createdtime ? \Carbon\Carbon::parse($t->createdtime)->format('d M Y H:i') : '—' }}</td>
                        <td>{{ trim(($t->owner_first ?? '') . ' ' . ($t->owner_last ?? '')) ?: 'Unassigned' }}</td>
                        <td>{{ $t->owner_department ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-6 d-block mb-2 text-success"></i>
                            No open tickets older than {{ $days }} days.
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
