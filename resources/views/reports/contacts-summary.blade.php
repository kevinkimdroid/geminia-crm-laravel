@extends('layouts.app')

@section('title', 'Contacts Summary Report')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav class="mb-2">
            <a href="{{ route('reports') }}" class="text-muted small">Reports</a>
            <span class="text-muted mx-1">/</span>
            <span class="text-dark">Contacts Summary</span>
        </nav>
        <h1 class="page-title mb-1">Contacts Summary Report</h1>
        <p class="page-subtitle mb-0">Total and new contacts overview.</p>
    </div>
    <form action="{{ route('reports.contacts-summary') }}" method="GET" class="d-flex gap-2 align-items-center">
        <select name="days" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="7" {{ ($days ?? 30) == 7 ? 'selected' : '' }}>Last 7 days</option>
            <option value="30" {{ ($days ?? 30) == 30 ? 'selected' : '' }}>Last 30 days</option>
            <option value="90" {{ ($days ?? 30) == 90 ? 'selected' : '' }}>Last 90 days</option>
        </select>
    </form>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Total Contacts</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($total ?? 0) }}</h3>
                <p class="text-muted small mb-0">All contacts in CRM</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card reports-card h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">New in Last {{ $days ?? 30 }} Days</h6>
                <h3 class="reports-stat-value mb-1">{{ number_format($new_last_days ?? 0) }}</h3>
                <p class="text-muted small mb-0">Contacts added in this period</p>
            </div>
        </div>
    </div>
    <div class="col-12">
        <a href="{{ route('contacts.index') }}" class="btn btn-primary">
            <i class="bi bi-people me-1"></i>View Contacts
        </a>
    </div>
</div>

<style>
.reports-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.reports-stat-value { font-size: 2rem; font-weight: 700; color: var(--primary, #0E4385); }
</style>
@endsection
