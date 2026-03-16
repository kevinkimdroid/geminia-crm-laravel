@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
<div class="tickets-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="app-page-title mb-1">Tickets</h1>
            <p class="app-page-sub mb-0">Support tickets and customer issues</p>
        </div>
        <a href="{{ route('tickets.create') }}" class="app-topbar-add">
            <i class="bi bi-plus-lg"></i> New Ticket
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Status pills --}}
    <div class="tickets-status-pills mb-4">
        <a href="{{ route('tickets.index') }}" class="tickets-pill {{ !($currentList ?? '') ? 'active' : '' }}">
            All <span class="tickets-pill-count">{{ number_format(($ticketCounts['Open'] ?? 0) + ($ticketCounts['In Progress'] ?? 0) + ($ticketCounts['Closed'] ?? 0) + ($ticketCounts['Wait For Response'] ?? 0) + ($ticketCounts['Inactive'] ?? 0) + ($ticketCounts['Unassigned'] ?? 0)) }}</span>
        </a>
        <a href="{{ route('tickets.index', ['list' => 'Open']) }}" class="tickets-pill tickets-pill-open {{ ($currentList ?? '') === 'Open' ? 'active' : '' }}">
            Open <span class="tickets-pill-count">{{ number_format($ticketCounts['Open'] ?? 0) }}</span>
        </a>
        <a href="{{ route('tickets.index', ['list' => 'In Progress']) }}" class="tickets-pill tickets-pill-progress {{ ($currentList ?? '') === 'In Progress' ? 'active' : '' }}">
            In Progress <span class="tickets-pill-count">{{ number_format($ticketCounts['In Progress'] ?? 0) }}</span>
        </a>
        <a href="{{ route('tickets.index', ['list' => 'Wait For Response']) }}" class="tickets-pill tickets-pill-wait {{ ($currentList ?? '') === 'Wait For Response' ? 'active' : '' }}">
            Awaiting <span class="tickets-pill-count">{{ number_format($ticketCounts['Wait For Response'] ?? 0) }}</span>
        </a>
        <a href="{{ route('tickets.index', ['list' => 'Closed']) }}" class="tickets-pill tickets-pill-closed {{ ($currentList ?? '') === 'Closed' ? 'active' : '' }}">
            Closed <span class="tickets-pill-count">{{ number_format($ticketCounts['Closed'] ?? 0) }}</span>
        </a>
        <a href="{{ route('tickets.index', ['list' => 'Inactive']) }}" class="tickets-pill tickets-pill-inactive {{ ($currentList ?? '') === 'Inactive' ? 'active' : '' }}">
            Inactive <span class="tickets-pill-count">{{ number_format($ticketCounts['Inactive'] ?? 0) }}</span>
        </a>
        @if(($ticketCounts['Unassigned'] ?? 0) > 0)
        <a href="{{ route('tickets.index', ['list' => 'Unassigned']) }}" class="tickets-pill tickets-pill-unassigned {{ ($currentList ?? '') === 'Unassigned' ? 'active' : '' }}">
            Unassigned <span class="tickets-pill-count">{{ number_format($ticketCounts['Unassigned']) }}</span>
        </a>
        @endif
    </div>

    <div class="app-card overflow-hidden">
        {{-- Search --}}
        <form action="{{ route('tickets.index') }}" method="GET" class="tickets-toolbar" id="tickets-search-form">
            @if($currentList ?? '')<input type="hidden" name="list" value="{{ $currentList }}">@endif
            <div class="tickets-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" id="tickets-search-input" class="form-control border-0 bg-transparent" placeholder="Search tickets, contacts, assigned to… (Ctrl+K)" value="{{ $search ?? '' }}">
            </div>
            <select name="assigned_to" class="form-select form-select-sm tickets-assign-filter" onchange="this.form.submit()">
                <option value="">All assignees</option>
                @foreach($users ?? [] as $u)
                    <option value="{{ $u->id }}" {{ ($assignedTo ?? null) == $u->id ? 'selected' : '' }}>
                        {{ trim($u->first_name . ' ' . $u->last_name) ?: $u->user_name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px;padding:.4rem 1rem">Search</button>
            <a href="{{ route('tickets.export', array_filter(['list' => $currentList ?? null, 'search' => $search ?? null, 'assigned_to' => $assignedTo ?? null])) }}" class="btn btn-sm btn-outline-secondary" title="Export to Excel"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel</a>
            <a href="{{ route('tickets.create') }}" class="btn btn-sm app-topbar-add">Add Ticket</a>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover tickets-table mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Title</th>
                        <th>Contact</th>
                        <th title="Policy linked when ticket was created (from Create Ticket / Serve Client)">Policy</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        @php
                            $contactName = trim(($ticket->contact_first ?? '') . ' ' . ($ticket->contact_last ?? '')) ?: '—';
                            $detailUrl = ($ticket->contact_id ?? null)
                                ? route('contacts.show', $ticket->contact_id)
                                : route('tickets.show', $ticket->ticketid);
                            // POLICY = policy_number column only. From contact cf_860/cf_856/cf_872 or "Related policy" in description.
                            // Never use contact_id — it must be policy_number only.
                            $policyNum = pick_policy_excluding_pin($ticket->cf_860 ?? null, $ticket->cf_856 ?? null, $ticket->cf_872 ?? null);
                            if (!$policyNum && !empty($ticket->description ?? '') && preg_match('/Related policy:\s*([^\n]+)/i', $ticket->description, $m)) {
                                $p = trim($m[1]);
                                $cid = (string)($ticket->contact_id ?? '');
                                if ($p !== '' && $p !== $cid && !looks_like_kra_pin($p) && !looks_like_client_id($p)) {
                                    $policyNum = $p;
                                }
                            }
                            $ownerName = trim(($ticket->owner_first ?? '') . ' ' . ($ticket->owner_last ?? '')) ?: ($ticket->owner_username ?? '—');
                            $words = array_filter(explode(' ', $contactName));
                            $initials = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1)) : ($contactName !== '—' ? strtoupper(substr($contactName, 0, 1)) : '?');
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ $detailUrl }}" class="tickets-id-link">{{ $ticket->ticket_no ?? 'TT' . $ticket->ticketid }}</a>
                            </td>
                            <td>
                                <a href="{{ $detailUrl }}" class="tickets-title-link">{{ Str::limit($ticket->title ?? 'Untitled', 40) }}</a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="tickets-avatar">{{ $initials }}</div>
                                    @if($contactName !== '—' && ($ticket->contact_id ?? null))
                                        <a href="{{ route('contacts.show', $ticket->contact_id) }}" class="text-decoration-none text-dark">{{ $contactName }}</a>
                                    @else
                                        <span class="text-muted">{{ $contactName }}</span>
                                    @endif
                                </div>
                            </td>
                            <td><span class="text-muted small font-monospace" title="{{ $policyNum ? 'Linked when ticket was created' : '' }}">{{ $policyNum ?? '—' }}</span></td>
                            <td>
                                <span class="tickets-status-badge tickets-status-{{ Str::slug($ticket->status ?? '') }}">{{ $ticket->status ?? '—' }}</span>
                            </td>
                            <td><span class="text-muted small">{{ $ticket->priority ?? 'Normal' }}</span></td>
                            <td><span class="text-muted small">{{ $ownerName }}</span></td>
                            <td><span class="text-muted small">{{ $ticket->createdtime ? date('d M Y', strtotime($ticket->createdtime)) : '—' }}</span></td>
                            <td class="text-end">
                                @if(($ticket->status ?? '') !== 'Closed')
                                <a href="{{ route('tickets.close.form', $ticket->ticketid) }}" class="btn btn-sm btn-link text-success p-1" title="Close"><i class="bi bi-check-circle"></i></a>
                                @endif
                                <a href="{{ $detailUrl }}" class="btn btn-sm btn-link text-muted p-1" title="View"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="text-center py-5">
                                    <div class="tickets-empty-icon mb-3"><i class="bi bi-ticket-perforated"></i></div>
                                    <h5 class="fw-bold mb-2">No tickets found</h5>
                                    <p class="text-muted mb-3">Get started by creating your first support ticket.</p>
                                    <a href="{{ route('tickets.create') }}" class="app-topbar-add"><i class="bi bi-plus-lg"></i> Create Ticket</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div class="tickets-pagination">
                <span class="text-muted small">Showing {{ $tickets->firstItem() ?? 0 }}–{{ $tickets->lastItem() ?? 0 }} of {{ $tickets->total() }}</span>
                {{ $tickets->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<style>
.tickets-status-pills { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tickets-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; border-radius: 9999px;
    background: #fff; border: 1px solid var(--geminia-border);
    color: var(--geminia-text); text-decoration: none; font-size: 0.875rem; font-weight: 500;
    transition: all 0.2s;
}
.tickets-pill:hover { border-color: var(--geminia-primary); color: var(--geminia-primary); }
.tickets-pill.active {
    background: var(--geminia-primary); border-color: var(--geminia-primary); color: #fff;
}
.tickets-pill-count { font-size: 0.75rem; opacity: 0.85; }
.tickets-pill-open .tickets-pill-count { color: inherit; }
.tickets-pill-progress { border-color: rgba(217, 119, 6, 0.4); }
.tickets-pill-progress.active { background: #d97706; border-color: #d97706; }
.tickets-pill-wait { border-color: rgba(14, 165, 233, 0.4); }
.tickets-pill-wait.active { background: #0ea5e9; border-color: #0ea5e9; }
.tickets-pill-closed { border-color: rgba(5, 150, 105, 0.4); }
.tickets-pill-closed.active { background: #059669; border-color: #059669; }
.tickets-pill-inactive { border-color: rgba(107, 114, 128, 0.4); }
.tickets-pill-inactive.active { background: #6b7280; border-color: #6b7280; }
.tickets-pill-unassigned { border-color: rgba(220, 38, 38, 0.4); }
.tickets-pill-unassigned.active { background: #dc2626; border-color: #dc2626; }

.tickets-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;
    padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid var(--geminia-border);
}
.tickets-search {
    flex: 1; min-width: 220px; max-width: 460px;
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--geminia-border); border-radius: 8px;
}
.tickets-search i { color: var(--geminia-text-muted); }
.tickets-search input { padding: 0; font-size: 0.9rem; }
.tickets-search input:focus { box-shadow: none; }
.tickets-assign-filter {
    min-width: 160px; font-size: 0.875rem; border-radius: 8px;
    border-color: var(--geminia-border); background: #fff; padding: 0.4rem 0.75rem;
}

.tickets-table th { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--geminia-text-muted); padding: 1rem 1.25rem; white-space: nowrap; }
.tickets-table td { padding: 1rem 1.25rem; vertical-align: middle; }
.tickets-table tbody tr:hover { background: var(--geminia-primary-muted); }
.tickets-id-link { font-weight: 600; color: var(--geminia-primary); text-decoration: none; }
.tickets-id-link:hover { color: var(--geminia-primary-dark); text-decoration: underline; }
.tickets-title-link { font-weight: 500; color: var(--geminia-text); text-decoration: none; }
.tickets-title-link:hover { color: var(--geminia-primary); }
.tickets-avatar {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--geminia-primary); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700; flex-shrink: 0;
}
.tickets-status-badge {
    font-size: 0.7rem; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 9999px; display: inline-block;
}
.tickets-status-open { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.tickets-status-in-progress { background: rgba(217, 119, 6, 0.15); color: #b45309; }
.tickets-status-In-Progress { background: rgba(217, 119, 6, 0.15); color: #b45309; }
.tickets-status-wait-for-response, .tickets-status-Wait-For-Response { background: rgba(14, 165, 233, 0.15); color: #0284c7; }
.tickets-status-closed { background: rgba(5, 150, 105, 0.15); color: #059669; }
.tickets-status-inactive { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.tickets-status-unassigned { background: rgba(220, 38, 38, 0.1); color: #dc2626; }

.tickets-empty-icon {
    width: 72px; height: 72px; margin: 0 auto;
    background: var(--geminia-primary-muted); color: var(--geminia-primary);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
}
.tickets-pagination {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid var(--geminia-border);
}
.tickets-pagination .pagination { margin: 0; }
.tickets-pagination .page-link { border-radius: 8px; border-color: var(--geminia-border); color: var(--geminia-text); }
.tickets-pagination .page-link:hover { background: var(--geminia-primary-muted); border-color: var(--geminia-primary); color: var(--geminia-primary); }
.tickets-pagination .page-item.active .page-link { background: var(--geminia-primary); border-color: var(--geminia-primary); }
</style>

@push('scripts')
<script>
(function(){
    var form = document.getElementById('tickets-search-form');
    var input = document.getElementById('tickets-search-input');
    if (!form || !input) return;
    var debounceTimer;
    input.addEventListener('input', function(){
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function(){ form.submit(); }, 400);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === '/' && document.activeElement !== input && !e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
            e.preventDefault();
            input.focus();
        } else if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
</script>
@endpush
@endsection
