@extends('layouts.app')

@section('title', 'Work activities by user')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Work activities by user</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Work activities by user</h1>
            <p class="reports-audit-subtitle mb-0">Calendar activities (tasks, events, meetings, calls), CRM tickets, and work ticket updates per user, for analysis.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <a href="{{ route('reports.export.work-activities', array_merge(request()->query(), ['scope' => 'summary', 'format' => 'xlsx'])) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Summary (Excel)
            </a>
            <a href="{{ route('reports.export.work-activities', array_merge(request()->query(), ['scope' => 'detail', 'format' => 'xlsx'])) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Detail (Excel)
            </a>
            <a href="{{ route('reports.export.work-activities', array_merge(request()->query(), ['scope' => 'detail', 'format' => 'csv'])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Detail (CSV)
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.work-activities') }}" class="row g-3 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">Date from</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" required>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">Date to</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" required>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Source</label>
                    <select name="source" class="form-select">
                        <option value="all" @selected($source === 'all')>All sources</option>
                        <option value="calendar" @selected($source === 'calendar')>Calendar only</option>
                        <option value="ticket" @selected($source === 'ticket')>CRM tickets only</option>
                        <option value="work" @selected($source === 'work')>Work tickets only</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Activity type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach(['Task', 'Event', 'Meeting', 'Call'] as $t)
                            <option value="{{ $t }}" @selected($activityType === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                @if($ownerFilter === null)
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">User</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">All users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((int) $assignedTo === (int) $u->id)>{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Subject, update, ticket…">
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">
                        <i class="bi bi-search me-1"></i>Run report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
        <div class="col">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Total activities</p>
                    <h3 class="mb-0">{{ number_format($summary['total_activities'] ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">{{ $dateFrom }} to {{ $dateTo }}</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Active users</p>
                    <h3 class="mb-0">{{ number_format($summary['users'] ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">Avg {{ number_format((float) ($summary['avg_per_user'] ?? 0), 1) }} per user</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Calendar activities</p>
                    <h3 class="mb-0">{{ number_format($summary['calendar_total'] ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">Tasks, events, meetings, calls</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">CRM tickets</p>
                    <h3 class="mb-0">{{ number_format($summary['ticket_total'] ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">Help desk tickets created</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card reports-card h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Work ticket updates</p>
                    <h3 class="mb-0">{{ number_format($summary['work_total'] ?? 0) }}</h3>
                    <p class="text-muted small mb-0 mt-2">{{ number_format(($summary['time_spent_minutes'] ?? 0) / 60, 1) }} hrs logged</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary: one row per user --}}
    <div class="reports-table-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th colspan="9">Activity summary by user</th>
                    </tr>
                    <tr>
                        <th style="width: 56px;">#</th>
                        <th>User</th>
                        <th>Department</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Calendar</th>
                        <th class="text-end">CRM tickets</th>
                        <th class="text-end">Work updates</th>
                        <th class="text-end">Time logged</th>
                        <th>Last activity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userRows as $idx => $row)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td class="fw-semibold">{{ $row->user_name }}</td>
                        <td>{{ $row->department ?: '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row->total) }}</td>
                        <td class="text-end">{{ number_format($row->calendar_count) }}</td>
                        <td class="text-end">{{ number_format($row->ticket_count) }}</td>
                        <td class="text-end">{{ number_format($row->work_count) }}</td>
                        <td class="text-end">{{ $row->time_spent_minutes > 0 ? number_format($row->time_spent_minutes / 60, 1) . ' hrs' : '—' }}</td>
                        <td>{{ $row->last_activity ? \Illuminate\Support\Carbon::parse($row->last_activity)->format('M j, Y H:i') : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No work activities found for this period.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detailed: one row per activity --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 no-print">
        <h6 class="mb-0 fw-semibold">Detailed activity log</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.export.work-activities', array_merge(request()->query(), ['scope' => 'detail', 'format' => 'xlsx'])) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
            </a>
            <a href="{{ route('reports.export.work-activities', array_merge(request()->query(), ['scope' => 'detail', 'format' => 'csv'])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>CSV
            </a>
        </div>
    </div>
    <div class="reports-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th colspan="8">
                            Detailed activity log
                            @if($detailTruncated)
                                <span class="text-muted text-lowercase fw-normal">(showing latest {{ number_format($detailLimit) }} — export for the full list)</span>
                            @endif
                        </th>
                    </tr>
                    <tr>
                        <th>User</th>
                        <th>Source</th>
                        <th>Type</th>
                        <th>Subject / update</th>
                        <th>Contact</th>
                        <th>Ticket</th>
                        <th>Status</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detailRows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->user_name }}</td>
                        <td>
                            @if($row->source === 'Calendar')
                                <span class="badge bg-primary bg-opacity-10 text-primary">Calendar</span>
                            @elseif($row->source === 'CRM Ticket')
                                <span class="badge bg-warning bg-opacity-10 text-warning">CRM ticket</span>
                            @else
                                <span class="badge bg-success bg-opacity-10 text-success">Work ticket</span>
                            @endif
                        </td>
                        <td>{{ $row->type }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($row->subject, 70) ?: '—' }}</td>
                        <td>{{ $row->contact ?: '—' }}</td>
                        <td>
                            @if($row->ticket_no)
                                @php
                                    $ticketUrl = $row->ticket_id
                                        ? ($row->ticket_module === 'work'
                                            ? route('work-tickets.show', $row->ticket_id)
                                            : route('tickets.show', $row->ticket_id))
                                        : null;
                                @endphp
                                @if($ticketUrl)
                                    <a href="{{ $ticketUrl }}" class="text-decoration-none fw-semibold">{{ $row->ticket_no }}</a>
                                @else
                                    <span class="fw-semibold">{{ $row->ticket_no }}</span>
                                @endif
                                @if($row->ticket_title)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($row->ticket_title, 50) }}</div>
                                @endif
                                @if($row->ticket_status)
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $row->ticket_status }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->status ?: '—' }}</td>
                        <td>{{ $row->activity_date ? \Illuminate\Support\Carbon::parse($row->activity_date)->format('M j, Y H:i') : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No activity records found for this period.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="reports-meta mt-3">Generated {{ now()->format('l, F j, Y g:i A') }}.</p>
</div>
@endsection
