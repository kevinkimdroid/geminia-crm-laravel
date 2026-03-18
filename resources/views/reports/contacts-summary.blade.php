@extends('layouts.app')

@section('title', 'Contacts Summary Report')

@section('content')
@include('partials.reports-audit-styles')
<div class="reports-audit-page">
    <div class="reports-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <nav class="reports-breadcrumb mb-2">
                <a href="{{ route('reports') }}">Reports</a>
                <span class="reports-breadcrumb-sep">/</span>
                <span class="reports-breadcrumb-current">Contacts Summary</span>
            </nav>
            <h1 class="reports-audit-title mb-1">Contacts Summary Report</h1>
            <p class="reports-audit-subtitle mb-0">Total and new contacts overview.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center no-print">
            <form action="{{ route('reports.contacts-summary') }}" method="GET" class="d-flex gap-2 align-items-center">
                <select name="days" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="7" {{ ($days ?? 30) == 7 ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ ($days ?? 30) == 30 ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ ($days ?? 30) == 90 ? 'selected' : '' }}>Last 90 days</option>
                </select>
            </form>
            <a href="{{ route('contacts.index') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-people me-1"></i>View Contacts
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Print report">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="reports-table-card p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Contacts</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($total ?? 0) }}</h3>
                <p class="text-muted small mb-0">All contacts in CRM</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="reports-table-card p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">New in Last {{ $days ?? 30 }} Days</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($new_last_days ?? 0) }}</h3>
                <p class="text-muted small mb-0">Contacts added in this period</p>
            </div>
        </div>
    </div>
    <div class="reports-meta text-muted small mt-3 py-2">
        <i class="bi bi-clock me-1"></i>Report generated: {{ now()->format('l, F j, Y \a\t g:i A') }}
    </div>
</div>

<style>
.reports-stat-value { font-size: 2rem; font-weight: 700; color: var(--geminia-primary, #1A468A); }
</style>
@endsection
