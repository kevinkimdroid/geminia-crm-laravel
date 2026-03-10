@extends('layouts.app')

@section('title', ($ticket->title ?? 'Ticket') . ' — Ticket')

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('tickets.index') }}" class="text-muted small text-decoration-none">Tickets</a>
    <span class="text-muted mx-2">/</span>
    @if($ticket->contact_id ?? null)
    <a href="{{ route('contacts.show', $ticket->contact_id) }}?tab=tickets" class="text-muted small text-decoration-none">Client</a>
    <span class="text-muted mx-2">/</span>
    @endif
    <span class="text-dark small fw-semibold">{{ $ticket->ticket_no ?? $ticket->ticketid }}</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-2">{{ $ticket->title ?? 'Untitled Ticket' }}</h1>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="ticket-status-badge ticket-status-{{ Str::slug($ticket->status ?? '') }}">{{ $ticket->status ?? '—' }}</span>
            @if($ticket->priority ?? null)
            <span class="text-muted small">• {{ $ticket->priority }} priority</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($ticket->contact_id ?? null)
        <a href="{{ route('contacts.show', $ticket->contact_id) }}?tab=tickets" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person me-1"></i> View Client
        </a>
        <a href="{{ route('support.sms-notifier', ['contact_id' => $ticket->contact_id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-chat-dots me-1"></i> Send SMS
        </a>
        @endif
        @if(($ticket->status ?? '') !== 'Closed')
        <a href="{{ route('tickets.close.form', $ticket->ticketid) }}" class="btn btn-sm btn-success">
            <i class="bi bi-check-circle me-1"></i> Close Ticket
        </a>
        @endif
        @if(($ticket->status ?? '') !== 'Inactive')
        <form action="{{ route('tickets.inactivate', $ticket->ticketid) }}" method="POST" onsubmit="return confirm('Inactivate this ticket? It will no longer appear in active lists.');" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Inactivate ticket"><i class="bi bi-pause-circle me-1"></i> Inactivate</button>
        </form>
        @endif
        <a href="{{ route('tickets.edit', $ticket->ticketid) }}" class="btn btn-sm app-btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Ticket workflow: Created by → Assigned to → Closed by --}}
<div class="ticket-workflow mb-4">
    <div class="ticket-workflow-step">
        <span class="ticket-workflow-label">Created by</span>
        <span class="ticket-workflow-value">{{ $ticket->created_by_name ?? '—' }}</span>
    </div>
    <i class="bi bi-arrow-right ticket-workflow-arrow"></i>
    <div class="ticket-workflow-step">
        <span class="ticket-workflow-label">Assigned to</span>
        <span class="ticket-workflow-value">{{ $ticket->assigned_to_name ?? '—' }}</span>
    </div>
    @if(($ticket->status ?? '') === 'Closed')
    <i class="bi bi-arrow-right ticket-workflow-arrow"></i>
    <div class="ticket-workflow-step">
        <span class="ticket-workflow-label">Closed by</span>
        <span class="ticket-workflow-value">{{ $ticket->closed_by_name ?? '—' }}</span>
    </div>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="app-card p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Description Details</h6>
            <div class="ticket-description">{{ $ticket->description ? nl2br(e(preg_replace("/\n{2,}/", "\n", $ticket->description))) : 'No description.' }}</div>
        </div>
        @if($ticket->solution ?? null)
        <div class="app-card p-4 mt-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Resolution</h6>
            <div class="ticket-description">{{ nl2br(e(preg_replace("/\n{2,}/", "\n", $ticket->solution ?? ''))) }}</div>
        </div>
        @elseif(($ticket->status ?? '') !== 'Closed')
        <div class="app-card p-4 mt-4 border border-success">
            <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Add a solution and change status to Closed to resolve this ticket.</p>
            <a href="{{ route('tickets.close.form', $ticket->ticketid) }}" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i> Close Ticket</a>
        </div>
        @endif
        @if(($ticket->status ?? '') === 'Closed' && ($feedback ?? null))
        <div class="app-card p-4 mt-4 border-start border-4 border-success">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Client Feedback</h6>
            <p class="mb-2">
                <strong>Rating:</strong>
                @if($feedback->rating === 'happy')
                    <span class="text-success"><i class="bi bi-emoji-smile me-1"></i>Happy with the service</span>
                @else
                    <span class="text-warning"><i class="bi bi-emoji-frown me-1"></i>Not satisfied</span>
                @endif
            </p>
            @if($feedback->comment)
            <p class="text-muted small mb-0"><strong>Comment:</strong> {{ e($feedback->comment) }}</p>
            @endif
            <p class="text-muted small mt-2 mb-0">Submitted {{ $feedback->created_at?->diffForHumans() ?? '' }}</p>
        </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="app-card p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Details</h6>
            <dl class="ticket-details mb-0">
                <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--geminia-border)!important">
                    <dt class="text-muted small mb-0">Status</dt>
                    <dd class="mb-0"><span class="ticket-status-badge ticket-status-{{ Str::slug($ticket->status ?? '') }}">{{ $ticket->status ?? '—' }}</span></dd>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--geminia-border)!important">
                    <dt class="text-muted small mb-0">Assigned To</dt>
                    <dd class="mb-0">{{ $ticket->assigned_to_name ?? '—' }}</dd>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--geminia-border)!important">
                    <dt class="text-muted small mb-0">Priority</dt>
                    <dd class="mb-0">{{ $ticket->priority ?? '—' }}</dd>
                </div>
                @if($ticket->category ?? null)
                <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--geminia-border)!important">
                    <dt class="text-muted small mb-0">Category</dt>
                    <dd class="mb-0">{{ $ticket->category }}</dd>
                </div>
                @endif
                <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--geminia-border)!important">
                    <dt class="text-muted small mb-0">Ticket #</dt>
                    <dd class="mb-0 font-monospace small">{{ $ticket->ticket_no ?? $ticket->ticketid }}</dd>
                </div>
                @if($ticket->createdtime ?? null)
                <div class="d-flex justify-content-between py-2">
                    <dt class="text-muted small mb-0">Created</dt>
                    <dd class="mb-0 small">{{ date('d M Y, H:i', strtotime($ticket->createdtime)) }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>

<style>
.ticket-workflow {
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem;
    padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--geminia-border); border-radius: 12px;
}
.ticket-workflow-step { display: flex; flex-direction: column; gap: 0.15rem; }
.ticket-workflow-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--geminia-text-muted); }
.ticket-workflow-value { font-weight: 500; color: var(--geminia-text); }
.ticket-workflow-arrow { color: var(--geminia-border); font-size: 0.9rem; }
.ticket-status-badge {
    font-size: 0.75rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 9999px; display: inline-block;
}
.ticket-status-open { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.ticket-status-in-progress, .ticket-status-In-Progress { background: rgba(217, 119, 6, 0.15); color: #b45309; }
.ticket-status-wait-for-response, .ticket-status-Wait-For-Response { background: rgba(14, 165, 233, 0.15); color: #0284c7; }
.ticket-status-closed { background: rgba(5, 150, 105, 0.15); color: #059669; }
.ticket-status-inactive { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.ticket-description { line-height: 1.65; color: var(--geminia-text); }
.btn-outline-danger { border-color: #fecaca; color: #dc2626; }
.btn-outline-danger:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }
</style>
@endsection
