@extends('layouts.app')

@section('title', 'Maturing Policies')

@section('content')
<nav class="mb-3">
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Maturing Policies</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1">Maturing Policies</h1>
        <p class="app-page-sub mb-0">Policies maturing within the next {{ $days }} days — create tickets to follow up</p>
    </div>
    <form method="GET" action="{{ route('support.maturities') }}" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small text-muted">Within</label>
        <select name="days" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
            <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 days</option>
            <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 days</option>
            <option value="60" {{ $days == 60 ? 'selected' : '' }}>60 days</option>
            <option value="90" {{ $days == 90 ? 'selected' : '' }}>90 days</option>
        </select>
    </form>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="app-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Policy</th>
                    <th>Client / Agent</th>
                    <th>Product</th>
                    <th>Maturity Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($policies as $row)
                    @php
                        $policy = trim($row->policy_number ?? '');
                        $clientName = trim($row->life_assur ?? $row->life_assured ?? '') ?: trim(($row->pol_prepared_by ?? '') . ' ' . ($row->intermediary ?? '')) ?: '—';
                        $product = $row->product ?? '—';
                        $maturity = $row->maturity ?? null;
                        $maturityFormatted = $maturity ? \Carbon\Carbon::parse($maturity)->format('d M Y') : '—';
                    @endphp
                    <tr>
                        <td>
                            <span class="font-monospace fw-semibold">{{ $policy }}</span>
                        </td>
                        <td>{{ $clientName }}</td>
                        <td><span class="text-muted small">{{ $product }}</span></td>
                        <td>{{ $maturityFormatted }}</td>
                        <td class="text-end">
                            <a href="{{ route('support.clients.create-ticket', ['policy' => $policy]) }}" class="btn btn-sm btn-success" title="Create ticket for this policy">
                                <i class="bi bi-ticket-perforated me-1"></i> Create Ticket
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-calendar-event" style="font-size: 2.5rem"></i></div>
                                <h6 class="fw-semibold mb-1">No maturing policies</h6>
                                <p class="text-muted small mb-0">No policies maturing within the next {{ $days }} days. Try increasing the period or check ERP sync.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Data from {{ \Illuminate\Support\Facades\Schema::hasTable('erp_clients_cache') ? 'ERP cache' : 'ERP API' }}.
    Use "Create Ticket" to open a new support ticket with policy pre-filled for each maturing policy.
</p>
@endsection
