@extends('layouts.app')

@section('title', 'Ticket Automation Analysis')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Ticket Automation Analysis</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Ticket Automation Analysis</h1>
            <p class="reports-audit-subtitle mb-0">Standalone analysis of normal tickets vs work tickets and what should be automated.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <a href="{{ route('reports.export.ticket-automation-analysis', ['format' => 'xlsx']) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </a>
            <a href="{{ route('reports.export.ticket-automation-analysis', ['format' => 'csv']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <span class="text-muted small">
                <i class="bi bi-clock me-1"></i>Generated: {{ now()->format('l, F j, Y g:i A') }}
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Overall Tickets</p>
                    <h3 class="mb-1">{{ number_format($overallTotal) }}</h3>
                    <p class="mb-0 small text-muted">Combined normal + work tickets</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Overall Closure Rate</p>
                    <h3 class="mb-1">{{ number_format($overallClosureRate, 1) }}%</h3>
                    <p class="mb-0 small text-muted">Closed/Done out of all tickets</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Overall Active Backlog</p>
                    <h3 class="mb-1">{{ number_format($overallBacklogRate, 1) }}%</h3>
                    <p class="mb-0 small text-muted">Open + In Progress + Waiting/Blocked share</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="reports-table-card">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">Normal Tickets (CRM)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($normalByStatus as $status => $count)
                            <tr>
                                <td>{{ $status }}</td>
                                <td class="text-end fw-semibold">{{ number_format($count) }}</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td class="fw-semibold">Total</td>
                                <td class="text-end fw-semibold">{{ number_format($normalTotal) }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Closure rate</td>
                                <td class="text-end">{{ number_format($normalClosureRate, 1) }}%</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Backlog rate</td>
                                <td class="text-end">{{ number_format($normalBacklogRate, 1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="reports-table-card">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">Work Tickets</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workByStatus as $status => $count)
                            <tr>
                                <td>{{ $status }}</td>
                                <td class="text-end fw-semibold">{{ number_format($count) }}</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td class="fw-semibold">Total</td>
                                <td class="text-end fw-semibold">{{ number_format($workTotal) }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Closure rate</td>
                                <td class="text-end">{{ number_format($workClosureRate, 1) }}%</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Backlog rate</td>
                                <td class="text-end">{{ number_format($workBacklogRate, 1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="reports-table-card mb-4">
        <div class="p-3 border-bottom">
            <h6 class="mb-0">Real Issues Driving Backlog (Management Focus)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Issue Theme</th>
                        <th class="text-end">Active Volume</th>
                        <th>Source Mix</th>
                        <th>Most Impacted Owner</th>
                        <th>Recommended Management Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($realIssueInsights ?? []) as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['issue'] }}</td>
                        <td class="text-end">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                        <td>
                            Normal: {{ (int) (($row['source_mix']['Normal'] ?? 0)) }}
                            · Work: {{ (int) (($row['source_mix']['Work'] ?? 0)) }}
                        </td>
                        <td>{{ $row['most_impacted_owner'] }} ({{ (int) ($row['owner_load'] ?? 0) }})</td>
                        <td>{{ $row['action'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No issue insights available yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card reports-card">
        <div class="card-body">
            <h6 class="mb-2">What needs to be automated</h6>
            <ul class="mb-0">
                @foreach($automationRecommendations as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
