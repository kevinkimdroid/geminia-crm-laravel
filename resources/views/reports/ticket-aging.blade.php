@extends('layouts.app')

@section('title', 'Ticket Aging Report')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav class="mb-2">
            <a href="{{ route('reports') }}" class="text-muted small">Reports</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">Ticket Aging</span>
        </nav>
        <h1 class="page-title mb-1">Ticket Aging Report</h1>
        <p class="page-subtitle mb-0">Open tickets older than {{ $days }} days.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
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
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Ticket</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Title</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Status</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Category</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Contact</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Created</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Assigned To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets ?? [] as $t)
                    <tr>
                        <td class="px-4">
                            <a href="{{ route('tickets.show', $t->ticketid) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $t->ticket_no ?? 'TT' . $t->ticketid }}
                            </a>
                        </td>
                        <td class="px-4">{{ Str::limit($t->title ?? '—', 50) }}</td>
                        <td class="px-4"><span class="badge bg-warning">{{ $t->status ?? '—' }}</span></td>
                        <td class="px-4">{{ $t->category ?? 'General' }}</td>
                        <td class="px-4">{{ trim(($t->firstname ?? '') . ' ' . ($t->lastname ?? '')) ?: '—' }}</td>
                        <td class="px-4 text-nowrap">{{ $t->createdtime ? \Carbon\Carbon::parse($t->createdtime)->format('d M Y H:i') : '—' }}</td>
                        <td class="px-4">{{ trim(($t->owner_first ?? '') . ' ' . ($t->owner_last ?? '')) ?: 'Unassigned' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-6 d-block mb-2 text-success"></i>
                            No open tickets older than {{ $days }} days.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
