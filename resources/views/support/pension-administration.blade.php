@extends('layouts.app')

@section('title', 'Pension Administration')

@section('content')
<div class="page-header mb-4">
    <nav class="mb-2">
        <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
        <span class="text-muted small mx-1">/</span>
        <span class="text-muted small">Pension Administration</span>
    </nav>
    <h1 class="page-title">Pension Administration</h1>
    <p class="page-subtitle mb-0">Group pension clients, inbound mail to <strong>{{ $mailbox }}</strong>, and related support workflows.</p>
</div>

@if (! ($clientsConfigured ?? true))
<div class="alert alert-warning border-0 shadow-sm mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    {{ $clientTabLabel }} client list is not configured yet. Set <code>ERP_CLIENTS_GROUP_PENSION_VIEW</code> in <code>.env</code> (and the matching view in erp-clients-api) to browse pension clients from ERP.
</div>
@endif

<p class="text-muted small mb-4">Quick access for the pension team. Start with the pension inbox or browse {{ $clientTabLabel }} clients, then serve or email as needed.</p>

<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('tools.mail-manager', ['mailbox' => $mailbox]) }}" class="card support-quick-card text-decoration-none h-100" style="border:2px solid #7c3aed;background:#f5f3ff">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:#ede9fe;color:#5b21b6"><i class="bi bi-envelope-at-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">Pension Inbox</h6>
                    <p class="text-muted small mb-0">Emails to {{ $mailbox }}</p>
                </div>
                <i class="bi bi-chevron-right ms-auto" style="color:#7c3aed"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.customers', ['system' => $clientSystem]) }}" class="card support-quick-card text-decoration-none h-100" style="border:2px solid #7c3aed;background:#f5f3ff">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:#ede9fe;color:#5b21b6"><i class="bi bi-piggy-bank-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">{{ $clientTabLabel }} Clients</h6>
                    <p class="text-muted small mb-0">Browse and administer pension clients</p>
                </div>
                <i class="bi bi-chevron-right ms-auto" style="color:#7c3aed"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.serve-client') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-person-plus-fill"></i></div>
                <div>
                    <h6 class="mb-1">Serve Client</h6>
                    <p class="text-muted small mb-0">Search policy → assist → create ticket</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('support.email-client') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-send-fill"></i></div>
                <div>
                    <h6 class="mb-1">Email Client</h6>
                    <p class="text-muted small mb-0">Compose and send to a pension client</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('tickets.index') }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                <div>
                    <h6 class="mb-1">Tickets</h6>
                    <p class="text-muted small mb-0">Support tickets for pension enquiries</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('tickets.create', ['organization_id' => $ticketOrganization, 'from' => 'pension-administration']) }}" class="card support-quick-card text-decoration-none h-100" style="border-color:#7c3aed">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon" style="background:#ede9fe;color:#5b21b6"><i class="bi bi-plus-circle-fill"></i></div>
                <div>
                    <h6 class="mb-1 fw-semibold">New Pension Ticket</h6>
                    <p class="text-muted small mb-0">Quick create for pension issues</p>
                </div>
                <i class="bi bi-ticket-perforated ms-auto" style="color:#7c3aed"></i>
            </div>
        </a>
    </div>
    @if(isset($can) && ($can('marketing.campaigns') || $can('marketing.broadcast') || $can('support.customers')))
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('marketing.broadcast', ['system' => $clientSystem]) }}" class="card support-quick-card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="support-quick-icon"><i class="bi bi-broadcast"></i></div>
                <div>
                    <h6 class="mb-1">Email &amp; SMS broadcast</h6>
                    <p class="text-muted small mb-0">Mass message pension clients</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    @endif
</div>

<style>
.support-quick-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: all .2s; color: inherit; }
.support-quick-card:hover { border-color: var(--primary, #0E4385); background: var(--primary-muted, rgba(14, 67, 133, 0.06)); }
.support-quick-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--primary-light, rgba(14, 67, 133, 0.12)); color: var(--primary, #0E4385); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
</style>
@endsection
