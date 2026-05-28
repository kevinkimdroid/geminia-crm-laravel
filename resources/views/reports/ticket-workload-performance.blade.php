@extends('layouts.app')

@section('title', 'Ticket Workload Performance')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Ticket Workload Performance</h1>
            <p class="text-muted mb-0">Management monthly matrix: each user should hit at least {{ $target }} tickets every month.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('reports') }}">
                <i class="bi bi-arrow-left me-1"></i> Reports
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.ticket-workload-performance') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Target</label>
                    <input type="number" min="200" max="2000" name="target" class="form-control form-control-sm" value="{{ $target }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                    <a href="{{ route('reports.export.ticket-workload-performance', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'target' => $target, 'format' => 'xlsx']) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel
                    </a>
                    <a href="{{ route('reports.export.ticket-workload-performance', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'target' => $target, 'format' => 'csv']) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-filetype-csv me-1"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><small class="text-muted d-block">Users</small><strong>{{ number_format($summary['users'] ?? 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><small class="text-muted d-block">Normal Tickets</small><strong>{{ number_format($summary['total_normal'] ?? 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><small class="text-muted d-block">Work Tickets</small><strong>{{ number_format($summary['total_work'] ?? 0) }}</strong></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body py-2"><small class="text-muted d-block">Total Worked Across Period</small><strong>{{ number_format($summary['total_worked'] ?? 0) }}</strong></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Section</th>
                        <th class="text-end">Normal Tickets</th>
                        <th class="text-end">Work Tickets</th>
                        @foreach ($months as $month)
                            <th class="text-end">{{ $month['label'] }}</th>
                            <th class="text-end">{{ $month['label'] }} %</th>
                        @endforeach
                        <th class="text-end">Total Worked</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->user_name }}</td>
                            <td>{{ $row->department ?: '—' }}</td>
                            <td class="text-end">{{ number_format((int) ($row->overall_normal ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((int) ($row->overall_work ?? 0)) }}</td>
                            @foreach ($months as $month)
                                @php
                                    $m = $row->monthly[$month['key']] ?? ['total' => 0, 'achievement' => 0];
                                    $met = ((int) ($m['total'] ?? 0)) >= $target;
                                @endphp
                                <td class="text-end {{ $met ? 'table-success' : 'table-danger' }}">
                                    {{ number_format((int) ($m['total'] ?? 0)) }}
                                </td>
                                <td class="text-end {{ $met ? 'table-success' : 'table-danger' }}">
                                    {{ number_format((float) ($m['achievement'] ?? 0), 1) }}
                                </td>
                            @endforeach
                            <td class="text-end fw-semibold">{{ number_format((int) ($row->overall_total ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + (count($months) * 2) }}" class="text-center text-muted py-4">No workload data found for selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
