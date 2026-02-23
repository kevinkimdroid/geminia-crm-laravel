@extends('layouts.app')

@section('title', ($client->life_assur ?? $client->client_name ?? $client->name ?? 'Client') . ' — Client')

@section('content')
@php $clientPhone = $client->phone_no ?? $client->phoneNo ?? $client->mobile ?? $client->phone ?? $client->PHONE_NO ?? null; @endphp
<nav class="breadcrumb-nav mb-3">
    @if($fromServeClient ?? false)
    <a href="{{ route('support.serve-client', ['search' => $client->policy_no ?? $policy]) }}" class="text-muted small text-decoration-none">Serve Client</a>
    @else
    <a href="{{ route('support.customers') }}" class="text-muted small text-decoration-none">Clients</a>
    @endif
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">{{ $client->policy_no ?? $policy }}</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-2">{{ $client->life_assur ?? $client->client_name ?? $client->name ?? 'Client' }}</h1>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="client-status-badge client-status-{{ ($client->status ?? '') === 'A' ? 'active' : (($client->status ?? '') === 'FL' ? 'lapsed' : 'other') }}">{{ $client->status ?? '—' }}</span>
            <span class="text-muted small font-monospace">{{ $client->policy_no ?? $policy }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        @if($clientPhone)
        <a href="tel:{{ tel_href($clientPhone) }}" class="btn btn-sm btn-success">
            <i class="bi bi-telephone me-1"></i> Call
        </a>
        @endif
        <a href="{{ route('support.clients.create-ticket', ['policy' => $client->policy_no ?? $policy]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-ticket-perforated me-1"></i> Create Ticket
        </a>
        <a href="{{ route('support.serve-client', ['search' => $client->policy_no ?? $policy]) }}" class="btn btn-sm app-btn-primary">
            <i class="bi bi-person-plus me-1"></i> Serve Client
        </a>
        <a href="{{ ($fromServeClient ?? false) ? route('support.serve-client', ['search' => $client->policy_no ?? $policy]) : route('support.customers') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="app-card p-4">
            <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Client Details</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <dl class="client-detail-dl mb-0">
                        <dt>Life Assured (Client)</dt>
                        <dd>{{ $client->life_assur ?? $client->client_name ?? $client->name ?? '—' }}</dd>
                        <dt>Policy Number</dt>
                        <dd class="font-monospace">{{ $client->policy_no ?? $policy ?? '—' }}</dd>
                        <dt>Who Prepared Policy</dt>
                        <dd>{{ $client->pol_prepared_by ?? '—' }}</dd>
                        <dt>Intermediary (Agent)</dt>
                        <dd>{{ $client->intermediary ?? '—' }}</dd>
                        <dt>Product</dt>
                        <dd>{{ $client->product ?? '—' }}</dd>
                        <dt>Policy Status</dt>
                        <dd><span class="client-status-badge client-status-{{ ($client->status ?? '') === 'A' ? 'active' : (($client->status ?? '') === 'FL' ? 'lapsed' : 'other') }}">{{ $client->status ?? '—' }}</span></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="client-detail-dl mb-0">
                        <dt>Date of Birth</dt>
                        <dd>{{ $client->prp_dob ?? $client->prpDob ?? '—' }}</dd>
                        <dt>Effective Date</dt>
                        <dd>{{ $client->effective_date ?? $client->effectiveDate ?? '—' }}</dd>
                        <dt>Maturity Date</dt>
                        <dd>{{ $client->maturity ?? $client->maturity_date ?? $client->maturityDate ?? '—' }}</dd>
                        <dt>KRA PIN</dt>
                        <dd class="font-monospace">{{ $client->kra_pin ?? $client->kraPin ?? '—' }}</dd>
                        <dt>ID Number</dt>
                        <dd class="font-monospace">{{ $client->id_no ?? $client->idNo ?? $client->ID_NO ?? '—' }}</dd>
                        <dt>Phone Number</dt>
                        <dd>{{ $client->phone_no ?? $client->phoneNo ?? $client->mobile ?? $client->phone ?? $client->PHONE_NO ?? '—' }}</dd>
                        <dt>Total Paid Amount</dt>
                        <dd>{{ $client->bal ?? $client->paid_mat_amt ?? $client->paidMatAmt ?? '—' }}</dd>
                        <dt>Checkoff</dt>
                        <dd>{{ $client->checkoff ?? '—' }}</dd>
                        @if($client->email_adr ?? null)
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $client->email_adr }}">{{ $client->email_adr }}</a></dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @if($tickets->isNotEmpty())
        <div class="app-card p-4 mt-4">
            <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Related Tickets</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $t)
                        <tr>
                            <td class="font-monospace small">{{ $t->ticket_no ?? $t->ticketid }}</td>
                            <td>{{ Str::limit($t->title ?? '—', 40) }}</td>
                            <td><span class="ticket-status-badge ticket-status-{{ Str::slug($t->status ?? '') }}">{{ $t->status ?? '—' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('tickets.show', $t->ticketid) }}" class="btn btn-sm btn-link p-0">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="app-card p-4">
            <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Quick Actions</h6>
            <div class="d-flex flex-column gap-2">
                @if($clientPhone)
                <a href="tel:{{ tel_href($clientPhone) }}" class="btn btn-outline-primary">
                    <i class="bi bi-telephone me-2"></i>Call
                </a>
                @endif
                <a href="{{ route('support.clients.create-ticket', ['policy' => $client->policy_no ?? $policy]) }}" class="btn btn-outline-success">
                    <i class="bi bi-ticket-perforated me-2"></i>Create Ticket
                </a>
                <a href="{{ route('support.serve-client', ['search' => $client->policy_no ?? $policy]) }}" class="btn btn-outline-primary">
                    <i class="bi bi-person-plus me-2"></i>Serve Client
                </a>
                @if($contact ?? null)
                <a href="{{ route('contacts.show', $contact->contactid) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-person me-2"></i>View CRM Contact
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.client-detail-dl dt { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--geminia-text-muted); margin-top: 0.75rem; margin-bottom: 0.2rem; }
.client-detail-dl dt:first-child { margin-top: 0; }
.client-detail-dl dd { margin: 0; font-size: 0.95rem; }
.client-status-badge { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 6px; display: inline-block; }
.client-status-active { background: #dcfce7; color: #166534; }
.client-status-lapsed { background: #fee2e2; color: #991b1b; }
.client-status-other { background: #f1f5f9; color: #475569; }
.ticket-status-badge { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 4px; }
.ticket-status-open { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.ticket-status-closed { background: rgba(5, 150, 105, 0.15); color: #059669; }
</style>
@endsection
