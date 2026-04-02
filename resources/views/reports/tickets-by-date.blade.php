@extends('layouts.app')

@section('title', 'Tickets by date range')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Tickets by date</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Tickets by date range</h1>
            <p class="reports-audit-subtitle mb-0">Fetch support tickets by <strong>created</strong> date (inclusive). Combine with status, search, or assignee as needed.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <a href="{{ route('reports.export.tickets-by-date', array_filter([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'search' => $search,
                'only_with_contact' => $onlyWithContact ? 1 : null,
                'assigned_to' => ($ownerFilter === null && $assignedTo) ? $assignedTo : null,
                'format' => 'xlsx',
            ])) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </a>
            <a href="{{ route('reports.export.tickets-by-date', array_filter([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'search' => $search,
                'only_with_contact' => $onlyWithContact ? 1 : null,
                'assigned_to' => ($ownerFilter === null && $assignedTo) ? $assignedTo : null,
            ])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Print report">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.tickets-by-date') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Date from</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Date to</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="Open" {{ ($status ?? '') === 'Open' ? 'selected' : '' }}>Open</option>
                        <option value="In Progress" {{ ($status ?? '') === 'In Progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="Wait For Response" {{ ($status ?? '') === 'Wait For Response' ? 'selected' : '' }}>Wait For Response</option>
                        <option value="Closed" {{ ($status ?? '') === 'Closed' ? 'selected' : '' }}>Closed</option>
                        <option value="Unassigned" {{ ($status ?? '') === 'Unassigned' ? 'selected' : '' }}>Unassigned (no contact)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Per page</label>
                    <select name="per_page" class="form-select">
                        @foreach([25, 50, 100, 200] as $n)
                        <option value="{{ $n }}" {{ (int)($perPage ?? 50) === $n ? 'selected' : '' }}>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Ticket no, title, contact, policy fields, owner..." value="{{ $search ?? '' }}">
                </div>
                @if($ownerFilter === null)
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Assigned to</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Anyone</option>
                        @foreach(($users ?? []) as $u)
                        <option value="{{ $u->id }}" {{ (int)($assignedTo ?? 0) === (int)$u->id ? 'selected' : '' }}>
                            {{ trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->user_name ?? 'User') }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="only_with_contact" value="1" id="onlyWithContact" {{ !empty($onlyWithContact) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="onlyWithContact">Only tickets linked to a contact</label>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">
                        <i class="bi bi-search me-1"></i>Run report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p class="text-muted small no-print mb-2">
        Showing {{ $tickets->total() }} ticket(s) created between {{ $dateFrom }} and {{ $dateTo }}.
        @if($ownerFilter !== null)
            <span class="badge bg-secondary-subtle text-dark">Filtered to your tickets</span>
        @endif
    </p>

    <div class="reports-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Category</th>
                        <th>Contact</th>
                        <th>Created</th>
                        <th>Assigned to</th>
                        <th>Dept</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                    <tr>
                        <td>
                            <a href="{{ route('tickets.show', $t->ticketid) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $t->ticket_no ?? 'TT'.$t->ticketid }}
                            </a>
                        </td>
                        <td>{{ Str::limit($t->title ?? '—', 55) }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $t->status ?? '—' }}</span></td>
                        <td>{{ $t->priority ?? '—' }}</td>
                        <td>{{ $t->category ?? '—' }}</td>
                        <td>{{ trim(($t->contact_first ?? '').' '.($t->contact_last ?? '')) ?: '—' }}</td>
                        <td class="text-nowrap">{{ $t->createdtime ? \Carbon\Carbon::parse($t->createdtime)->format('d M Y H:i') : '—' }}</td>
                        <td>{{ trim(($t->owner_first ?? '').' '.($t->owner_last ?? '')) ?: 'Unassigned' }}</td>
                        <td>{{ $t->owner_department ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-ticket-perforated display-6 d-block mb-2"></i>
                            No tickets in this range with the current filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
        <div class="card-footer no-print d-flex justify-content-center">
            {{ $tickets->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
