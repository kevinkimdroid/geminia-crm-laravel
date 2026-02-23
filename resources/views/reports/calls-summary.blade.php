@extends('layouts.app')

@section('title', 'Calls Summary Report')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav class="mb-2">
            <a href="{{ route('reports') }}" class="text-muted small">Reports</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">Calls Summary</span>
        </nav>
        <h1 class="page-title mb-1">Calls Summary Report</h1>
        <p class="page-subtitle mb-0">PBX call volume and duration.</p>
    </div>
    <a href="{{ route('tools.pbx-manager') }}" class="btn btn-outline-secondary">
        <i class="bi bi-telephone me-1"></i>PBX Manager
    </a>
</div>

@php
    $totalMin = $total_duration_sec ? round($total_duration_sec / 60, 1) : 0;
@endphp

<div class="row g-4">
    <div class="col-md-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Calls</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($total_calls ?? 0) }}</h3>
                <p class="text-muted small mb-0">All recorded PBX calls</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Talk Time</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($totalMin) }} min</h3>
                <p class="text-muted small mb-0">Combined call duration</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Avg per Call</h6>
                <h3 class="reports-stat-value mb-1">{{ $total_calls ? number_format($totalMin / $total_calls, 1) : 0 }} min</h3>
                <p class="text-muted small mb-0">Average duration</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Calls by Status</h6>
                @if (count($by_status ?? []) > 0)
                <div class="reports-list">
                    @foreach ($by_status as $status => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-capitalize">{{ $status }}</span>
                        <strong>{{ number_format($cnt) }}</strong>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No call data yet</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Calls by User</h6>
                @if (count($by_user ?? []) > 0)
                <div class="reports-list">
                    @foreach ($by_user as $row)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $row->user_name ?? '—' }}</span>
                        <strong>{{ number_format($row->cnt ?? 0) }}</strong>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No call data yet</p>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.reports-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.reports-stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary, #0E4385); }
</style>
@endsection
