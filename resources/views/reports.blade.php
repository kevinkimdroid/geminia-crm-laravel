@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle mb-0">Analytics, audit reports, and business intelligence.</p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-clock me-1"></i>Page loaded: {{ now()->format('l, F j, Y g:i A') }}
    </div>
</div>

{{-- Audit & Compliance — primary section for auditors --}}
<div class="reports-section mb-4">
    <h5 class="reports-section-title mb-3">
        <i class="bi bi-shield-check text-primary me-2"></i>Audit &amp; Compliance
    </h5>
    <div class="row g-3">
        <div class="col-lg-4">
            <a href="{{ route('reports.sla-broken') }}" class="text-decoration-none">
                <div class="card reports-audit-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary">SLA</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                        </div>
                        <h6 class="card-title mb-2">Broken SLA Report</h6>
                        <p class="text-muted small mb-0">Tickets that exceeded their department TAT.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-4">
            <a href="{{ route('reports.ticket-aging') }}" class="text-decoration-none">
                <div class="card reports-audit-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary">Aging</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                        </div>
                        <h6 class="card-title mb-2">Ticket Aging Report</h6>
                        <p class="text-muted small mb-0">Open tickets older than 7+ days.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-4">
            <a href="{{ route('reports.tickets-by-date') }}" class="text-decoration-none">
                <div class="card reports-audit-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary">Tickets</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                        </div>
                        <h6 class="card-title mb-2">Tickets by date range</h6>
                        <p class="text-muted small mb-0">Created from / to with filters and Excel export.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-4">
            <a href="{{ route('reports.reassignment-audit') }}" class="text-decoration-none">
                <div class="card reports-audit-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary">Audit trail</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                        </div>
                        <h6 class="card-title mb-2">Reassignment Audit</h6>
                        <p class="text-muted small mb-0">Audit trail of ticket reassignments.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="{{ route('reports.export.sla-broken', ['format' => 'xlsx']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Broken SLA</a>
        <a href="{{ route('reports.export.ticket-aging', ['format' => 'xlsx']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Ticket Aging</a>
        <a href="{{ route('reports.export.tickets-by-date', array_merge(request()->only([]), ['date_from' => now()->startOfMonth()->format('Y-m-d'), 'date_to' => now()->format('Y-m-d'), 'format' => 'xlsx'])) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Tickets by date</a>
        <a href="{{ route('reports.export.reassignment-audit', ['format' => 'xlsx']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Reassignment Audit</a>
    </div>
</div>

{{-- Sales & Pipeline --}}
<h5 class="reports-section-title mb-3">
    <i class="bi bi-graph-up-arrow text-primary me-2"></i>Sales &amp; Pipeline
</h5>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Won Revenue</h6>
                    <a href="{{ route('deals.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                </div>
                <h3 class="reports-stat-value mb-1">KES {{ number_format($wonRevenue ?? 0, 0) }}</h3>
                <p class="text-muted small mb-0 mt-2">Closed deals total</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Pipeline Value</h6>
                    <a href="{{ route('deals.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                </div>
                <h3 class="reports-stat-value mb-1">KES {{ number_format($pipelineValue ?? 0, 0) }}</h3>
                <p class="text-muted small mb-0 mt-2">Active opportunities</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Sales by Person</h6>
                    <div>
                        <a href="{{ route('reports.export.sales-by-person', ['format' => 'xlsx']) }}" class="text-muted me-2" title="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></a>
                        <a href="{{ route('deals.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <p class="text-muted small mb-2">Top performers (closed won)</p>
                <div class="reports-list">
                    @forelse ($salesByPerson ?? [] as $row)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ trim($row->name) ?: 'Unassigned' }}</span>
                        <strong>KES {{ number_format($row->total, 0) }}</strong>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No closed deals yet</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Leads by Source</h6>
                    <a href="{{ route('leads.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                </div>
                @if (count($leadsBySource ?? []) > 0)
                <div class="reports-list">
                    @foreach ($leadsBySource as $source => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $source }}</span>
                        <strong>{{ $cnt }}</strong>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No lead source data yet</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Pipeline by Stage</h6>
                    <div>
                        <a href="{{ route('reports.export.pipeline-by-stage', ['format' => 'xlsx']) }}" class="text-muted me-2" title="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></a>
                        <a href="{{ route('deals.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                @if (count($pipelineByStage ?? []) > 0)
                <div class="reports-list">
                    @foreach ($pipelineByStage as $stage => $data)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $stage }}</span>
                        <span><strong>{{ $data['count'] }}</strong> deals · KES {{ number_format($data['amount'], 0) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No pipeline data yet</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <a href="{{ route('reports.contacts-summary') }}" class="text-decoration-none">
            <div class="card reports-card h-100">
                <div class="card-body p-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                        <h6>Contacts Summary</h6>
                        <i class="bi bi-arrow-right text-muted"></i>
                    </div>
                    <p class="text-muted small mb-0">Total and new contacts overview.</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-lg-4">
        <a href="{{ route('reports.calls-summary') }}" class="text-decoration-none">
            <div class="card reports-card h-100">
                <div class="card-body p-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                        <h6>Calls Summary</h6>
                        <i class="bi bi-arrow-right text-muted"></i>
                    </div>
                    <p class="text-muted small mb-0">PBX call volume and duration.</p>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Support & Overview --}}
<h5 class="reports-section-title mb-3 mt-4">
    <i class="bi bi-ticket-perforated text-primary me-2"></i>Support &amp; Overview
