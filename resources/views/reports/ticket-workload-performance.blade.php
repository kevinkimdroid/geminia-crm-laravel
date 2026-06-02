@extends('layouts.app')

@section('title', 'Ticket Workload Performance')

@push('head')
<style>
    .twp-hero {
        background: linear-gradient(135deg, #0E4385 0%, #1A468A 45%, #33B4E3 130%);
        border-radius: 18px;
        color: #fff;
        padding: 1.5rem 1.75rem;
        box-shadow: 0 12px 30px rgba(14, 67, 133, 0.25);
    }
    .twp-hero .twp-target-pill {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        padding: 0.3rem 0.85rem;
        font-weight: 600;
        font-size: 0.8rem;
    }
    .twp-kpi {
        border: none;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .twp-kpi:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(15, 23, 42, 0.1); }
    .twp-kpi .twp-kpi-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; }
    .twp-kpi .twp-kpi-value { font-size: 1.7rem; font-weight: 700; color: #0E4385; line-height: 1.1; }
    .twp-kpi .twp-kpi-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }

    .twp-card { border: none; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); }
    .twp-card .card-header { background: transparent; border-bottom: 1px solid #eef2f7; font-weight: 600; }

    .twp-matrix-wrap { max-height: min(72vh, 920px); overflow: auto; border-radius: 0 0 16px 16px; }
    .twp-matrix { border-collapse: separate; border-spacing: 0; margin: 0; font-size: 0.85rem; }
    .twp-matrix thead th {
        position: sticky; top: 0; z-index: 3; background: #f1f5f9; color: #475569;
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;
        box-shadow: inset 0 -1px 0 #e2e8f0;
    }
    .twp-matrix th.twp-sticky-col, .twp-matrix td.twp-sticky-col {
        position: sticky; left: 0; z-index: 2; background: #fff; box-shadow: inset -1px 0 0 #e2e8f0; min-width: 180px;
    }
    .twp-matrix thead th.twp-sticky-col { z-index: 4; background: #f1f5f9; }
    .twp-matrix tbody tr:hover td { background: #f8fafc; }
    .twp-matrix tbody tr:hover td.twp-sticky-col { background: #f8fafc; }
    .twp-cell-met { background: rgba(34, 197, 94, 0.16); color: #166534; font-weight: 600; }
    .twp-cell-miss { background: rgba(239, 68, 68, 0.14); color: #991b1b; font-weight: 600; }
    .twp-cell-empty { color: #cbd5e1; }
    .twp-rank { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #eef2f7; color: #475569; font-size: 0.72rem; font-weight: 700; margin-right: 0.5rem; }
    .twp-rank.gold { background: #fde68a; color: #92400e; }
    .twp-rank.silver { background: #e2e8f0; color: #475569; }
    .twp-rank.bronze { background: #fed7aa; color: #9a3412; }
    .twp-consistency-bar { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .twp-consistency-bar > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e, #16a34a); }
    .twp-search { max-width: 280px; }
    .twp-leader-row { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
    .twp-leader-row:last-child { border-bottom: none; }
    .twp-mini-bar { height: 8px; border-radius: 999px; background: #eef2f7; overflow: hidden; width: 120px; }
    .twp-mini-bar > span { display: block; height: 100%; background: linear-gradient(90deg, #1A468A, #33B4E3); }
</style>
@endpush

@section('content')
@php
    $monthCount = max(1, count($months));
    $compliance = (float) ($summary['compliance_rate'] ?? 0);
    $maxTopTotal = collect($summary['top_performers'] ?? [])->max('total') ?: 1;
@endphp
<div class="container-fluid py-3">
    {{-- Hero --}}
    <div class="twp-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('reports') }}" class="text-white-50 text-decoration-none">Reports</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Workload Performance</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-1">Ticket Workload Performance</h1>
                <p class="mb-2 text-white-50">
                    {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
                    · {{ $monthCount }} month{{ $monthCount === 1 ? '' : 's' }} · normal + work tickets, counted uniquely per user
                </p>
                <span class="twp-target-pill"><i class="bi bi-bullseye me-1"></i>Minimum target: {{ number_format($target) }} tickets / user / month</span>
            </div>
            <div class="text-end">
                <div class="display-6 fw-bold">{{ number_format($compliance, 1) }}%</div>
                <div class="text-white-50 small">of users hit target every month</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="twp-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.ticket-workload-performance') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Monthly target</label>
                    <input type="number" min="200" max="2000" name="target" class="form-control form-control-sm" value="{{ $target }}">
                </div>
                <div class="col-6 col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <a href="{{ route('reports.export.ticket-workload-performance', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'target' => $target, 'format' => 'xlsx']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>
                    <a href="{{ route('reports.export.ticket-workload-performance', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'target' => $target, 'format' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(26,70,138,0.1); color:#1A468A;"><i class="bi bi-people"></i></div>
                <div><div class="twp-kpi-label">Active users</div><div class="twp-kpi-value">{{ number_format($summary['users'] ?? 0) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(34,197,94,0.12); color:#16a34a;"><i class="bi bi-patch-check"></i></div>
                <div><div class="twp-kpi-label">Hit target every month</div><div class="twp-kpi-value text-success">{{ number_format($summary['fully_compliant'] ?? 0) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(245,158,11,0.14); color:#b45309;"><i class="bi bi-speedometer2"></i></div>
                <div><div class="twp-kpi-label">Avg / user / month</div><div class="twp-kpi-value">{{ number_format($summary['avg_per_user_month'] ?? 0, 1) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(51,180,227,0.14); color:#0E4385;"><i class="bi bi-ticket-detailed"></i></div>
                <div><div class="twp-kpi-label">Normal tickets</div><div class="twp-kpi-value">{{ number_format($summary['total_normal'] ?? 0) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(124,58,237,0.12); color:#6d28d9;"><i class="bi bi-kanban"></i></div>
                <div><div class="twp-kpi-label">Work tickets</div><div class="twp-kpi-value">{{ number_format($summary['total_work'] ?? 0) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-xl">
            <div class="twp-kpi"><div class="card-body d-flex align-items-center gap-3">
                <div class="twp-kpi-icon" style="background: rgba(15,23,42,0.08); color:#0f172a;"><i class="bi bi-collection"></i></div>
                <div><div class="twp-kpi-label">Total worked</div><div class="twp-kpi-value">{{ number_format($summary['total_worked'] ?? 0) }}</div></div>
            </div></div>
        </div>
    </div>

    {{-- Charts + leaderboard --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="twp-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-trophy text-warning me-1"></i>Top performers</span>
                    <span class="badge bg-light text-muted">Top 10</span>
                </div>
                <div class="card-body">
                    @forelse ($summary['top_performers'] ?? [] as $i => $tp)
                        <div class="twp-leader-row">
                            <div class="d-flex align-items-center">
                                <span class="twp-rank {{ $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) }}">{{ $i + 1 }}</span>
                                <span class="small fw-medium text-truncate" style="max-width: 160px;">{{ $tp['name'] }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="twp-mini-bar"><span style="width: {{ max(4, round(($tp['total'] / $maxTopTotal) * 100)) }}%;"></span></span>
                                <strong class="small">{{ number_format($tp['total']) }}</strong>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No data yet for this period.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="twp-card h-100">
                <div class="card-header"><i class="bi bi-graph-up-arrow text-primary me-1"></i>Monthly team output vs target line</div>
                <div class="card-body"><canvas id="twpTrendChart" height="120"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Matrix --}}
    <div class="twp-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span><i class="bi bi-grid-3x3-gap me-1 text-muted"></i>Per-user monthly matrix</span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill twp-cell-met">Target met</span>
                <span class="badge rounded-pill twp-cell-miss">Below target</span>
                <input type="search" id="twpSearch" class="form-control form-control-sm twp-search" placeholder="Search user or section…" autocomplete="off">
            </div>
        </div>
        <div class="twp-matrix-wrap">
            <table class="table twp-matrix mb-0">
                <thead>
                    <tr>
                        <th class="twp-sticky-col">User</th>
                        <th>Section</th>
                        <th class="text-end">Consistency</th>
                        @foreach ($months as $month)
                            <th class="text-end">{{ $month['label'] }}</th>
                        @endforeach
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody id="twpTableBody">
                    @forelse ($rows as $idx => $row)
                        <tr class="twp-row" data-search="{{ strtolower(($row->user_name ?? '') . ' ' . ($row->department ?? '')) }}">
                            <td class="twp-sticky-col">
                                <span class="twp-rank {{ $idx === 0 ? 'gold' : ($idx === 1 ? 'silver' : ($idx === 2 ? 'bronze' : '')) }}">{{ $idx + 1 }}</span>
                                <span class="fw-medium">{{ $row->user_name }}</span>
                            </td>
                            <td class="text-muted">{{ $row->department ?: '—' }}</td>
                            <td class="text-end" style="min-width: 130px;">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <span class="small text-muted">{{ (int) $row->months_met }}/{{ (int) $row->months_total }}</span>
                                    <span class="twp-consistency-bar" style="width: 64px;"><span style="width: {{ (float) ($row->consistency_percent ?? 0) }}%;"></span></span>
                                </div>
                            </td>
                            @foreach ($months as $month)
                                @php $m = $row->monthly[$month['key']] ?? ['total' => 0, 'met' => false]; @endphp
                                <td class="text-end {{ ($m['total'] ?? 0) > 0 ? ($m['met'] ? 'twp-cell-met' : 'twp-cell-miss') : 'twp-cell-empty' }}"
                                    title="{{ $month['label'] }} · {{ number_format((float) ($m['achievement'] ?? 0), 1) }}% of target">
                                    {{ number_format((int) ($m['total'] ?? 0)) }}
                                </td>
                            @endforeach
                            <td class="text-end fw-bold">{{ number_format((int) ($row->overall_total ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($months) }}" class="text-center text-muted py-5">
                                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                No workload data found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->count() > 0)
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td class="twp-sticky-col">TOTAL ({{ $rows->count() }} users)</td>
                            <td></td>
                            <td></td>
                            @foreach ($months as $month)
                                <td class="text-end">{{ number_format((int) $rows->sum(fn ($r) => (int) ($r->monthly[$month['key']]['total'] ?? 0))) }}</td>
                            @endforeach
                            <td class="text-end">{{ number_format((int) $rows->sum('overall_total')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
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
    // Live search filter
    var search = document.getElementById('twpSearch');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#twpTableBody .twp-row'));
    if (search) {
        search.addEventListener('input', function () {
            var q = (this.value || '').trim().toLowerCase();
            rows.forEach(function (tr) {
                var hay = tr.getAttribute('data-search') || '';
                tr.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // Monthly trend chart
    var trend = @json($summary['monthly_trend'] ?? []);
    var el = document.getElementById('twpTrendChart');
    if (el && trend.length && typeof Chart !== 'undefined') {
        var labels = trend.map(function (t) { return t.label; });
        var totals = trend.map(function (t) { return t.total; });
        var metUsers = trend.map(function (t) { return t.met_users; });
        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { type: 'bar', label: 'Total tickets worked', data: totals, backgroundColor: 'rgba(26,70,138,0.75)', borderRadius: 6, yAxisID: 'y' },
                    { type: 'line', label: 'Users meeting target', data: metUsers, borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.3, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Tickets' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Users' } }
                }
            }
        });
    }
});
</script>
@endpush
