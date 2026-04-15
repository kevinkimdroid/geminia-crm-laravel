@extends('layouts.app')

@section('title', 'Work Tickets')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title mb-1">Work Tickets</h1>
        <p class="page-subtitle mb-0">Daily remote-work tracking with manager visibility.</p>
    </div>
    <div class="d-flex gap-2">
        @if($canSeeAll ?? false)
        <a href="{{ route('setup.users') }}" class="btn btn-outline-secondary">
            <i class="bi bi-diagram-3 me-1"></i> Reporting Lines
        </a>
        @endif
        <a href="{{ route('work-tickets.create') }}" class="btn btn-primary-custom">
            <i class="bi bi-plus-lg me-1"></i> New Work Ticket
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mb-3 mt-1">
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Total</div><div class="h5 mb-0">{{ $stats['total'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Open</div><div class="h5 mb-0">{{ $stats['open'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">In Progress</div><div class="h5 mb-0">{{ $stats['in_progress'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Blocked</div><div class="h5 mb-0 text-danger">{{ $stats['blocked'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Done</div><div class="h5 mb-0 text-success">{{ $stats['done'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">TAT Breached</div><div class="h5 mb-0 text-danger">{{ $stats['tat_breached'] ?? 0 }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('work-tickets.index', array_merge(request()->except('scope', 'page'), ['scope' => 'mine'])) }}"
               class="btn btn-sm {{ ($scope ?? 'mine') === 'mine' ? 'btn-primary' : 'btn-outline-primary' }}">My Tickets</a>
            @if($canSeeTeam ?? false)
            <a href="{{ route('work-tickets.index', array_merge(request()->except('scope', 'page'), ['scope' => 'team'])) }}"
               class="btn btn-sm {{ ($scope ?? '') === 'team' ? 'btn-primary' : 'btn-outline-primary' }}">Team Activity</a>
            @endif
            @if($canSeeAll ?? false)
            <a href="{{ route('work-tickets.index', array_merge(request()->except('scope', 'page'), ['scope' => 'all'])) }}"
               class="btn btn-sm {{ ($scope ?? '') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All Tickets</a>
            @endif
        </div>
    </div>
    <div class="card-body border-bottom bg-light">
        <form method="GET" action="{{ route('work-tickets.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="scope" value="{{ $scope ?? 'mine' }}">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['Open', 'In Progress', 'Blocked', 'Done', 'Cancelled'] as $s)
                    <option value="{{ $s }}" {{ ($status ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Ticket no, title, description">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
            <div class="col-auto">
                <a href="{{ route('work-tickets.index', ['scope' => $scope ?? 'mine']) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ticket #</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Assignee</th>
                    <th>Reporting To</th>
                    <th>TAT</th>
                    <th>TAT Due</th>
                    <th>SLA</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('work-tickets.show', $ticket) }}" class="fw-semibold">{{ $ticket->ticket_no }}</a></td>
                    <td>{{ $ticket->title }}</td>
                    <td>
                        @php
                            $statusClass = match($ticket->status) {
                                'Done' => 'bg-success',
                                'Blocked' => 'bg-danger',
                                'In Progress' => 'bg-primary',
                                'Cancelled' => 'bg-secondary',
                                default => 'bg-warning text-dark'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $ticket->status }}</span>
                    </td>
                    <td>{{ $ticket->priority }}</td>
                    <td>{{ $usersById[$ticket->assignee_id] ?? ('User #' . $ticket->assignee_id) }}</td>
                    <td>
                        @if($ticket->reporting_manager_id)
                            {{ $usersById[$ticket->reporting_manager_id] ?? ('User #' . $ticket->reporting_manager_id) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ (int) ($ticket->tat_hours ?? 0) > 0 ? ((int) $ticket->tat_hours . 'h') : '—' }}</td>
                    <td class="text-nowrap">{{ $ticket->tat_due_at ? \Carbon\Carbon::parse($ticket->tat_due_at)->format('d M Y H:i') : '—' }}</td>
                    <td>
                        @php
                            $tatDueAt = !empty($ticket->tat_due_at) ? \Carbon\Carbon::parse($ticket->tat_due_at) : null;
                            $tatBreached = false;
                            if ($tatDueAt) {
                                if (($ticket->status ?? '') === 'Done' && !empty($ticket->completed_at)) {
                                    $tatBreached = \Carbon\Carbon::parse($ticket->completed_at)->gt($tatDueAt);
                                } elseif (($ticket->status ?? '') !== 'Done') {
                                    $tatBreached = now()->gt($tatDueAt);
                                }
                            }
                        @endphp
                        @if(!$tatDueAt)
                            <span class="badge bg-secondary">No TAT</span>
                        @elseif($tatBreached)
                            <span class="badge bg-danger">Breached</span>
                        @else
                            <span class="badge bg-success">Within TAT</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $ticket->updated_at?->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="bi bi-kanban fs-3 d-block mb-2"></i>
                        No work tickets found for this view.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($tickets, 'links'))
    <div class="card-body border-top">
        {{ $tickets->links() }}
    </div>
    @endif
</div>
@endsection
