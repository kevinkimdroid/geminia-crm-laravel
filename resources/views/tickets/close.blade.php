@extends('layouts.app')

@section('title', 'Close Ticket — ' . ($ticket->title ?? 'Ticket'))

@section('content')
<nav class="mb-3">
    <a href="{{ route('tickets.index') }}" class="text-muted small text-decoration-none">Tickets</a>
    <span class="text-muted mx-2">/</span>
    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="text-muted small text-decoration-none">{{ Str::limit($ticket->title ?? 'Ticket', 30) }}</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Close</span>
</nav>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="app-card p-4">
            <h1 class="h4 fw-bold mb-4"><i class="bi bi-check-circle text-success me-2"></i>Close Ticket</h1>
            <p class="text-muted small mb-4">Add a brief resolution (or leave blank to use "Closed").</p>

            <form method="POST" action="{{ route('tickets.close', $ticket->ticketid) }}">
                @csrf
                <label class="form-label fw-semibold">Solution <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="solution" class="form-control mb-4" rows="3" placeholder="e.g. Issue resolved, customer satisfied" autofocus>{{ old('solution') }}</textarea>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Close Ticket</button>
                    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
