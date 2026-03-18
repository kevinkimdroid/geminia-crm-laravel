@extends('layouts.app')

@section('title', 'Calls Summary Report')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Calls Summary</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Calls Summary Report</h1>
            <p class="reports-audit-subtitle mb-0">PBX call volume and duration.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <a href="{{ route('tools.pbx-manager') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-telephone me-1"></i>PBX Manager
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Print report">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

@php
    $totalMin = $total_duration_sec ? round($total_duration_sec / 60, 1) : 0;
@endphp

    <div class="row g-4">
        <div class="col-md-4">
            <div class="reports-table-card p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Calls</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($total_calls ?? 0) }}</h3>
                <p class="text-muted small mb-0">All recorded PBX calls</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="reports-table-card p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Talk Time</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($totalMin) }} min</h3>
                <p class="text-muted small mb-0">Combined call duration</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="reports-table-card p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Avg per Call</h6>
                <h3 class="reports-stat-value mb-1">{{ $total_calls ? number_format($totalMin / $total_calls, 1) : 0 }} min</h3>
                <p class="text-muted small mb-0">Average duration</p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="reports-table-card p-4">
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
        <div class="col-lg-6">
            <div class="reports-table-card p-4">
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
    <div class="reports-meta text-muted small mt-3 py-2">
        <i class="bi bi-clock me-1"></i>Report generated: {{ now()->format('l, F j, Y \a\t g:i A') }}
    </div>
</div>

<style>
.reports-stat-value { font-size: 1.75rem; font-weight: 700; color: var(--geminia-primary, #1A468A); }
</style>
@endsection
