@extends('layouts.app')

@section('title', 'Support')

@section('content')
<div class="page-header">
    <h1 class="page-title">Support</h1>
    <p class="page-subtitle">Customer support and help desk.</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card support-stat-card h-100">
            <div class="card-body">
                <p class="support-stat-label">Open</p>
                <h3 class="support-stat-value mb-0">{{ number_format($ticketCounts['Open'] ?? 0) }}</h3>
                <p class="text-muted small mb-0">Tickets</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card support-stat-card h-100">
            <div class="card-body">
                <p class="support-stat-label">In Progress</p>
                <h3 class="support-stat-value mb-0">{{ number_format($ticketCounts['In Progress'] ?? 0) }}</h3>
                <p class="text-muted small mb-0">Tickets</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card support-stat-card h-100">
            <div class="card-body">
                <p class="support-stat-label">Wait Response</p>
                <h3 class="support-stat-value mb-0">{{ number_format($ticketCounts['Wait For Response'] ?? 0) }}</h3>
                <p class="text-muted small mb-0">Tickets</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card support-stat-card support-stat-closed h-100">
            <div class="card-body">
                <p class="support-stat-label">Closed</p>
                <h3 class="support-stat-value mb-0">{{ number_format($ticketCounts['Closed'] ?? 0) }}</h3>
                <p class="text-muted small mb-0">Tickets</p>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small mb-2">Quick access to support workflows. Start with Serve Client to search and assist, or jump to Clients to browse.</p>
<p class="mb-4">
    <a href="{{ route('support.mortgage-renewals') }}" class="text-decoration-none fw-semibold"><i class="bi bi-house-heart me-1"></i>Due for renewal (mortgage)</a>
    <span class="text-muted small"> — Renewal dates due within the next 30 days (change the period on that page).</span>
</p>
<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.serve-client') }}" class="card support-quick-card text-decoration-none h-100" style="border:2px solid var(--geminia-primary);background:var(--geminia-primary-muted)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:var(--geminia-primary-muted)"><i class="bi bi-person-plus-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">Serve Client</h6>
                    <p class="text-muted small mb-0">Search client → Create ticket → Done</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-primary"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('tickets.index') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                <div>
                    <h6 class="mb-1">Tickets</h6>
                    <p class="text-muted small mb-0">Manage support tickets</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.faq') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-question-circle-fill"></i></div>
                <div>
                    <h6 class="mb-1">FAQ</h6>
                    <p class="text-muted small mb-0">Knowledge base</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.customers') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-people-fill"></i></div>
                <div>
                    <h6 class="mb-1">Clients</h6>
                    <p class="text-muted small mb-0">Browse clients → View details → Create ticket</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.maturities') }}" class="card support-quick-card text-decoration-none h-100" style="border-color:var(--geminia-primary);">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:rgba(14,67,133,0.15)"><i class="bi bi-calendar-event-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-semibold">Maturities</h6>
                    <p class="text-muted small mb-0">Policies maturing soon → Create tickets</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-primary"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('tickets.create', ['organization_id' => 'line:Group Life', 'from' => 'serve-client']) }}" class="card support-quick-card text-decoration-none h-100" style="border-color:var(--geminia-primary);">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:rgba(14,67,133,0.15)"><i class="bi bi-people-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-semibold">Group Life — New Ticket</h6>
                    <p class="text-muted small mb-0">Quick create ticket for Group Life issues</p>
                </div>
                <i class="bi bi-ticket-perforated text-primary ms-auto"></i>
            </div>
        </a>
    </div>
</div>

<style>
.support-stat-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: transform .2s, box-shadow .2s; }
.support-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(14, 67, 133, 0.1); }
.support-stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted, #64748b); margin-bottom: 0.25rem; }
.support-stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary, #0E4385); }
.support-stat-closed .support-stat-value { color: var(--success, #059669); }
.support-quick-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: all .2s; color: inherit; }
.support-quick-card:hover { border-color: var(--primary, #0E4385); background: var(--primary-muted, rgba(14, 67, 133, 0.06)); }
.support-quick-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--primary-light, rgba(14, 67, 133, 0.12)); color: var(--primary, #0E4385); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
</style>
@endsection
