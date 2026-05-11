@extends('layouts.app')

@section('title', 'Agency advances')

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
@if (!empty($loadError))
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-database-exclamation me-2"></i><strong>Data could not be loaded.</strong> {{ $loadError }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title mb-1">Agency advances</h1>
        <p class="page-subtitle mb-0">AGNADV cheques with no bank branch on the cheque (<code>cqr_bbr_code</code> null), matched to LMS agency by payee. Same list as the scheduled email to Finance.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('finance.payments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cash-coin me-1"></i> Finance cheques
        </a>
    </div>
</div>

<div class="alert alert-warning border mt-3" role="alert">
    <i class="bi bi-shield-lock me-1"></i>
    <strong>Restricted module:</strong> Finance department users and Administrators only.
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body border-bottom bg-light">
        <form method="GET" action="{{ route('finance.agency-advances.index') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Reference year</label>
                <input type="number" name="year" class="form-control form-control-sm" style="width:7rem" min="2000" max="2100" value="{{ $year }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search me-1"></i> Apply</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>CQR no</th>
                    <th>Ref date</th>
                    <th>Payee</th>
                    <th>CQR BBR</th>
                    <th>Cpy acc</th>
                    <th>Agency bank acc</th>
                    <th>Agency BBR</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['cqr_no'] !== '' ? $r['cqr_no'] : '—' }}</td>
                    <td>{{ !empty($r['cqr_ref_date']) ? \Carbon\Carbon::parse($r['cqr_ref_date'])->format('d M Y') : '—' }}</td>
                    <td>{{ $r['cqr_payee'] !== '' ? $r['cqr_payee'] : '—' }}</td>
                    <td>{{ $r['cqr_bbr_code'] !== '' ? $r['cqr_bbr_code'] : '—' }}</td>
                    <td class="small">{{ $r['cqr_cpy_acc_no'] !== '' ? $r['cqr_cpy_acc_no'] : '—' }}</td>
                    <td class="small">{{ $r['agn_bank_acc_no'] !== '' ? $r['agn_bank_acc_no'] : '—' }}</td>
                    <td>{{ $r['agn_bbr_code'] !== '' ? $r['agn_bbr_code'] : '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-building fs-3 d-block mb-2"></i>
                        @if (!empty($blockingError))
                            Fix the ERP connection issue above to load this list.
                        @elseif (!empty($loadError))
                            No rows loaded because the query failed.
                        @else
                            No agency advance rows for year {{ $year }}.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
