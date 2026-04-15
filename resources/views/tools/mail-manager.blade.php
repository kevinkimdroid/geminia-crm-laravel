@extends('layouts.app')

@section('title', 'Mail Manager')

@section('content')
<div class="page-header mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="page-title">Mail Manager</h1>
            <p class="page-subtitle mb-0">Emails from {{ $useMicrosoftGraph ?? false ? config('microsoft-graph.mailbox') . ' (Microsoft Graph)' : ($useEmailService ?? false ? config('email-service.sender') . ' (HTTP)' : config('email-service.sender', 'life@geminialife.co.ke')) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tools.mail-manager.create') }}" class="btn btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i> Create Email
            </a>
            <form action="{{ route('tools.mail-manager.fetch') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Fetch Emails
                </button>
            </form>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center py-2" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $mailHealth = $mailFetchHealth ?? [];
    $lastSuccess = !empty($mailHealth['last_success_at']) ? \Carbon\Carbon::parse($mailHealth['last_success_at']) : null;
    $lastAttempt = !empty($mailHealth['last_attempt_at']) ? \Carbon\Carbon::parse($mailHealth['last_attempt_at']) : null;
    $healthIsOk = ($mailHealth['status'] ?? 'unknown') === 'ok';
    $healthIsStale = (bool) ($mailHealth['is_stale'] ?? true);
    $staleMinutes = (int) ($mailHealth['stale_minutes'] ?? 15);
@endphp

<div class="alert {{ ($healthIsOk && !$healthIsStale) ? 'alert-info' : 'alert-warning' }} py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="small">
        <strong>Mail Fetch Health:</strong>
        @if($healthIsStale)
            <span class="fw-semibold text-warning-emphasis">Stale</span> (no successful fetch in {{ $staleMinutes }}+ min).
        @endif
        @if($lastSuccess)
            Last success {{ $lastSuccess->diffForHumans() }} ({{ $lastSuccess->format('M d, Y H:i') }})
            · fetched {{ (int) ($mailHealth['fetched'] ?? 0) }}, stored {{ (int) ($mailHealth['stored'] ?? 0) }}
        @else
            No successful fetch recorded yet.
        @endif
        @if($lastAttempt)
            · last attempt {{ $lastAttempt->diffForHumans() }}
        @endif
        @if(!empty($mailHealth['error']))
            · error: {{ \Illuminate\Support\Str::limit($mailHealth['error'], 180) }}
        @endif
    </div>
</div>

{{-- Fixed-height container: only inner panels scroll, not the page --}}
<div class="mail-manager-panels d-flex border rounded overflow-hidden bg-white shadow-sm">
    {{-- Left: Email list --}}
    <div class="mail-list-panel flex-shrink-0 d-flex flex-column border-end">
        <div class="p-2 border-bottom bg-light flex-shrink-0">
            <form method="GET" action="{{ route('tools.mail-manager') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" name="selected" value="{{ $selected ?? '' }}">
                <div class="input-group input-group-sm flex-grow-1" style="min-width: 140px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ $search ?? '' }}">
                </div>
                <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="50" {{ ($perPageParam ?? 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ ($perPageParam ?? '') == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ ($perPageParam ?? '') == 250 ? 'selected' : '' }}>250</option>
                    <option value="all" {{ ($perPageParam ?? '') === 'all' ? 'selected' : '' }}>All</option>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Search</button>
                @if($search ?? null)
                    <a href="{{ route('tools.mail-manager', ['selected' => $selected ?? '', 'per_page' => $perPageParam ?? '50']) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>
        <div class="mail-list-scroll flex-grow-1 overflow-y-auto">
            @forelse($emails ?? [] as $email)
            <a href="{{ route('tools.mail-manager', ['selected' => $email->id, 'search' => $search ?? '', 'page' => $page ?? 1, 'per_page' => $perPageParam ?? '50']) }}"
               class="list-group-item list-group-item-action py-2 px-3 text-decoration-none border-0 border-bottom rounded-0 {{ ($selected ?? null) == $email->id ? 'active' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="fw-semibold small text-truncate">{{ $email->from_name ?: $email->from_address }}</span>
                    <small class="text-nowrap flex-shrink-0 {{ ($selected ?? null) == $email->id ? 'text-white-50' : 'text-muted' }}">{{ $email->date ? \Carbon\Carbon::parse($email->date)->format('M d') : '' }}</small>
                </div>
                <div class="small mt-0 mb-1 {{ ($selected ?? null) == $email->id ? 'text-white' : 'text-dark' }}">
                    {{ Str::limit($email->subject ?? '(No subject)', 55) }}
                    @if($email->has_attachments)
                        <i class="bi bi-paperclip ms-1 opacity-75"></i>
                    @endif
                    @if($email->ticket_id ?? null)
                        <i class="bi bi-ticket-perforated-fill ms-1 opacity-75" title="Linked to ticket"></i>
                    @endif
                </div>
            </a>
            @empty
            <div class="list-group-item text-center py-5 text-muted small border-0">
                @if($search ?? null)
                    No emails match your search.
                @else
                    No emails yet. Click "Fetch Emails" to pull emails{{ ($useMicrosoftGraph ?? false) ? ' via Microsoft Graph' : (($useEmailService ?? false) ? ' via HTTP' : ' from ' . (config('email-service.sender') ?: 'life@geminialife.co.ke')) }}.
                @endif
            </div>
            @endforelse
        </div>
        @if(($total ?? 0) > 0)
        <div class="p-2 border-top bg-light flex-shrink-0 d-flex justify-content-between align-items-center">
            <span class="text-muted small">{{ number_format($total) }} emails</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ ($page ?? 1) <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('tools.mail-manager', ['page' => ($page ?? 1) - 1, 'search' => $search ?? '', 'selected' => $selected ?? '', 'per_page' => $perPageParam ?? '50']) }}"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <li class="page-item {{ ($page ?? 1) * ($perPage ?? 50) >= ($total ?? 0) ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('tools.mail-manager', ['page' => ($page ?? 1) + 1, 'search' => $search ?? '', 'selected' => $selected ?? '', 'per_page' => $perPageParam ?? '50']) }}"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        @endif
    </div>

    {{-- Right: Email preview – only this panel scrolls when viewing email --}}
    <div class="mail-preview-panel flex-grow-1 d-flex flex-column min-w-0">
        @if($selectedEmail ?? null)
        <div class="mail-preview-scroll flex-grow-1 overflow-y-auto">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="min-w-0">
                        <h5 class="mb-1">{{ $selectedEmail->subject ?? '(No subject)' }}</h5>
                        <p class="text-muted small mb-0">
                            {{ $selectedEmail->from_name ? $selectedEmail->from_name . ' <' . $selectedEmail->from_address . '>' : $selectedEmail->from_address }}
                            · {{ $selectedEmail->date ? \Carbon\Carbon::parse($selectedEmail->date)->format('M d, Y H:i') : '' }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        @if($selectedEmail->ticket_id ?? null)
                            <a href="{{ route('tickets.show', $selectedEmail->ticket_id) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-ticket-perforated me-1"></i> View Ticket
                            </a>
                        @else
                            <a href="{{ route('tools.mail-manager.create-ticket', $selectedEmail->id) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Create Ticket
                            </a>
                        @endif
                    </div>
                </div>
                @if($selectedEmail->to_addresses)
                    <p class="mb-1 small"><strong>To:</strong> {{ $selectedEmail->to_addresses }}</p>
                @endif
                @if($selectedEmail->cc_addresses)
                    <p class="mb-2 small"><strong>Cc:</strong> {{ $selectedEmail->cc_addresses }}</p>
                @endif
                @if($selectedEmail->has_attachments)
                    <p class="mb-2 text-muted small"><i class="bi bi-paperclip"></i> Has attachments</p>
                @endif
                <hr>
                <div class="email-body" style="max-width: 720px;">
                    @if($selectedEmail->body_html)
                        {!! $selectedEmail->body_html !!}
                    @else
                        <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;">{{ $selectedEmail->body_text ?? 'No content' }}</pre>
                    @endif
                </div>
            </div>
        </div>
        @else
        <div class="mail-preview-empty flex-grow-1 d-flex align-items-center justify-content-center text-muted">
            <div class="text-center py-5">
                <i class="bi bi-envelope-open display-4 mb-3 opacity-25"></i>
                <p class="mb-0">Select an email to read</p>
            </div>
        </div>
        @endif
    </div>
</div>

<style>
.mail-manager-panels {
    height: calc(100vh - 220px);
    min-height: 400px;
}
.mail-list-panel {
    width: 360px;
    max-width: 40%;
}
.mail-list-scroll {
    min-height: 0;
}
.mail-preview-panel {
    min-height: 0;
}
.mail-preview-scroll {
    min-height: 0;
}
.mail-preview-empty {
    min-height: 0;
}
.email-body img {
    max-width: 100%;
    height: auto;
}
</style>
@endsection
