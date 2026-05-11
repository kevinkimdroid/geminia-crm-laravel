@extends('layouts.app')

@section('title', 'Finance Cheques')

@section('content')
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i><strong>Something went wrong.</strong> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (!empty($blockingError))
    <div class="alert alert-danger border mt-3" role="alert">
        <i class="bi bi-plug-fill me-2"></i><strong>Finance cannot connect to ERP.</strong> {{ $blockingError }}
    </div>
@endif
@if (!empty($erpError))
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-database-exclamation me-2"></i><strong>Data could not be loaded.</strong> {{ $erpError }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title mb-1">Finance Cheques</h1>
        <p class="page-subtitle mb-0">Live cheque feed from FMS source for ticket creation workflow.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('finance.agency-advances.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-building me-1"></i> Agency advances
        </a>
    </div>
</div>

<div class="alert alert-warning border mt-3" role="alert">
    <i class="bi bi-shield-lock me-1"></i>
    <strong>Restricted module:</strong> This finance area is only accessible by Finance department users and Administrators.
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mt-1 mb-3">
    <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Total Cheques</div><div class="h5 mb-0">{{ $stats['total_count'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Total Amount</div><div class="h6 mb-0">KES {{ number_format((float)($stats['total_amount'] ?? 0), 2) }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Today</div><div class="h5 mb-0">{{ $stats['today_count'] ?? 0 }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Distinct Payees</div><div class="h5 mb-0">{{ $stats['distinct_payees'] ?? 0 }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body border-bottom bg-light">
                <form method="GET" action="{{ route('finance.payments.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">System Source</label>
                        <select name="source" class="form-select form-select-sm">
                            <option value="">All sources</option>
                            @foreach($sourceOptions as $option)
                            <option value="{{ $option->source_code }}" {{ (string)($source ?? '') === (string)$option->source_code ? 'selected' : '' }}>
                                {{ $option->source_name }} ({{ $option->source_code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Date From</label>
                        <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Date To</label>
                        <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Payment no, customer, policy, or phone">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('finance.payments.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>System</th>
                            <th>Ref</th>
                            <th>Date</th>
                            <th>Payee</th>
                            <th>Amount</th>
                            <th>Narrative</th>
                            <th>FMS Remarks</th>
                            <th>Ticket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->sys_source }}</td>
                            <td class="fw-semibold">{{ $payment->cqr_ref }}</td>
                            <td>{{ !empty($payment->cqr_ref_date) ? \Carbon\Carbon::parse($payment->cqr_ref_date)->format('d M Y') : '—' }}</td>
                            <td>{{ $payment->cqr_payee ?: '—' }}</td>
                            <td>KES {{ number_format((float)($payment->cqr_amount ?? 0), 2) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit((string)($payment->cqr_narrative ?? '—'), 80) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit((string)($payment->cqr_fms_remarks ?? '—'), 80) }}</td>
                            <td>
                                <a
                                    href="{{ route('finance.payments.create-ticket', ['ref' => $payment->cqr_ref, 'source' => $payment->cqr_source]) }}"
                                    class="btn btn-sm btn-primary"
                                >
                                    <i class="bi bi-ticket-perforated me-1"></i>Create Ticket
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-cash-coin fs-3 d-block mb-2"></i>
                                @if (!empty($blockingError))
                                    Fix the ERP connection issue above to load cheques.
                                @elseif (!empty($erpError))
                                    No rows loaded because the query failed.
                                @else
                                    No finance cheque rows found.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($payments, 'links'))
            <div class="card-body border-top">
                {{ $payments->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
