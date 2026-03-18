@extends('layouts.app')

@section('title', 'Dashboard')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $userName = $currentUserName ?? null;
    $firstName = $userName ? explode(' ', trim($userName))[0] : null;
    $tc = $ticketCounts ?? [];
    $open = $tc['Open'] ?? $tc['open'] ?? 0;
    $inProgress = $tc['In Progress'] ?? $tc['InProgress'] ?? 0;
    $waitResp = $tc['Wait For Response'] ?? 0;
    $closed = $tc['Closed'] ?? $tc['closed'] ?? 0;
    $max = max(1, $open, $inProgress, $waitResp, $closed);
    $ticketOpen = $open + $inProgress + $waitResp;
    $ticketClosed = $closed;
    $ticketTotal = max(1, $ticketOpen + $ticketClosed);
    $ticketResolvedPct = round(($ticketClosed / $ticketTotal) * 100);
@endphp

<div class="dashboard">
    {{-- Header: compact one-liner --}}
    <header class="dashboard-header">
        <div class="dashboard-header-left">
            <h1 class="dashboard-title">
                <span class="dashboard-greeting">{{ $greeting }}{{ $firstName ? ',' : '' }}</span>
                <span class="dashboard-name">{{ $firstName ?? 'Welcome back' }}</span>
            </h1>
            <p class="dashboard-subtitle">CRM overview · {{ now()->format('l, F j') }}</p>
        </div>
        <div class="dashboard-header-right">
            <div class="dropdown">
                <button class="dashboard-header-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots"></i> More
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('tickets.index') }}"><i class="bi bi-ticket-perforated me-2"></i>Tickets</a></li>
                    <li><a class="dropdown-item" href="{{ route('reports') }}"><i class="bi bi-currency-dollar me-2"></i>Revenue</a></li>
                    <li><a class="dropdown-item" href="{{ route('activities.index') }}"><i class="bi bi-exclamation-triangle me-2"></i>Overdue</a></li>
                </ul>
            </div>
            <a href="{{ route('reports') }}" class="dashboard-header-btn dashboard-header-btn-primary">
                <i class="bi bi-download"></i> Export
            </a>
        </div>
    </header>

    {{-- KPI strip --}}
    <section class="dashboard-kpis{{ (($clientsCountDeferred ?? false) || (isset($clientsCount) && $clientsCount !== null)) ? '' : ' dashboard-kpis-4' }}">
        <a href="{{ route('deals.index') }}" class="dashboard-kpi dashboard-kpi-featured">
            <div class="dashboard-kpi-icon"><i class="bi bi-currency-dollar"></i></div>
            <div class="dashboard-kpi-content">
                <span class="dashboard-kpi-label">Pipeline Value</span>
                <span class="dashboard-kpi-value">KES {{ number_format($pipelineValue ?? 0, 0) }}</span>
                <span class="dashboard-kpi-meta">{{ number_format($dealsCount ?? 0) }} active deals</span>
            </div>
        </a>
        <a href="{{ route('contacts.index') }}" class="dashboard-kpi">
            <span class="dashboard-kpi-value" id="contactsCountValue">{{ $contactsCountDeferred ?? false ? '—' : number_format($contactsCount ?? 0) }}</span>
            <span class="dashboard-kpi-label">Contacts</span>
        </a>
        <a href="{{ route('leads.index') }}" class="dashboard-kpi">
            <span class="dashboard-kpi-value">{{ number_format($leadsCount ?? 0) }}</span>
            <span class="dashboard-kpi-label">Leads</span>
            @if(($leadsTodayCount ?? 0) > 0)
            <span class="dashboard-kpi-badge">{{ $leadsTodayCount }} new</span>
            @endif
        </a>
        @if(($clientsCountDeferred ?? false) || (isset($clientsCount) && $clientsCount !== null))
        <div class="dashboard-kpi dashboard-kpi-static" id="clientsStatCard">
            <span class="dashboard-kpi-value" id="clientsCountValue">{{ $clientsCountDeferred ?? false ? '...' : number_format($clientsCount ?? 0) }}</span>
            <span class="dashboard-kpi-label">Clients</span>
            <a href="{{ route('support.customers') }}" class="dashboard-kpi-link">View</a>
        </div>
        @endif
        <a href="{{ route('deals.index') }}" class="dashboard-kpi">
            <span class="dashboard-kpi-value">{{ number_format($dealsCount ?? 0) }}</span>
            <span class="dashboard-kpi-label">Active Deals</span>
        </a>
    </section>

    {{-- Quick actions: moved up for immediate access --}}
    <section class="dashboard-actions dashboard-actions-top">
        <span class="dashboard-actions-label">Quick actions</span>
        <div class="dashboard-actions-btns">
            @if((isset($clientsCount) && $clientsCount !== null) || ($clientsCountDeferred ?? false))
            <a href="{{ route('support.customers') }}" class="dashboard-action-btn"><i class="bi bi-people"></i> Clients</a>
            <a href="{{ route('support.serve-client') }}" class="dashboard-action-btn dashboard-action-btn-primary"><i class="bi bi-person-plus"></i> Serve Client</a>
            @if(in_array(config('erp.clients_view_source', 'crm'), ['erp_http', 'erp_sync']))
            <a href="{{ route('support.customers', ['system' => 'group']) }}" class="dashboard-action-btn"><i class="bi bi-people-fill"></i> Group Life</a>
            <a href="{{ route('support.customers', ['system' => 'individual']) }}" class="dashboard-action-btn"><i class="bi bi-person-fill"></i> Individual</a>
            @endif
            @endif
            @if($pbxCanCall ?? false)
            <a href="{{ route('tools.pbx-manager') }}" class="dashboard-action-btn"><i class="bi bi-telephone"></i> Call</a>
            @endif
            <a href="{{ route('tickets.create') }}" class="dashboard-action-btn dashboard-action-btn-primary"><i class="bi bi-ticket-perforated"></i> New Ticket</a>
            <a href="{{ route('leads.create') }}" class="dashboard-action-btn"><i class="bi bi-plus-circle"></i> Add Lead</a>
            <a href="{{ route('contacts.index') }}" class="dashboard-action-btn"><i class="bi bi-person"></i> Contacts</a>
            <a href="{{ route('deals.index') }}" class="dashboard-action-btn"><i class="bi bi-currency-dollar"></i> Deals</a>
        </div>
    </section>

    {{-- Section: Needs Attention (urgent first) --}}
    <section class="dashboard-section">
        <h2 class="dashboard-section-title"><i class="bi bi-exclamation-triangle-fill"></i> Needs Attention</h2>
        <div class="dashboard-section-grid">
            <div class="dashboard-card dashboard-card-overdue">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-exclamation-triangle"></i> Overdue Activities</h3>
                    <div class="dashboard-card-actions">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Mine</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item active" href="#">Mine</a></li>
                                <li><a class="dropdown-item" href="{{ route('activities.index') }}">View all</a></li>
                            </ul>
                        </div>
                        <a href="{{ route('activities.index') }}" class="dashboard-card-link">View</a>
                    </div>
                </div>
                <div class="dashboard-list dashboard-list-overdue">
                    @forelse(($overdueActivities ?? []) as $activity)
                        <div class="dashboard-list-row dashboard-list-row-overdue">
                            <i class="bi bi-exclamation-circle"></i>
                            <div>
                                <span>{{ $activity['subject'] }}</span>
                                <small>{{ $activity['due_date'] ? \Carbon\Carbon::parse($activity['due_date'])->diffForHumans() : '—' }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="dashboard-empty">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>No overdue activities</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Support & Tickets --}}
    <section class="dashboard-section">
        <h2 class="dashboard-section-title"><i class="bi bi-headset"></i> Support & Tickets</h2>
        <div class="dashboard-section-grid dashboard-section-grid-2">
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-bar-chart"></i> Ticket Statistics</h3>
                    <a href="{{ route('tickets.index') }}" class="dashboard-card-link">View all</a>
                </div>
                <div class="dashboard-ticket-grid">
                    <a href="{{ route('tickets.index', ['list' => 'Open']) }}" class="dashboard-ticket-item">
                        <span class="dashboard-ticket-num">{{ $open }}</span>
                        <span class="dashboard-ticket-lbl">Open</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($open/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'In Progress']) }}" class="dashboard-ticket-item">
                        <span class="dashboard-ticket-num">{{ $inProgress }}</span>
                        <span class="dashboard-ticket-lbl">In Progress</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($inProgress/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'Wait For Response']) }}" class="dashboard-ticket-item">
                        <span class="dashboard-ticket-num">{{ $waitResp }}</span>
                        <span class="dashboard-ticket-lbl">Waiting</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($waitResp/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'Closed']) }}" class="dashboard-ticket-item">
                        <span class="dashboard-ticket-num">{{ $closed }}</span>
                        <span class="dashboard-ticket-lbl">Closed</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($closed/$max)*100 }}%"></div></div>
                    </a>
                </div>
            </div>

            {{-- Ticket Resolution --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-pie-chart-fill"></i> Ticket Resolution</h3>
                    <a href="{{ route('tickets.index') }}" class="dashboard-card-link">View all</a>
                </div>
                <div class="dashboard-donut">
                    <div class="dashboard-donut-chart">
                        <svg viewBox="0 0 36 36">
                            <path class="dashboard-donut-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="dashboard-donut-resolved" stroke-dasharray="{{ $ticketResolvedPct }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="dashboard-donut-pending" stroke-dasharray="{{ 100 - $ticketResolvedPct }}, 100" stroke-dashoffset="{{ -$ticketResolvedPct }}" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <div class="dashboard-donut-center">{{ $ticketResolvedPct }}%</div>
                    </div>
                    <div class="dashboard-donut-legend">
                        <span><i style="background:#5a9b7d"></i> Closed {{ $ticketClosed }}</span>
                        <span><i style="background:#8eb8d4"></i> Open {{ $ticketOpen }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Sales & Pipeline --}}
    <section class="dashboard-section">
        <h2 class="dashboard-section-title"><i class="bi bi-currency-dollar"></i> Sales & Pipeline</h2>
        <div class="dashboard-section-grid dashboard-section-grid-3">
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-currency-dollar"></i> Revenue by Salesperson</h3>
                    <a href="{{ route('reports') }}" class="dashboard-card-link">View</a>
                </div>
                @php $salesByPerson = $salesByPerson ?? collect(); @endphp
                @if ($salesByPerson->isNotEmpty())
                    <div class="dashboard-list">
                        @foreach ($salesByPerson->take(5) as $row)
                            <div class="dashboard-list-row">
                                <span>{{ $row->name }}</span>
                                <span class="dashboard-list-amount">KES {{ number_format($row->total ?? 0, 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty">
                        <i class="bi bi-currency-dollar"></i>
                        <span>No opportunities matched</span>
                        <a href="{{ route('deals.index') }}" class="btn btn-sm btn-primary mt-2">View Deals</a>
                    </div>
                @endif
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-pie-chart"></i> Leads by Source</h3>
                    <a href="{{ route('leads.index') }}" class="dashboard-card-link">View</a>
                </div>
                @php $leadsBySource = $leadsBySource ?? []; $totalLeads = array_sum($leadsBySource); @endphp
                @if (count($leadsBySource) > 0)
                    <div class="dashboard-bars">
                        @foreach ($leadsBySource as $source => $cnt)
                            <div class="dashboard-bar">
                                <span class="dashboard-bar-label">{{ $source }}</span>
                                <div class="dashboard-bar-track"><div class="dashboard-bar-fill" style="width:{{ $totalLeads > 0 ? ($cnt/$totalLeads)*100 : 0 }}%"></div></div>
                                <span class="dashboard-bar-count">{{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty"><i class="bi bi-pie-chart"></i><span>No data yet</span></div>
                @endif
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h3 class="dashboard-card-title"><i class="bi bi-calendar-check"></i> Deals Closing Soon</h3>
                    <a href="{{ route('deals.index') }}" class="dashboard-card-link">View</a>
                </div>
                @php $dealsClosingSoon = $dealsClosingSoon ?? collect(); @endphp
                @if ($dealsClosingSoon->isNotEmpty())
                    <div class="dashboard-list">
                        @foreach ($dealsClosingSoon->take(5) as $deal)
                            <a href="{{ route('deals.show', $deal->potentialid) }}" class="dashboard-list-row dashboard-list-row-link">
                                <div>
                                    <strong>{{ $deal->potentialname }}</strong>
                                    <small>{{ $deal->closingdate ? \Carbon\Carbon::parse($deal->closingdate)->format('M j, Y') : '—' }}</small>
                                </div>
                                <span class="dashboard-list-badge">KES {{ number_format($deal->amount ?? 0, 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty"><i class="bi bi-calendar-x"></i><span>No deals in 30 days</span><a href="{{ route('deals.index') }}" class="btn btn-sm btn-primary mt-2">View Deals</a></div>
                @endif
            </div>
        </div>
    </section>
</div>

<style>
:root {
    --dash-bg: #faf8f5;
    --dash-card: #ffffff;
    --dash-card-hover: #fefefe;
    --dash-primary: #1a3a52;
    --dash-primary-light: #2d5a7b;
    --dash-accent: #c9a227;
    --dash-accent-soft: rgba(201, 162, 39, 0.12);
    --dash-text: #2c3e50;
    --dash-text-soft: #5a6c7d;
    --dash-border: rgba(44, 62, 80, 0.08);
    --dash-success: #2d7d6b;
    --dash-warn: #b45309;
    --dash-danger: #c0392b;
    --dash-radius: 14px;
    --dash-radius-lg: 20px;
    --dash-shadow: 0 2px 12px rgba(26, 58, 82, 0.06);
    --dash-shadow-hover: 0 12px 40px rgba(26, 58, 82, 0.12);
}

.dashboard {
    max-width: 1320px; margin: 0 auto; padding: 2rem 2.25rem;
    font-family: 'DM Sans', system-ui, sans-serif;
    background: var(--dash-bg);
    min-height: 100%;
    position: relative;
    -webkit-font-smoothing: antialiased;
}
.dashboard::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 320px;
    background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(26, 58, 82, 0.06), transparent 70%);
    pointer-events: none;
}

.dashboard-header {
    display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
    gap: 1.25rem; margin-bottom: 2rem; padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--dash-border);
    position: relative;
}
.dashboard-title {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 1.85rem; font-weight: 700; color: var(--dash-text); margin: 0 0 0.25rem;
    letter-spacing: -0.02em; line-height: 1.25;
}
.dashboard-greeting { color: var(--dash-text-soft); font-weight: 500; font-family: 'DM Sans', sans-serif; }
.dashboard-name { color: var(--dash-primary); }
.dashboard-subtitle { font-size: 0.9rem; color: var(--dash-text-soft); margin: 0; }
.dashboard-header-right { display: flex; align-items: center; gap: 0.5rem; }
.dashboard-header-btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; border-radius: var(--dash-radius);
    background: var(--dash-card); border: 1px solid var(--dash-border); color: var(--dash-text-soft);
    text-decoration: none; transition: all 0.25s ease;
    box-shadow: var(--dash-shadow);
}
.dashboard-header-btn:hover {
    border-color: rgba(26, 58, 82, 0.2); color: var(--dash-primary);
    box-shadow: 0 4px 16px rgba(26, 58, 82, 0.08);
}
.dashboard-header-btn-primary {
    background: var(--dash-primary) !important;
    border: none !important; color: #fff !important;
}
.dashboard-header-btn-primary:hover {
    background: var(--dash-primary-light) !important;
    box-shadow: 0 6px 20px rgba(26, 58, 82, 0.25);
}

.dashboard-kpis { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 2rem; position: relative; }
.dashboard-kpis.dashboard-kpis-4 { grid-template-columns: 1.4fr 1fr 1fr 1fr; }
@media (max-width: 992px) { .dashboard-kpis, .dashboard-kpis.dashboard-kpis-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .dashboard-kpis, .dashboard-kpis.dashboard-kpis-4 { grid-template-columns: 1fr; } }
.dashboard-kpi {
    background: var(--dash-card); border: 1px solid var(--dash-border); border-radius: var(--dash-radius-lg);
    padding: 1.35rem 1.5rem;
    display: flex; flex-direction: column; align-items: flex-start; text-decoration: none; color: inherit;
    transition: all 0.3s ease; position: relative;
    box-shadow: var(--dash-shadow);
}
.dashboard-kpi:hover {
    border-color: rgba(26, 58, 82, 0.15);
    box-shadow: var(--dash-shadow-hover);
    transform: translateY(-2px);
}
.dashboard-kpi-featured {
    background: linear-gradient(145deg, var(--dash-primary) 0%, #1e4a6a 100%);
    border: none; color: #fff !important;
    flex-direction: row; align-items: center; gap: 1.25rem;
    box-shadow: 0 8px 32px rgba(26, 58, 82, 0.2);
}
.dashboard-kpi-featured:hover {
    color: #fff !important;
    background: linear-gradient(145deg, var(--dash-primary-light) 0%, #2a6b8f 100%);
    box-shadow: 0 12px 40px rgba(26, 58, 82, 0.28);
    transform: translateY(-3px);
}
.dashboard-kpi-icon {
    width: 56px; height: 56px; background: rgba(255,255,255,0.15); border-radius: 16px;
    display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;
    backdrop-filter: blur(10px);
}
.dashboard-kpi-content { display: flex; flex-direction: column; gap: 0.2rem; }
.dashboard-kpi-label {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--dash-text-soft);
}
.dashboard-kpi-featured .dashboard-kpi-label { color: rgba(255,255,255,0.85); }
.dashboard-kpi-value {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 1.6rem; font-weight: 700; color: var(--dash-text); line-height: 1.2;
}
.dashboard-kpi-featured .dashboard-kpi-value { font-size: 1.7rem; color: #fff; }
.dashboard-kpi-meta { font-size: 0.8rem; color: rgba(255,255,255,0.9); }
.dashboard-kpi-badge {
    position: absolute; top: 0.75rem; right: 0.75rem;
    padding: 0.25rem 0.6rem; background: var(--dash-accent); color: #fff;
    font-size: 0.65rem; font-weight: 700; border-radius: 8px;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.dashboard-kpi-static { cursor: default; }
.dashboard-kpi-link { font-size: 0.75rem; font-weight: 600; color: var(--dash-primary); margin-top: 0.4rem; }

.dashboard-section { margin-bottom: 2.25rem; position: relative; }
.dashboard-section:last-child { margin-bottom: 0; }
.dashboard-section-title {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 0.95rem; font-weight: 600; color: var(--dash-text-soft);
    letter-spacing: 0.02em; margin: 0 0 1rem;
    display: flex; align-items: center; gap: 0.6rem;
}
.dashboard-section-title i {
    color: var(--dash-accent); font-size: 1rem;
    width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
    background: var(--dash-accent-soft); border-radius: 8px;
}
.dashboard-section-grid { display: grid; gap: 1.25rem; }
.dashboard-section-grid-2 { grid-template-columns: 1fr 1fr; }
.dashboard-section-grid-3 { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 1200px) { .dashboard-section-grid-3 { grid-template-columns: 1fr 1fr; } }
@media (max-width: 768px) {
    .dashboard-section-grid-2, .dashboard-section-grid-3 { grid-template-columns: 1fr; }
}

.dashboard-card {
    background: var(--dash-card); border: 1px solid var(--dash-border); border-radius: var(--dash-radius-lg);
    padding: 1.5rem 1.6rem;
    box-shadow: var(--dash-shadow);
    transition: all 0.3s ease;
}
.dashboard-card:hover {
    box-shadow: var(--dash-shadow-hover);
    border-color: rgba(26, 58, 82, 0.1);
}
.dashboard-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.dashboard-card-title {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 1.05rem; font-weight: 600; color: var(--dash-text); margin: 0;
    display: flex; align-items: center; gap: 0.5rem;
}
.dashboard-card-title i {
    color: var(--dash-primary); font-size: 1rem;
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    background: var(--dash-accent-soft); border-radius: 10px;
}
.dashboard-card-link {
    font-size: 0.8rem; font-weight: 600; color: var(--dash-primary); text-decoration: none;
    padding: 0.4rem 0.85rem; border-radius: 10px;
    transition: all 0.2s; background: transparent;
}
.dashboard-card-link:hover { background: var(--dash-accent-soft); }
.dashboard-card-actions { display: flex; align-items: center; gap: 0.5rem; }
.dashboard-card .btn-outline-secondary {
    font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.85rem; border-radius: 10px;
    border-color: var(--dash-border); color: var(--dash-text-soft); background: var(--dash-card);
}

.dashboard-ticket-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.dashboard-ticket-item {
    background: linear-gradient(180deg, #fbfaf8 0%, #f5f3ef 100%);
    border: 1px solid var(--dash-border); border-radius: 12px; padding: 1.15rem;
    text-align: center; text-decoration: none; color: inherit;
    transition: all 0.25s ease;
}
.dashboard-ticket-item:hover {
    background: var(--dash-card);
    border-color: rgba(26, 58, 82, 0.12);
    box-shadow: 0 4px 16px rgba(26, 58, 82, 0.08);
}
.dashboard-ticket-num {
    font-family: 'Fraunces', Georgia, serif;
    display: block; font-size: 1.6rem; font-weight: 700; color: var(--dash-text);
}
.dashboard-ticket-lbl {
    display: block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    color: var(--dash-text-soft); margin-bottom: 0.6rem; letter-spacing: 0.05em;
}
.dashboard-ticket-bar { height: 6px; background: rgba(44, 62, 80, 0.08); border-radius: 6px; overflow: hidden; }
.dashboard-ticket-fill { height: 100%; border-radius: 6px; transition: width 0.4s ease; }
.dashboard-ticket-item:nth-child(1) .dashboard-ticket-fill { background: linear-gradient(90deg, #3498db, #5dade2); }
.dashboard-ticket-item:nth-child(2) .dashboard-ticket-fill { background: linear-gradient(90deg, #e67e22, #f39c12); }
.dashboard-ticket-item:nth-child(3) .dashboard-ticket-fill { background: linear-gradient(90deg, #1abc9c, #48c9b0); }
.dashboard-ticket-item:nth-child(4) .dashboard-ticket-fill { background: linear-gradient(90deg, var(--dash-success), #3d9d8a); }

.dashboard-donut { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; padding: 0.5rem 0; }
.dashboard-donut-chart { position: relative; width: 130px; height: 130px; flex-shrink: 0; }
.dashboard-donut-chart svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.dashboard-donut-bg { fill: none; stroke: rgba(44, 62, 80, 0.08); stroke-width: 5; }
.dashboard-donut-resolved { fill: none; stroke: var(--dash-success); stroke-width: 5; stroke-linecap: round; }
.dashboard-donut-pending { fill: none; stroke: #5dade2; stroke-width: 5; stroke-linecap: round; }
.dashboard-donut-center {
    font-family: 'Fraunces', Georgia, serif;
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 700; color: var(--dash-primary);
}
.dashboard-donut-legend { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem; }
.dashboard-donut-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 4px; margin-right: 0.5rem; vertical-align: middle; }

.dashboard-list { display: flex; flex-direction: column; gap: 0.6rem; }
.dashboard-list-row {
    display: flex; justify-content: space-between; align-items: center; padding: 0.9rem 1.1rem;
    background: linear-gradient(180deg, #fbfaf8 0%, #f5f3ef 100%);
    border-radius: 12px; font-size: 0.9rem; border: 1px solid transparent;
    transition: all 0.2s;
}
.dashboard-list-row:hover { background: rgba(255,255,255,0.9); }
.dashboard-list-amount {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 700; color: var(--dash-primary);
}
.dashboard-list-row-overdue {
    background: rgba(192, 57, 43, 0.06);
    border-left: 4px solid var(--dash-danger);
}
.dashboard-list-row-overdue i { color: var(--dash-danger); margin-right: 0.5rem; font-size: 1.1rem; }
.dashboard-list-row-link { text-decoration: none; color: inherit; }
.dashboard-list-row-link:hover { background: rgba(255,255,255,0.9); }
.dashboard-list-badge {
    padding: 0.35rem 0.7rem; background: var(--dash-accent-soft); color: var(--dash-primary);
    font-weight: 600; font-size: 0.8rem; border-radius: 10px;
}

.dashboard-bars { display: flex; flex-direction: column; gap: 1rem; }
.dashboard-bar { display: flex; align-items: center; gap: 1rem; font-size: 0.9rem; }
.dashboard-bar-label { flex: 0 0 120px; font-weight: 500; color: var(--dash-text-soft); }
.dashboard-bar-track { flex: 1; height: 10px; background: rgba(44, 62, 80, 0.08); border-radius: 8px; overflow: hidden; }
.dashboard-bar-fill {
    height: 100%; background: linear-gradient(90deg, var(--dash-primary), var(--dash-primary-light));
    border-radius: 8px; transition: width 0.4s ease;
}
.dashboard-bar-count {
    font-family: 'Fraunces', Georgia, serif;
    flex: 0 0 36px; font-weight: 700; color: var(--dash-primary); text-align: right;
}

.dashboard-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 2.5rem 1.5rem; color: var(--dash-text-soft); text-align: center; min-height: 140px;
    background: linear-gradient(180deg, #fbfaf8 0%, #f5f3ef 100%);
    border-radius: 14px; border: 2px dashed rgba(44, 62, 80, 0.15);
}
.dashboard-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5; }
.dashboard-empty .btn {
    border-radius: 12px; font-weight: 600;
    background: var(--dash-primary); border-color: var(--dash-primary);
}

.dashboard-actions { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border); }
.dashboard-actions-top {
    margin-top: 0; padding-top: 0; border-top: none;
    margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--dash-border);
}
.dashboard-action-btn-primary {
    background: var(--dash-primary) !important;
    border: none !important; color: #fff !important;
}
.dashboard-action-btn-primary:hover {
    background: var(--dash-primary-light) !important;
    color: #fff !important;
}
.dashboard-actions-label {
    font-size: 0.75rem; font-weight: 600; color: var(--dash-text-soft);
    text-transform: uppercase; letter-spacing: 0.1em;
    display: block; margin-bottom: 1rem;
}
.dashboard-actions-btns { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.dashboard-action-btn {
    display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem;
    font-size: 0.875rem; font-weight: 600;
    background: var(--dash-card); border: 1px solid var(--dash-border);
    border-radius: 12px; color: var(--dash-text-soft); text-decoration: none;
    transition: all 0.25s ease;
    box-shadow: var(--dash-shadow);
}
.dashboard-action-btn:hover {
    border-color: var(--dash-primary);
    background: var(--dash-primary); color: #fff;
    box-shadow: var(--dash-shadow-hover);
    transform: translateY(-1px);
}
.dashboard-action-btn i { font-size: 1.05rem; }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var c = document.getElementById('clientsCountValue');
    var t = document.getElementById('contactsCountValue');
    if (c || t) {
        fetch('{{ route("api.dashboard.clients-count") }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var n = d.count != null ? (d.count).toLocaleString() : '—';
                if (c) c.textContent = n;
                if (t) t.textContent = n;
            })
            .catch(function() {
                if (c) c.textContent = '—';
                if (t) t.textContent = '—';
            });
    }
    if (window.Echo) window.Echo.channel('dashboard').listen('.stats.updated', function() { location.reload(); });
});
</script>
@endpush
@endsection