</h5>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Tickets by Status</h6>
                    <a href="{{ route('tickets.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                </div>
                @if (count($ticketsByStatus ?? []) > 0)
                <div class="reports-list">
                    @foreach ($ticketsByStatus as $status => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $status }}</span>
                        <strong>{{ $cnt }}</strong>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No tickets yet</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between mb-3">
                    <h6>Tickets by Category</h6>
                    <a href="{{ route('tickets.index') }}" class="text-muted"><i class="bi bi-arrow-right"></i></a>
                </div>
                @if (count($ticketsByCategory ?? []) > 0)
                <div class="reports-list">
                    @foreach ($ticketsByCategory as $cat => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $cat }}</span>
                        <strong>{{ $cnt }}</strong>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No ticket categories yet</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12">
        <h5 class="mb-3">Analytics Charts</h5>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="card-header-custom mb-3">Pipeline by Stage</h6>
                <canvas id="chart-pipeline" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="card-header-custom mb-3">Tickets by Status</h6>
                <canvas id="chart-tickets-status" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="card-header-custom mb-3">Leads by Source</h6>
                <canvas id="chart-leads-source" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="card-header-custom mb-3">Sales by Person (Top 10)</h6>
                <canvas id="chart-sales-person" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card reports-export-card border-0">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Export to Excel</h6>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('reports.export.all-excel') }}" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export All (Excel)</a>
                    <span class="text-muted small">|</span>
                    <a href="{{ route('reports.export.sla-broken', ['format' => 'xlsx']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Broken SLA (.xlsx)</a>
                    <a href="{{ route('reports.export.ticket-aging', ['format' => 'xlsx']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Ticket Aging (.xlsx)</a>
                    <a href="{{ route('reports.export.reassignment-audit', ['format' => 'xlsx']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Reassignment Audit (.xlsx)</a>
                    <a href="{{ route('reports.export.sales-by-person', ['format' => 'xlsx']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Sales by Person (.xlsx)</a>
                    <a href="{{ route('reports.export.pipeline-by-stage', ['format' => 'xlsx']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Pipeline (.xlsx)</a>
                    <span class="text-muted small">|</span>
                    <a href="{{ route('reports.export.sla-broken') }}" class="btn btn-outline-secondary btn-sm" title="CSV">Broken SLA (.csv)</a>
                    <a href="{{ route('reports.export.ticket-aging') }}" class="btn btn-outline-secondary btn-sm" title="CSV">Ticket Aging (.csv)</a>
                    <a href="{{ route('reports.export.reassignment-audit') }}" class="btn btn-outline-secondary btn-sm" title="CSV">Reassignment Audit (.csv)</a>
                    <a href="{{ route('reports.export.sales-by-person') }}" class="btn btn-outline-secondary btn-sm" title="CSV">Sales (.csv)</a>
                    <a href="{{ route('reports.export.pipeline-by-stage') }}" class="btn btn-outline-secondary btn-sm" title="CSV">Pipeline (.csv)</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.reports-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: transform .2s, box-shadow .2s; }
.reports-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(14, 67, 133, 0.1); }
.reports-stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary, #0E4385); }
.reports-export-card { background: rgba(14, 67, 133, 0.04); border-radius: 16px; }
.reports-section-title { font-weight: 600; color: var(--geminia-text, #1e293b); font-size: 1rem; }
.reports-audit-card {
    border-radius: 14px; border: 1px solid rgba(26, 74, 138, 0.12);
    transition: transform .2s, box-shadow .2s; background: #fff;
}
.reports-audit-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26, 74, 138, 0.12); }
.reports-audit-card .card-title { font-weight: 600; color: var(--geminia-text); }
</style>

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush

@push('scripts')
@php $salesChart = collect($salesByPerson ?? [])->take(10)->map(fn($r) => ['name' => trim($r->name ?? '') ?: 'Unassigned', 'total' => (float)($r->total ?? 0)])->values()->toArray(); @endphp
<script>
document.addEventListener('DOMContentLoaded', function() {
    var colors = ['#1A468A','#2563eb','#3b82f6','#60a5fa','#93c5fd','#0ea5e9','#06b6d4','#14b8a6'];
    var pipelineData = @json($pipelineByStage ?? []);
    var ticketsStatusData = @json($ticketsByStatus ?? []);
    var leadsSourceData = @json($leadsBySource ?? []);
    var salesPersonData = @json($salesChart ?? []);

    function makeBar(el, labels, values, isHorizontal) {
        if (!el || labels.length === 0) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Count', data: values, backgroundColor: colors.slice(0, labels.length), borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: true, indexAxis: isHorizontal ? 'y' : 'x', plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
    function makeDoughnut(el, labels, values) {
        if (!el || labels.length === 0) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
    var pipelineLabels = Object.keys(pipelineData);
    var pipelineCounts = pipelineLabels.map(function(k) { return pipelineData[k].count || 0; });
    var ticketLabels = Object.keys(ticketsStatusData);
    var ticketCounts = ticketLabels.map(function(k) { return ticketsStatusData[k] || 0; });
    var leadLabels = Object.keys(leadsSourceData);
    var leadCounts = leadLabels.map(function(k) { return leadsSourceData[k] || 0; });
    var salesLabels = salesPersonData.map(function(r) { return r.name; });
    var salesValues = salesPersonData.map(function(r) { return r.total; });
    makeBar(document.getElementById('chart-pipeline'), pipelineLabels, pipelineCounts, false);
    makeDoughnut(document.getElementById('chart-tickets-status'), ticketLabels, ticketCounts);
    makeDoughnut(document.getElementById('chart-leads-source'), leadLabels, leadCounts);
    makeBar(document.getElementById('chart-sales-person'), salesLabels, salesValues, true);
});
</script>
@endpush
@endsection
