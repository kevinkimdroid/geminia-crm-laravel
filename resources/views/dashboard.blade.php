@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-page">
    {{-- Header --}}
    <div class="dashboard-header mb-4">
        <div>
            <h1 class="app-page-title mb-1">Welcome back</h1>
            <p class="app-page-sub mb-0">Here's your CRM overview for {{ now()->format('l, F j') }}</p>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="row g-3 mb-4">
        @if($clientsCountDeferred ?? false)
        <div class="col-sm-6 col-xl-3" id="clientsStatCard">
            <a href="{{ route('support.customers') }}" class="dashboard-stat dashboard-stat-primary">
                <div class="dashboard-stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Clients</span>
                    <span class="dashboard-stat-value" id="clientsCountValue"><span class="spinner-border spinner-border-sm align-middle" role="status"></span></span>
                    <span class="dashboard-stat-cta">View all <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
        @elseif(isset($clientsCount) && $clientsCount !== null)
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('support.customers') }}" class="dashboard-stat dashboard-stat-primary">
                <div class="dashboard-stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Clients</span>
                    <span class="dashboard-stat-value">{{ number_format($clientsCount) }}</span>
                    <span class="dashboard-stat-cta">View all <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
        @endif
        <div class="col-sm-6 col-xl-3" id="contactsStatCard">
            <a href="{{ route('contacts.index') }}" class="dashboard-stat {{ ($clientsCountDeferred ?? false) || (isset($clientsCount) && $clientsCount !== null) ? '' : 'dashboard-stat-primary' }}">
                <div class="dashboard-stat-icon"><i class="bi bi-person-lines-fill"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Contacts</span>
                    @if($contactsCountDeferred ?? false)
                    <span class="dashboard-stat-value" id="contactsCountValue"><span class="spinner-border spinner-border-sm align-middle" role="status"></span></span>
                    @else
                    <span class="dashboard-stat-value">{{ number_format($contactsCount ?? 0) }}</span>
                    @endif
                    <span class="dashboard-stat-cta">View all <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('leads.index') }}" class="dashboard-stat">
                <div class="dashboard-stat-icon dashboard-stat-leads"><i class="bi bi-people-fill"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Leads</span>
                    <span class="dashboard-stat-value">{{ number_format($leadsCount ?? 0) }}</span>
                    <span class="dashboard-stat-meta"><i class="bi bi-plus-circle me-1"></i>{{ $leadsTodayCount ?? 0 }} new today</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('deals.index') }}" class="dashboard-stat">
                <div class="dashboard-stat-icon dashboard-stat-pipeline"><i class="bi bi-currency-dollar"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Pipeline Value</span>
                    <span class="dashboard-stat-value dashboard-stat-accent">KES {{ number_format($pipelineValue ?? 0, 0) }}</span>
                    <span class="dashboard-stat-cta">View deals <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('deals.index') }}" class="dashboard-stat">
                <div class="dashboard-stat-icon dashboard-stat-deals"><i class="bi bi-briefcase-fill"></i></div>
                <div class="dashboard-stat-body">
                    <span class="dashboard-stat-label">Active Deals</span>
                    <span class="dashboard-stat-value">{{ number_format($dealsCount ?? 0) }}</span>
                    <span class="dashboard-stat-cta">View all <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    {{-- Main grid --}}
    <div class="row g-4">
        {{-- Tickets by Status --}}
        <div class="col-lg-8">
            <div class="app-card dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title"><i class="bi bi-ticket-perforated me-2"></i>Tickets by Status</h2>
                    <a href="{{ route('tickets.index') }}" class="dashboard-card-link">View all</a>
                </div>
                @php
                    $tc = $ticketCounts ?? [];
                    $open = $tc['Open'] ?? $tc['open'] ?? 0;
                    $inProgress = $tc['In Progress'] ?? $tc['InProgress'] ?? 0;
                    $waitResp = $tc['Wait For Response'] ?? 0;
                    $closed = $tc['Closed'] ?? $tc['closed'] ?? 0;
                    $max = max(1, $open, $inProgress, $waitResp, $closed);
                @endphp
                <div class="dashboard-ticket-stats">
                    <a href="{{ route('tickets.index', ['list' => 'Open']) }}" class="dashboard-ticket-stat dashboard-ticket-open">
                        <span class="dashboard-ticket-count">{{ $open }}</span>
                        <span class="dashboard-ticket-label">Open</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($open/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'In Progress']) }}" class="dashboard-ticket-stat dashboard-ticket-progress">
                        <span class="dashboard-ticket-count">{{ $inProgress }}</span>
                        <span class="dashboard-ticket-label">In Progress</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($inProgress/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'Wait For Response']) }}" class="dashboard-ticket-stat dashboard-ticket-wait">
                        <span class="dashboard-ticket-count">{{ $waitResp }}</span>
                        <span class="dashboard-ticket-label">Waiting</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($waitResp/$max)*100 }}%"></div></div>
                    </a>
                    <a href="{{ route('tickets.index', ['list' => 'Closed']) }}" class="dashboard-ticket-stat dashboard-ticket-closed">
                        <span class="dashboard-ticket-count">{{ $closed }}</span>
                        <span class="dashboard-ticket-label">Closed</span>
                        <div class="dashboard-ticket-bar"><div class="dashboard-ticket-fill" style="width:{{ ($closed/$max)*100 }}%"></div></div>
                    </a>
                </div>
            </div>
        </div>

        {{-- Overdue Activities --}}
        <div class="col-lg-4">
            <div class="app-card dashboard-card h-100">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title"><i class="bi bi-exclamation-triangle me-2"></i>Overdue</h2>
                    <a href="{{ route('activities.index') }}" class="dashboard-card-link">View</a>
                </div>
                <div class="dashboard-list">
                    @forelse ($overdueActivities ?? [] as $activity)
                        <div class="dashboard-list-item dashboard-list-item-overdue">
                            <i class="bi bi-exclamation-circle"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600 text-truncate">{{ $activity['subject'] }}</div>
                                <small class="text-muted">{{ $activity['due_date'] ? \Carbon\Carbon::parse($activity['due_date'])->diffForHumans() : '—' }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="dashboard-empty">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>No overdue activities</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Second row --}}
    <div class="row g-4 mt-0">
        <div class="col-lg-6">
            <div class="app-card dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title"><i class="bi bi-pie-chart me-2"></i>Leads by Source</h2>
                    <a href="{{ route('leads.index') }}" class="dashboard-card-link">View</a>
                </div>
                @php $leadsBySource = $leadsBySource ?? []; $totalLeads = array_sum($leadsBySource); @endphp
                @if (count($leadsBySource) > 0)
                    <div class="dashboard-lead-bars">
                        @foreach ($leadsBySource as $source => $cnt)
                            <div class="dashboard-lead-bar">
                                <span class="dashboard-lead-source">{{ $source }}</span>
                                <div class="dashboard-lead-track">
                                    <div class="dashboard-lead-fill" style="width:{{ $totalLeads > 0 ? ($cnt/$totalLeads)*100 : 0 }}%"></div>
                                </div>
                                <span class="dashboard-lead-count">{{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty dashboard-empty-tall">
                        <i class="bi bi-pie-chart"></i>
                        <span>No lead source data yet</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title"><i class="bi bi-calendar-check me-2"></i>Deals Closing Soon</h2>
                    <a href="{{ route('deals.index') }}" class="dashboard-card-link">View</a>
                </div>
                @php $dealsClosingSoon = $dealsClosingSoon ?? collect(); @endphp
                @if ($dealsClosingSoon->isNotEmpty())
                    <div class="dashboard-deals-list">
                        @foreach ($dealsClosingSoon->take(5) as $deal)
                            <a href="{{ route('deals.show', $deal->potentialid) }}" class="dashboard-deal-item">
                                <div>
                                    <div class="fw-600">{{ $deal->potentialname }}</div>
                                    <small class="text-muted">{{ $deal->closingdate ? \Carbon\Carbon::parse($deal->closingdate)->format('M j, Y') : '—' }}</small>
                                </div>
                                <span class="dashboard-deal-badge">KES {{ number_format($deal->amount ?? 0, 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty dashboard-empty-tall">
                        <i class="bi bi-calendar-x"></i>
                        <span>No deals closing in 30 days</span>
                        <a href="{{ route('deals.index') }}" class="btn btn-sm app-btn-primary mt-2">View Deals</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="dashboard-quick-actions mt-4">
        @if(isset($clientsCount) && $clientsCount !== null)
        <a href="{{ route('support.customers') }}" class="dashboard-quick-action"><i class="bi bi-people"></i> Clients</a>
        <a href="{{ route('support.serve-client') }}" class="dashboard-quick-action"><i class="bi bi-person-plus"></i> Serve Client</a>
        @endif
        @if($pbxCanCall ?? false)
        <a href="{{ route('tools.pbx-manager') }}" class="dashboard-quick-action"><i class="bi bi-telephone"></i> Make Call</a>
        @endif
        <a href="{{ route('leads.create') }}" class="dashboard-quick-action"><i class="bi bi-plus-circle"></i> Add Lead</a>
        <a href="{{ route('tickets.create') }}" class="dashboard-quick-action"><i class="bi bi-ticket-perforated"></i> New Ticket</a>
        <a href="{{ route('contacts.index') }}" class="dashboard-quick-action"><i class="bi bi-person"></i> Contacts</a>
        <a href="{{ route('deals.index') }}" class="dashboard-quick-action"><i class="bi bi-currency-dollar"></i> Deals</a>
    </div>
</div>

<style>
.dashboard-stat {
    background: #fff;
    border: 1px solid var(--geminia-border);
    border-radius: 14px;
    padding: 1.35rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.25s;
}
.dashboard-stat:hover {
    border-color: var(--geminia-primary);
    box-shadow: 0 8px 24px rgba(26, 85, 158, 0.12);
    transform: translateY(-2px);
}
.dashboard-stat-primary {
    background: linear-gradient(135deg, var(--geminia-primary) 0%, var(--geminia-primary-dark) 100%);
    border: none;
    color: #fff !important;
}
.dashboard-stat-primary:hover { box-shadow: 0 12px 32px rgba(26, 85, 158, 0.28); }
.dashboard-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.dashboard-stat-leads { background: rgba(5, 150, 105, 0.12); color: #059669; }
.dashboard-stat-pipeline { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.dashboard-stat-deals { background: rgba(14, 165, 233, 0.12); color: #0284c7; }
.dashboard-stat-primary .dashboard-stat-icon { background: rgba(255,255,255,0.25); }
.dashboard-stat-body { display: flex; flex-direction: column; gap: 0.15rem; }
.dashboard-stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--geminia-text-muted); }
.dashboard-stat-primary .dashboard-stat-label { color: rgba(255,255,255,0.85); }
.dashboard-stat-value { font-size: 1.6rem; font-weight: 700; color: var(--geminia-text); }
.dashboard-stat-primary .dashboard-stat-value { color: #fff; }
.dashboard-stat-accent { color: var(--geminia-primary) !important; }
.dashboard-stat-cta, .dashboard-stat-meta { font-size: 0.8rem; font-weight: 600; margin-top: 0.2rem; }
.dashboard-stat-cta { color: var(--geminia-text-muted); }
.dashboard-stat:hover .dashboard-stat-cta { color: var(--geminia-primary); }
.dashboard-stat-primary .dashboard-stat-cta { color: rgba(255,255,255,0.9); }
.dashboard-stat-meta { color: #059669; }

.dashboard-card { padding: 1.5rem; }
.dashboard-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.dashboard-card-title { font-size: 1rem; font-weight: 700; color: var(--geminia-text); margin: 0; display: flex; align-items: center; }
.dashboard-card-title i { color: var(--geminia-primary); }
.dashboard-card-link { font-size: 0.85rem; font-weight: 600; color: var(--geminia-primary); text-decoration: none; }
.dashboard-card-link:hover { color: var(--geminia-primary-dark); text-decoration: underline; }

.dashboard-ticket-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.dashboard-ticket-stat {
    background: var(--geminia-bg);
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.dashboard-ticket-stat:hover { background: var(--geminia-primary-muted); }
.dashboard-ticket-count { display: block; font-size: 1.5rem; font-weight: 700; color: var(--geminia-text); }
.dashboard-ticket-label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--geminia-text-muted); margin-bottom: 0.75rem; }
.dashboard-ticket-bar { height: 6px; background: var(--geminia-border); border-radius: 3px; overflow: hidden; }
.dashboard-ticket-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
.dashboard-ticket-open .dashboard-ticket-fill { background: var(--geminia-primary); }
.dashboard-ticket-progress .dashboard-ticket-fill { background: #0ea5e9; }
.dashboard-ticket-wait .dashboard-ticket-fill { background: #f59e0b; }
.dashboard-ticket-closed .dashboard-ticket-fill { background: #059669; }
@media (max-width: 768px) { .dashboard-ticket-stats { grid-template-columns: repeat(2, 1fr); } }

.dashboard-list { display: flex; flex-direction: column; gap: 0.5rem; }
.dashboard-list-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    transition: background 0.2s;
}
.dashboard-list-item-overdue {
    background: rgba(220, 38, 38, 0.06);
    border-left: 4px solid #dc2626;
}
.dashboard-list-item-overdue i { color: #dc2626; font-size: 1.1rem; }

.dashboard-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    color: var(--geminia-text-muted);
}
.dashboard-empty i { font-size: 2rem; margin-bottom: 0.5rem; }
.dashboard-empty-tall { padding: 2.5rem 1.5rem; min-height: 180px; }

.dashboard-lead-bars { display: flex; flex-direction: column; gap: 1rem; }
.dashboard-lead-bar { display: flex; align-items: center; gap: 1rem; }
.dashboard-lead-source { flex: 0 0 140px; font-size: 0.9rem; }
.dashboard-lead-track { flex: 1; height: 10px; background: var(--geminia-border); border-radius: 5px; overflow: hidden; }
.dashboard-lead-fill { height: 100%; background: var(--geminia-primary); border-radius: 5px; transition: width 0.4s; }
.dashboard-lead-count { flex: 0 0 32px; font-weight: 700; text-align: right; color: var(--geminia-primary); font-size: 0.9rem; }

.dashboard-deals-list { display: flex; flex-direction: column; gap: 0.5rem; }
.dashboard-deal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: var(--geminia-bg);
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.dashboard-deal-item:hover { background: var(--geminia-primary-muted); }
.dashboard-deal-badge {
    padding: 0.35rem 0.75rem;
    background: var(--geminia-primary-muted);
    color: var(--geminia-primary);
    font-weight: 600;
    font-size: 0.85rem;
    border-radius: 8px;
}

.dashboard-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.dashboard-quick-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: #fff;
    border: 1px solid var(--geminia-border);
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--geminia-text);
    text-decoration: none;
    transition: all 0.2s;
}
.dashboard-quick-action:hover {
    border-color: var(--geminia-primary);
    background: var(--geminia-primary-muted);
    color: var(--geminia-primary);
}
.dashboard-quick-action i { font-size: 1.1rem; }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var clientsEl = document.getElementById('clientsCountValue');
    var contactsEl = document.getElementById('contactsCountValue');
    var needDeferred = clientsEl || contactsEl;
    if (needDeferred) {
        fetch('{{ route("api.dashboard.clients-count") }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var count = d.count != null ? (d.count).toLocaleString() : '—';
                if (clientsEl) clientsEl.textContent = count;
                if (contactsEl) contactsEl.textContent = count;
            })
            .catch(function() {
                if (clientsEl) clientsEl.textContent = '—';
                if (contactsEl) contactsEl.textContent = '—';
            });
    }
    if (window.Echo) {
        window.Echo.channel('dashboard').listen('.stats.updated', function() {
            window.location.reload();
        });
    }
});
</script>
@endpush
@endsection
