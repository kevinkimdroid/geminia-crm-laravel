@extends('layouts.app')

@section('title', 'Management usage report')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Management usage</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Management usage report</h1>
            <p class="reports-audit-subtitle mb-0">Usage over time to date and the top tracked/reported issue categories.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <a href="{{ route('reports.export.management-usage', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'simple' => 1, 'format' => 'xlsx']) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Simplified Excel
            </a>
            <a href="{{ route('reports.export.management-usage', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'format' => 'xlsx']) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </a>
            <a href="{{ route('reports.export.management-usage', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.management-usage') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Date from</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Date to</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" required>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">
                        <i class="bi bi-search me-1"></i>Run report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Total Reported</p>
                    <h3 class="mb-0">{{ number_format($totalReported ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">Issues reported from {{ $dateFrom }} to {{ $dateTo }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Total Tracked</p>
                    <h3 class="mb-0">{{ number_format($totalTracked ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">Open/in-progress issues in this reporting window</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Top Performer</p>
                    @if(!empty($topPerformer))
                        <h5 class="mb-1">{{ $topPerformer['owner_name'] ?? 'Unassigned' }}</h5>
                        <p class="mb-0 small text-muted">
                            Closed {{ number_format($topPerformer['closed_count'] ?? 0) }} / {{ number_format($topPerformer['assigned_count'] ?? 0) }}
                            ({{ number_format((float)($topPerformer['close_rate'] ?? 0), 1) }}%)
                        </p>
                        <p class="mb-0 small text-muted">Most handled issue: {{ $topPerformer['top_issue'] ?? 'General' }}</p>
                    @else
                        <p class="mb-0 text-muted small">No assignee performance data in this period.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <h6 class="mb-2">Decision Snapshot</h6>
                    <p class="mb-1 small text-muted">Period: {{ $decisionSummary['period'] ?? ($dateFrom.' to '.$dateTo) }}</p>
                    <p class="mb-1"><strong>Health:</strong> {{ $decisionSummary['health_status'] ?? 'No Data' }}</p>
                    <p class="mb-1"><strong>Closure rate:</strong> {{ number_format((float)($decisionSummary['closure_rate'] ?? 0), 1) }}%</p>
                    <p class="mb-0"><strong>Backlog ratio:</strong> {{ number_format((float)($decisionSummary['backlog_ratio'] ?? 0), 1) }}%</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <h6 class="mb-2">Improvements Delivered</h6>
                    <ul class="mb-0">
                        @forelse(($improvementsDone ?? []) as $item)
                            <li>{{ $item }}</li>
                        @empty
                            <li>No measurable improvement captured for this period.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card reports-card mb-4">
        <div class="card-body p-4">
            <h6 class="mb-3">Usage trend over time</h6>
            <canvas id="chart-management-usage" height="120"></canvas>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="reports-table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th colspan="3">Most reported issues</th>
                            </tr>
                            <tr>
                                <th style="width: 64px;">#</th>
                                <th>Issue category</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($mostReported ?? []) as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $row['issue'] ?? 'General' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['count'] ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No data for this period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="reports-table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th colspan="3">Most tracked issues</th>
                            </tr>
                            <tr>
                                <th style="width: 64px;">#</th>
                                <th>Issue category</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($mostTracked ?? []) as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $row['issue'] ?? 'General' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['count'] ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No data for this period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="reports-table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th colspan="8">Who does the most (assignee analytics)</th>
                            </tr>
                            <tr>
                                <th>#</th>
                                <th>Assignee</th>
                                <th class="text-end">Assigned</th>
                                <th class="text-end">Closed</th>
                                <th class="text-end">Active</th>
                                <th class="text-end">Close %</th>
                                <th class="text-end">Avg close hrs</th>
                                <th>Most handled issue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($ownerPerformance ?? []) as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $row['owner_name'] ?? 'Unassigned' }}</td>
                                <td class="text-end">{{ number_format($row['assigned_count'] ?? 0) }}</td>
                                <td class="text-end">{{ number_format($row['closed_count'] ?? 0) }}</td>
                                <td class="text-end">{{ number_format($row['active_count'] ?? 0) }}</td>
                                <td class="text-end">{{ number_format((float)($row['close_rate'] ?? 0), 1) }}%</td>
                                <td class="text-end">{{ isset($row['avg_close_hours']) ? number_format((float)$row['avg_close_hours'], 1) : '—' }}</td>
                                <td>{{ $row['top_issue'] ?? 'General' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No assignee analytics in this period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card reports-card mt-4">
        <div class="card-body">
            <h6 class="mb-2">Management recommendations</h6>
            <ul class="mb-0">
                @forelse(($recommendations ?? []) as $rec)
                    <li>{{ $rec }}</li>
                @empty
                    <li>Not enough data to generate recommendations for this period.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var daily = @json($daily ?? []);
    if (!daily.length) return;

    var labels = daily.map(function (r) { return r.date; });
    var reported = daily.map(function (r) { return r.reported || 0; });
    var closed = daily.map(function (r) { return r.closed || 0; });
    var el = document.getElementById('chart-management-usage');
    if (!el) return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Reported',
                    data: reported,
                    borderColor: '#1A468A',
                    backgroundColor: 'rgba(26, 70, 138, 0.08)',
                    tension: 0.25,
                    fill: true
                },
                {
                    label: 'Closed',
                    data: closed,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.08)',
                    tension: 0.25,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxRotation: 70, minRotation: 45 } }
            }
        }
    });
});
</script>
@endpush
