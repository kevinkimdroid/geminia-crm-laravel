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
        <a href="{{ route('tickets.edit', $ticket->ticketid) }}" class="btn btn-sm btn-success">
            <i class="bi bi-check-circle me-1"></i> Close Ticket
        </a>
        @endif
        <a href="{{ route('tickets.edit', $ticket->ticketid) }}" class="btn btn-sm app-btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <form action="{{ route('tickets.destroy', $ticket->ticketid) }}" method="POST" onsubmit="return confirm('Delete this ticket?');" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
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
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Description Details</h6>
            <div class="ticket-description">{{ $ticket->description ? nl2br(e($ticket->description)) : 'No description.' }}</div>
        </div>
        @if($ticket->solution ?? null)
        <div class="app-card p-4 mt-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Ticket Resolution</h6>
            <div class="ticket-description">{{ nl2br(e($ticket->solution)) }}</div>
        </div>
        @elseif(($ticket->status ?? '') !== 'Closed')
        <div class="app-card p-4 mt-4 border border-success">
            <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Add a solution and change status to Closed to resolve this ticket.</p>
            <a href="{{ route('tickets.edit', $ticket->ticketid) }}" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i> Close Ticket</a>
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
.ticket-status-badge {
    font-size: 0.75rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 9999px; display: inline-block;
}
.ticket-status-open { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.ticket-status-in-progress, .ticket-status-In-Progress { background: rgba(217, 119, 6, 0.15); color: #b45309; }
.ticket-status-wait-for-response, .ticket-status-Wait-For-Response { background: rgba(14, 165, 233, 0.15); color: #0284c7; }
.ticket-status-closed { background: rgba(5, 150, 105, 0.15); color: #059669; }
.ticket-description { line-height: 1.65; color: var(--geminia-text); }
.btn-outline-danger { border-color: #fecaca; color: #dc2626; }
.btn-outline-danger:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }
</style>
@endsection
