@extends('layouts.app')

@section('title', 'Mortgage — due for renewal')

@push('head')
<style>
    .mortgage-renewals-table-card {
        border-top: 3px solid #ea580c;
        overflow: hidden;
    }
    .mortgage-renewals-table-card .table thead th {
        background: #f8fafc;
        color: var(--geminia-text);
        font-weight: 600;
        border-bottom-width: 1px;
        white-space: nowrap;
    }
    .mortgage-renewals-table-card .table tbody td { vertical-align: middle; }
    .mortgage-renewals-toolbar .form-select { min-width: 11rem; }
</style>
@endpush

@section('content')
<nav class="mb-3">
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Mortgage — due for renewal</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 mortgage-renewals-title-icon" style="width:2.5rem;height:2.5rem;background:#ffedd5;color:#c2410c" aria-hidden="true">
                <i class="bi bi-house-heart-fill"></i>
            </span>
            Due for renewal (mortgage)
        </h1>
        <p class="app-page-sub mb-0">
            <strong>Not the full mortgage list.</strong> Only policies whose <strong>renewal date</strong> in the policy system is between <strong>today</strong> and <strong>{{ $renewalDateEnd->format('M j, Y') }}</strong>
            (inclusive, next {{ $window }} days, using the policy system’s clock). Full register: <a href="{{ route('support.customers', ['system' => 'mortgage']) }}" class="text-decoration-none">Support → Clients → Mortgage</a>.
        </p>
    </div>
    <div class="mortgage-renewals-toolbar d-flex flex-wrap align-items-center gap-2 bg-white border rounded-3 px-3 py-2 shadow-sm">
        <form method="GET" action="{{ route('support.mortgage-renewals') }}" class="d-flex flex-wrap align-items-center gap-2">
            <label class="form-label mb-0 small text-muted">Period</label>
            <select name="window" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Renewal due within" style="min-width:12rem">
                <option value="7" {{ $window === 7 ? 'selected' : '' }}>Due within 7 days</option>
                <option value="14" {{ $window === 14 ? 'selected' : '' }}>Due within 14 days</option>
                <option value="30" {{ $window === 30 ? 'selected' : '' }}>Due within 30 days</option>
                <option value="90" {{ $window === 90 ? 'selected' : '' }}>Due within 90 days</option>
                <option value="120" {{ $window === 120 ? 'selected' : '' }}>Due within 120 days</option>
            </select>
        </form>
        @if (($mortgageConfigured ?? false) && ($useHttp ?? false) && ! ($pageError ?? null))
            <a href="{{ route('support.mortgage-renewals.export', ['window' => $window]) }}" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" title="Download all rows in this period as Excel">
                <i class="bi bi-file-earmark-excel"></i> Download Excel
            </a>
        @endif
    </div>
</div>

@if (session('error'))
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-0 text-warning"></i>
    <div class="flex-grow-1">{{ session('error') }}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if ($pageError ?? null)
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-0 text-warning"></i>
    <div class="flex-grow-1">{{ $pageError }}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if (($mortgageConfigured ?? false) && ($useHttp ?? false) && ! ($pageError ?? null))
<div class="mb-4">
    <div class="app-card p-3 border-0 shadow-sm d-inline-block">
        <p class="small text-muted text-uppercase fw-semibold mb-1" style="letter-spacing:0.04em">Due for renewal in window</p>
        <p class="fs-3 fw-bold text-dark mb-0">{{ number_format((int) ($customers->total() ?? 0)) }}</p>
    </div>
</div>
@endif

<div class="app-card mortgage-renewals-table-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Policy</th>
                    <th scope="col">Life assured</th>
                    <th scope="col">Product</th>
                    <th scope="col" class="text-nowrap">Status</th>
                    <th scope="col" class="text-nowrap">Renewal date</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php
                        $rowPolicy = trim((string) ($customer->policy_no ?? $customer->policy_number ?? ''));
                        $renewRaw = $customer->mendr_renewal_date ?? $customer->maturity ?? null;
                        $renewStr = '—';
                        if ($renewRaw !== null && $renewRaw !== '') {
                            try {
                                $renewStr = \Carbon\Carbon::parse($renewRaw)->format('d M Y');
                            } catch (\Throwable $e) {
                                $renewStr = (string) $renewRaw;
                            }
                        }
                        $st = (string) ($customer->status ?? '');
                        $statusBadgeClass = match (true) {
                            $st === 'A' => 'bg-success',
                            $st === 'FL' => 'bg-danger',
                            $st !== '' => 'bg-secondary',
                            default => 'bg-light text-dark border',
                        };
                    @endphp
                    <tr>
                        <td>
                            @if($rowPolicy !== '')
                                <a href="{{ route('support.serve-client', ['search' => $rowPolicy]) }}" class="font-monospace fw-semibold text-decoration-none text-primary">{{ $rowPolicy }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="fw-medium">{{ $customer->life_assur ?? $customer->client_name ?? '—' }}</td>
                        <td><span class="text-muted small">{{ \Illuminate\Support\Str::limit($customer->product ?? '—', 48) }}</span></td>
                        <td>
                            @if($st !== '')
                                <span class="badge {{ $statusBadgeClass }}">{{ $st }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-nowrap small fw-semibold">{{ $renewStr }}</td>
                        <td class="text-end">
                            @if($rowPolicy !== '')
                                <div class="btn-group btn-group-sm flex-wrap justify-content-end" role="group" aria-label="Actions">
                                    <a href="{{ route('support.clients.show', ['policy' => $rowPolicy, 'system' => 'mortgage']) }}" class="btn btn-outline-primary" title="Policy details"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('support.clients.create-ticket', ['policy' => $rowPolicy, 'system' => 'mortgage']) }}" class="btn btn-success" title="Create renewal ticket"><i class="bi bi-ticket-perforated"></i></a>
                                    <a href="{{ route('support.serve-client', ['search' => $rowPolicy]) }}" class="btn btn-outline-secondary" title="Serve client"><i class="bi bi-person-plus"></i></a>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            @if ($pageError ?? null)
                                <p class="text-muted mb-0">Resolve the message above and refresh.</p>
                            @else
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:4rem;height:4rem;background:var(--geminia-primary-muted);color:var(--geminia-primary)">
                                    <i class="bi bi-house-heart fs-2"></i>
                                </div>
                                <p class="text-muted mb-0 fw-medium">No mortgages with a renewal in this period.</p>
                                <p class="small text-muted mb-0 mt-1">Try a longer period above, or check back when renewals are due.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(($customers->total() ?? 0) > 0)
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 px-1">
    <p class="small text-muted mb-0">
        Showing {{ number_format($customers->firstItem() ?? 0) }}–{{ number_format($customers->lastItem() ?? 0) }} of {{ number_format($customers->total()) }}
    </p>
    <div>
        {{ $customers->withQueryString()->links() }}
    </div>
</div>
@endif
@endsection
