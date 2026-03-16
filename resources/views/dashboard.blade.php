@extends('layouts.app')

@section('title', 'Dashboard')

@push('head')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    {{-- Main grid: 2 columns --}}
    <section class="dashboard-grid">
        {{-- Left column --}}
        <div class="dashboard-col">
            {{-- Tickets --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-bar-chart"></i> Ticket Statistics</h2>
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

            {{-- Revenue --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-currency-dollar"></i> Revenue by Salesperson</h2>
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

            {{-- Leads by Source --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-pie-chart"></i> Leads by Source</h2>
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
        </div>

        {{-- Right column --}}
        <div class="dashboard-col">
            {{-- Ticket Resolution --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-pie-chart-fill"></i> Ticket Resolution</h2>
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

            {{-- Overdue --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-exclamation-triangle"></i> Overdue Activities</h2>
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

            {{-- Deals Closing Soon --}}
            <div class="dashboard-card">
                <div class="dashboard-card-head">
                    <h2 class="dashboard-card-title"><i class="bi bi-calendar-check"></i> Deals Closing Soon</h2>
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

    {{-- Quick actions --}}
    <section class="dashboard-actions">
        <span class="dashboard-actions-label">Quick actions</span>
        <div class="dashboard-actions-btns">
            @if((isset($clientsCount) && $clientsCount !== null) || ($clientsCountDeferred ?? false))
            <a href="{{ route('support.customers') }}" class="dashboard-action-btn"><i class="bi bi-people"></i> Clients</a>
            <a href="{{ route('support.serve-client') }}" class="dashboard-action-btn"><i class="bi bi-person-plus"></i> Serve Client</a>
            @if(in_array(config('erp.clients_view_source', 'crm'), ['erp_http', 'erp_sync']))
            <a href="{{ route('support.customers', ['system' => 'group']) }}" class="dashboard-action-btn"><i class="bi bi-people-fill"></i> Group Life</a>
            <a href="{{ route('support.customers', ['system' => 'individual']) }}" class="dashboard-action-btn"><i class="bi bi-person-fill"></i> Individual</a>
            @endif
            @endif
            @if($pbxCanCall ?? false)
            <a href="{{ route('tools.pbx-manager') }}" class="dashboard-action-btn"><i class="bi bi-telephone"></i> Call</a>
            @endif
            <a href="{{ route('leads.create') }}" class="dashboard-action-btn"><i class="bi bi-plus-circle"></i> Add Lead</a>
            <a href="{{ route('tickets.create') }}" class="dashboard-action-btn"><i class="bi bi-ticket-perforated"></i> New Ticket</a>
            <a href="{{ route('contacts.index') }}" class="dashboard-action-btn"><i class="bi bi-person"></i> Contacts</a>
            <a href="{{ route('deals.index') }}" class="dashboard-action-btn"><i class="bi bi-currency-dollar"></i> Deals</a>
        </div>
    </section>
</div>

<style>
.dashboard { max-width: 1200px; margin: 0 auto; padding: 1.5rem; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
.dashboard-header {
    display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
    gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;
}
.dashboard-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 0.15rem; }
.dashboard-greeting { color: #6b7280; font-weight: 500; }
.dashboard-name { color: #1A468A; }
.dashboard-subtitle { font-size: 0.9rem; color: #6b7280; margin: 0; }
.dashboard-header-right { display: flex; align-items: center; gap: 0.5rem; }
.dashboard-header-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.45rem 0.9rem; font-size: 0.85rem; font-weight: 600; border-radius: 8px;
    background: #fff; border: 1px solid #e5e7eb; color: #374151; text-decoration: none; transition: all 0.2s;
}
.dashboard-header-btn:hover { border-color: #d1d5db; background: #f9fafb; color: #1A468A; }
.dashboard-header-btn-primary { background: #1A468A !important; border-color: #1A468A !important; color: #fff !important; }
.dashboard-header-btn-primary:hover { background: #133A6F !important; color: #fff !important; }

.dashboard-kpis {
    display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;
}
.dashboard-kpis.dashboard-kpis-4 { grid-template-columns: 1.4fr 1fr 1fr 1fr; }
@media (max-width: 992px) { .dashboard-kpis, .dashboard-kpis.dashboard-kpis-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .dashboard-kpis, .dashboard-kpis.dashboard-kpis-4 { grid-template-columns: 1fr; } }
.dashboard-kpi {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem;
    display: flex; flex-direction: column; align-items: flex-start; text-decoration: none; color: inherit;
    transition: all 0.2s; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.dashboard-kpi:hover { border-color: #1A468A; box-shadow: 0 4px 12px rgba(26,70,138,0.1); transform: translateY(-2px); }
.dashboard-kpi-featured {
    background: #1A468A; border: none; color: #fff !important;
    flex-direction: row; align-items: center; gap: 1rem;
}
.dashboard-kpi-featured:hover { color: #fff !important; background: #133A6F; }
.dashboard-kpi-icon { width: 48px; height: 48px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.dashboard-kpi-content { display: flex; flex-direction: column; gap: 0.1rem; }
.dashboard-kpi-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
.dashboard-kpi-featured .dashboard-kpi-label { color: rgba(255,255,255,0.85); }
.dashboard-kpi-value { font-size: 1.5rem; font-weight: 700; color: #111827; }
.dashboard-kpi-featured .dashboard-kpi-value { font-size: 1.75rem; }
.dashboard-kpi-meta { font-size: 0.8rem; color: rgba(255,255,255,0.9); }
.dashboard-kpi-badge { position: absolute; top: 0.5rem; right: 0.5rem; padding: 0.15rem 0.5rem; background: #1A468A; color: #fff; font-size: 0.65rem; font-weight: 600; border-radius: 6px; }
.dashboard-kpi-static { cursor: default; }
.dashboard-kpi-link { font-size: 0.75rem; font-weight: 600; color: #1A468A; margin-top: 0.25rem; }

.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media (max-width: 992px) { .dashboard-grid { grid-template-columns: 1fr; } }
.dashboard-col { display: flex; flex-direction: column; gap: 1.25rem; }
.dashboard-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s;
}
.dashboard-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.dashboard-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.dashboard-card-title { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.dashboard-card-title i { color: #5a7aa8; }
.dashboard-card-link { font-size: 0.8rem; font-weight: 600; color: #1A468A; text-decoration: none; }
.dashboard-card-link:hover { text-decoration: underline; }
.dashboard-card-actions { display: flex; align-items: center; gap: 0.5rem; }
.dashboard-card .btn-outline-secondary {
    font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.65rem; border-radius: 6px;
    border-color: #e5e7eb; color: #374151; background: #fff;
}
.dashboard-card .btn-outline-secondary:hover { border-color: #d1d5db; background: #f9fafb; color: #1A468A; }

.dashboard-ticket-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
.dashboard-ticket-item {
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem;
    text-align: center; text-decoration: none; color: inherit; transition: all 0.2s;
}
.dashboard-ticket-item:hover { background: #fff; border-color: #d1d5db; }
.dashboard-ticket-num { display: block; font-size: 1.4rem; font-weight: 700; color: #111827; }
.dashboard-ticket-lbl { display: block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6b7280; margin-bottom: 0.5rem; }
.dashboard-ticket-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
.dashboard-ticket-fill { height: 100%; border-radius: 3px; background: #4a6b9a; transition: width 0.3s; }
.dashboard-ticket-item:nth-child(1) .dashboard-ticket-fill { background: #4a6b9a; }
.dashboard-ticket-item:nth-child(2) .dashboard-ticket-fill { background: #5a9dc4; }
.dashboard-ticket-item:nth-child(3) .dashboard-ticket-fill { background: #b89a5a; }
.dashboard-ticket-item:nth-child(4) .dashboard-ticket-fill { background: #5a9b7d; }

.dashboard-donut { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.dashboard-donut-chart { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
.dashboard-donut-chart svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.dashboard-donut-bg { fill: none; stroke: #e5e7eb; stroke-width: 3; }
.dashboard-donut-resolved { fill: none; stroke: #5a9b7d; stroke-width: 3; stroke-linecap: round; }
.dashboard-donut-pending { fill: none; stroke: #8eb8d4; stroke-width: 3; stroke-linecap: round; }
.dashboard-donut-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; color: #1A468A; }
.dashboard-donut-legend { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.9rem; }
.dashboard-donut-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 0.5rem; vertical-align: middle; }

.dashboard-list { display: flex; flex-direction: column; gap: 0.5rem; }
.dashboard-list-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border-radius: 8px; font-size: 0.9rem; }
.dashboard-list-amount { font-weight: 700; color: #1A468A; }
.dashboard-list-row-overdue { background: rgba(185,28,28,0.06); border-left: 4px solid #b91c1c; }
.dashboard-list-row-overdue i { color: #b91c1c; margin-right: 0.5rem; }
.dashboard-list-row-link { text-decoration: none; color: inherit; transition: background 0.2s; }
.dashboard-list-row-link:hover { background: #f1f5f9; }
.dashboard-list-badge { padding: 0.25rem 0.6rem; background: rgba(26,70,138,0.1); color: #1A468A; font-weight: 600; font-size: 0.8rem; border-radius: 6px; }

.dashboard-bars { display: flex; flex-direction: column; gap: 0.75rem; }
.dashboard-bar { display: flex; align-items: center; gap: 1rem; font-size: 0.9rem; }
.dashboard-bar-label { flex: 0 0 120px; }
.dashboard-bar-track { flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.dashboard-bar-fill { height: 100%; background: #1A468A; border-radius: 4px; transition: width 0.4s; }
.dashboard-bar-count { flex: 0 0 32px; font-weight: 700; color: #1A468A; text-align: right; }

.dashboard-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; color: #6b7280; text-align: center; min-height: 120px; }
.dashboard-empty i { font-size: 2rem; margin-bottom: 0.5rem; }

.dashboard-actions { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; }
.dashboard-actions-label { font-size: 0.8rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem; }
.dashboard-actions-btns { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.dashboard-action-btn {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem;
    font-size: 0.85rem; font-weight: 600; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    color: #374151; text-decoration: none; transition: all 0.2s;
}
.dashboard-action-btn:hover { border-color: #1A468A; background: #1A468A; color: #fff; }
.dashboard-action-btn i { font-size: 1rem; }
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
