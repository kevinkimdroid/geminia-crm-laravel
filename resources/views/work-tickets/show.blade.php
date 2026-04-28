@extends('layouts.app')

@section('title', $ticket->ticket_no . ' - Work Ticket')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <nav class="mb-2">
            <a href="{{ route('work-tickets.index') }}" class="text-muted small">Work Tickets</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">{{ $ticket->ticket_no }}</span>
        </nav>
        <h1 class="page-title mb-1">{{ $ticket->title }}</h1>
        <p class="page-subtitle mb-0">Track daily activity updates for this ticket.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('work-tickets.create') }}" class="btn btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i> New Ticket
        </a>
        <a href="{{ route('work-tickets.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mt-1">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @php
                    $statusClass = match($ticket->status) {
                        'Done', 'Closed' => 'bg-success',
                        'Blocked' => 'bg-danger',
                        'In Progress' => 'bg-primary',
                        'Cancelled' => 'bg-secondary',
                        default => 'bg-warning text-dark'
                    };
                    $tatDueAt = $ticket->tat_due_at ? \Carbon\Carbon::parse($ticket->tat_due_at) : null;
                    $tatBreached = false;
                    if ($tatDueAt) {
                        if (in_array(($ticket->status ?? ''), ['Done', 'Closed'], true) && !empty($ticket->completed_at)) {
                            $tatBreached = \Carbon\Carbon::parse($ticket->completed_at)->gt($tatDueAt);
                        } elseif (!in_array(($ticket->status ?? ''), ['Done', 'Closed'], true)) {
                            $tatBreached = now()->gt($tatDueAt);
                        }
                    }
                @endphp
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge {{ $statusClass }}">{{ $ticket->status }}</span>
                    <span class="badge text-bg-light border">{{ $ticket->priority }}</span>
                </div>
                <div class="mb-3">
                    @if(!$tatDueAt)
                        <span class="badge bg-secondary">No TAT configured</span>
                    @elseif($tatBreached)
                        <span class="badge bg-danger">TAT Breached</span>
                    @else
                        <span class="badge bg-success">Within TAT</span>
                    @endif
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Ticket #</dt>
                    <dd class="col-7">{{ $ticket->ticket_no }}</dd>
                    <dt class="col-5 text-muted">Assignee</dt>
                    <dd class="col-7">{{ $usersById[$ticket->assignee_id] ?? ('User #' . $ticket->assignee_id) }}</dd>
                    <dt class="col-5 text-muted">Reporting To</dt>
                    <dd class="col-7">
                        @if($ticket->reporting_manager_id)
                            {{ $usersById[$ticket->reporting_manager_id] ?? ('User #' . $ticket->reporting_manager_id) }}
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-5 text-muted">Created By</dt>
                    <dd class="col-7">{{ $usersById[$ticket->created_by] ?? ('User #' . $ticket->created_by) }}</dd>
                    <dt class="col-5 text-muted">Due Date</dt>
                    <dd class="col-7">{{ $ticket->due_date ? \Carbon\Carbon::parse($ticket->due_date)->format('d M Y') : '—' }}</dd>
                    <dt class="col-5 text-muted">TAT</dt>
                    <dd class="col-7">{{ (int) ($ticket->tat_hours ?? 0) > 0 ? ((int) $ticket->tat_hours . 'h') : '—' }}</dd>
                    <dt class="col-5 text-muted">TAT Due By</dt>
                    <dd class="col-7">{{ $ticket->tat_due_at ? \Carbon\Carbon::parse($ticket->tat_due_at)->format('d M Y H:i') : '—' }}</dd>
                    <dt class="col-5 text-muted">Created</dt>
                    <dd class="col-7">{{ $ticket->created_at?->format('d M Y H:i') }}</dd>
                    <dt class="col-5 text-muted">Last Update</dt>
                    <dd class="col-7">{{ $ticket->updated_at?->diffForHumans() }}</dd>
                </dl>
            </div>
        </div>

        @if(!empty(trim((string) $ticket->description)))
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Description</h6>
                <p class="mb-0 text-muted" style="white-space: pre-wrap;">{{ $ticket->description }}</p>
            </div>
        </div>
        @endif
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Add Daily Update</div>
            <div class="card-body">
                <form action="{{ route('work-tickets.updates.store', $ticket) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Update <span class="text-danger">*</span></label>
                        <textarea name="update_text" rows="3" class="form-control" placeholder="What did you do today? blockers? next step?" required>{{ old('update_text') }}</textarea>
                        @error('update_text')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Progress %</label>
                            <input type="number" name="progress_percent" min="0" max="100" value="{{ old('progress_percent') }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Time Spent (mins)</label>
                            <input type="number" name="time_spent_minutes" min="1" max="1440" value="{{ old('time_spent_minutes') }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Work Mode</label>
                            <select name="work_mode" class="form-select">
                                <option value="">Select</option>
                                @foreach(['Remote', 'Office', 'Field'] as $mode)
                                <option value="{{ $mode }}" {{ old('work_mode') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Set Status</label>
                            <select name="status_after_update" class="form-select">
                                <option value="">Keep current</option>
                                @foreach(['Open', 'In Progress', 'Blocked', 'Closed', 'Cancelled'] as $status)
                                <option value="{{ $status }}" {{ old('status_after_update') === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="is_blocked" name="is_blocked" {{ old('is_blocked') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_blocked">Blocked</label>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small fw-semibold">Blocker Reason</label>
                            <input type="text" name="blocker_reason" value="{{ old('blocker_reason') }}" class="form-control" placeholder="Dependency, waiting approval, missing data...">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary-custom mt-3">
                        <i class="bi bi-save me-1"></i> Save Daily Update
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Activity Timeline</div>
            <div class="card-body">
                @forelse($updates as $update)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div class="small text-muted">
                            <span class="fw-semibold text-dark">{{ $usersById[$update->user_id] ?? ('User #' . $update->user_id) }}</span>
                            <span class="mx-1">•</span>
                            {{ $update->created_at?->format('d M Y H:i') }}
                        </div>
                        <div class="d-flex gap-1 flex-wrap">
                            @if($update->status_after_update)
                                <span class="badge text-bg-light border">Status: {{ $update->status_after_update }}</span>
                            @endif
                            @if($update->work_mode)
                                <span class="badge text-bg-light border">{{ $update->work_mode }}</span>
                            @endif
                            @if(!is_null($update->progress_percent))
                                <span class="badge text-bg-light border">{{ $update->progress_percent }}%</span>
                            @endif
                            @if(!is_null($update->time_spent_minutes))
                                <span class="badge text-bg-light border">{{ $update->time_spent_minutes }} mins</span>
                            @endif
                            @if($update->is_blocked)
                                <span class="badge bg-danger">Blocked</span>
                            @endif
                        </div>
                    </div>
                    <p class="mb-0 mt-2" style="white-space: pre-wrap;">{{ $update->update_text }}</p>
                    @if(!empty(trim((string) $update->blocker_reason)))
                    <div class="small text-danger mt-2"><strong>Blocker:</strong> {{ $update->blocker_reason }}</div>
                    @endif
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-journal-text fs-3 d-block mb-2"></i>
                    No updates yet. Add the first daily update above.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
