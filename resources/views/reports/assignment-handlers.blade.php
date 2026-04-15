@extends('layouts.app')

@section('title', 'Assignment Handlers Report')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">Assignment Handlers Report</h1>
        <p class="text-muted mb-0">Created by, checked by, authorized by, and closed by per ticket.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.export.assignment-handlers', array_merge(request()->only(['date_from', 'date_to', 'status']), ['limit' => 50000])) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
        </a>
        <a href="{{ route('reports') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to reports
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.assignment-handlers') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (['Open', 'In Progress', 'Wait For Response', 'Closed', 'Inactive'] as $opt)
                    <option value="{{ $opt }}" {{ ($status ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Per page</label>
                <select name="per_page" class="form-select">
                    @foreach ([25, 50, 100, 200] as $opt)
                    <option value="{{ $opt }}" {{ (int) ($perPage ?? 50) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th>Checked by</th>
                        <th>Authorized by</th>
                        <th>Closed by</th>
                        <th>Created at</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('tickets.show', $row->ticketid) }}" class="text-decoration-none fw-semibold">
                                {{ $row->ticket_no }}
                            </a>
                        </td>
                        <td class="text-truncate" style="max-width: 280px" title="{{ $row->title }}">{{ $row->title ?: 'Untitled' }}</td>
                        <td>
                            <span class="badge text-bg-light border">{{ $row->status ?: '—' }}</span>
                        </td>
                        <td>{{ $row->created_by }}</td>
                        <td>{{ $row->checked_by }}</td>
                        <td>{{ $row->authorized_by }}</td>
                        <td>{{ $row->closed_by }}</td>
                        <td class="text-muted small">{{ $row->createdtime ? date('d M Y, H:i', strtotime($row->createdtime)) : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No tickets found for the selected filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(method_exists($rows, 'links'))
<div class="mt-3">
    {{ $rows->links() }}
</div>
@endif
@endsection
