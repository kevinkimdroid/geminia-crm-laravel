@extends('layouts.app')

@section('title', 'Broken SLA Report')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav class="mb-2">
            <a href="{{ route('reports') }}" class="text-muted small">Reports</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">Broken SLA</span>
        </nav>
        <h1 class="page-title mb-1">Broken SLA Report</h1>
        <p class="page-subtitle mb-0">Tickets that exceeded their Turnaround Time (TAT).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.export.sla-broken', ['format' => 'xlsx']) }}" class="btn btn-primary">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
        </a>
        <a href="{{ route('reports.export.sla-broken') }}" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="{{ route('settings.crm') }}?section=ticket-sla" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> Configure TAT
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
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Department</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Status</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Contact</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Created</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">TAT</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Hours Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets ?? [] as $t)
                    @php
                        $contactName = trim(($t->contact_first ?? '') . ' ' . ($t->contact_last ?? '')) ?: '—';
                    @endphp
                    <tr>
                        <td class="px-4">
                            <a href="{{ route('tickets.show', $t->ticketid) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $t->ticket_no ?? 'TT' . $t->ticketid }}
                            </a>
                        </td>
                        <td class="px-4">{{ Str::limit($t->title ?? '—', 40) }}</td>
                        <td class="px-4"><span class="badge bg-secondary">{{ $t->category ?? 'General' }}</span></td>
                        <td class="px-4">
                            <span class="badge {{ $t->status === 'Closed' ? 'bg-success' : 'bg-warning' }}">
                                {{ $t->status ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4">{{ $contactName }}</td>
                        <td class="px-4 text-nowrap">{{ $t->createdtime ? \Carbon\Carbon::parse($t->createdtime)->format('d M Y H:i') : '—' }}</td>
                        <td class="px-4">{{ $t->tat_hours ?? 24 }}h</td>
                        <td class="px-4">
                            <span class="text-danger fw-semibold">{{ $t->hours_overdue ?? 0 }}h</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-6 d-block mb-2 text-success"></i>
                            No broken SLAs. All tickets are within their TAT.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
