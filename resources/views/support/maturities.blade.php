@extends('layouts.app')

@section('title', 'Maturing Policies')

@push('head')
<style>
    /* Native disclosure works inside tables; Bootstrap collapse often measures height 0 here */
    .maturity-renewal-details { margin-top: 0.35rem; }
    .maturity-renewal-details > summary {
        cursor: pointer;
        list-style: none;
        font-size: 0.8125rem;
        color: var(--geminia-primary, #1A468A);
        font-weight: 500;
    }
    .maturity-renewal-details > summary::-webkit-details-marker { display: none; }
    .maturity-renewal-details .renewal-form-panel {
        margin-top: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--geminia-border, #e2e8f0);
    }
</style>
@endpush

@section('content')
<nav class="mb-3">
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Maturing Policies</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1">Maturing Policies</h1>
        <p class="app-page-sub mb-0">Policies maturing within the next {{ $days }} days. Auto-tickets can use bands 90 / 60 / 30 days (configurable) with optional assignees by product.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <a href="{{ route('support.maturities.export', request()->except(['per_page', 'page'])) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
        </a>
        <form method="GET" action="{{ route('support.maturities') }}" class="d-flex align-items-center gap-2 flex-wrap">
            @if(request('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
            @endif
            @if(request('product'))
                <input type="hidden" name="product" value="{{ request('product') }}">
            @endif
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
            @endif
            @if(request('renewal_status'))
                <input type="hidden" name="renewal_status" value="{{ request('renewal_status') }}">
            @endif
            <label class="form-label mb-0 small text-muted">Within</label>
            <select name="days" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 days</option>
                <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 days</option>
                <option value="60" {{ $days == 60 ? 'selected' : '' }}>60 days</option>
                <option value="90" {{ $days == 90 ? 'selected' : '' }}>90 days</option>
                <option value="180" {{ $days == 180 ? 'selected' : '' }}>180 days</option>
                <option value="365" {{ $days == 365 ? 'selected' : '' }}>365 days</option>
            </select>
        </form>
        @if(!empty($products))
        <form method="GET" action="{{ route('support.maturities') }}" class="d-flex align-items-center gap-2">
            @foreach(request()->except(['product', 'per_page', 'page']) as $k => $v)
                @if($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
            @endforeach
            <label class="form-label mb-0 small text-muted">Product</label>
            <select name="product" class="form-select form-select-sm" style="min-width: 220px; max-width: 320px" onchange="this.form.submit()">
                <option value="">All products</option>
                @foreach($products as $p)
                    <option value="{{ $p }}" {{ ($product ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </form>
        @endif
        @if(!empty($trackingEnabled))
        <form method="GET" action="{{ route('support.maturities') }}" class="d-flex align-items-center gap-2">
            @foreach(request()->except(['renewal_status', 'per_page', 'page']) as $k => $v)
                @if($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
            @endforeach
            <label class="form-label mb-0 small text-muted">Renewal</label>
            <select name="renewal_status" class="form-select form-select-sm" style="min-width: 140px" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach($renewalStatusLabels as $key => $label)
                    <option value="{{ $key }}" {{ ($renewalStatus ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        @endif
        <form method="GET" action="{{ route('support.maturities') }}" class="d-flex align-items-center gap-2">
            @foreach(request()->except(['search', 'per_page', 'page']) as $k => $v)
                @if($v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
            @endforeach
            <div class="input-group input-group-sm" style="max-width: 280px">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" name="search" class="form-control border-start-0" placeholder="Policy, client, product…" value="{{ request('search') }}" aria-label="Search">
                <button type="submit" class="btn btn-outline-secondary">Search</button>
            </div>
        </form>
    </div>
</div>

@php
    $maturitiesColspan = ! empty($trackingEnabled) ? 7 : 6;
@endphp

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <strong>Could not save renewal.</strong>
        <ul class="mb-0 small mt-2">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

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

<div class="app-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>
                        <a href="{{ route('support.maturities', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'policy_number', 'dir' => ($sort ?? 'maturity') === 'policy_number' && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Policy @if(($sort ?? '') === 'policy_number')<i class="bi bi-chevron-{{ ($dir ?? 'asc') === 'desc' ? 'down' : 'up' }} small"></i>@endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('support.maturities', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'life_assured', 'dir' => ($sort ?? '') === 'life_assured' && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Client / Agent @if(($sort ?? '') === 'life_assured')<i class="bi bi-chevron-{{ ($dir ?? 'asc') === 'desc' ? 'down' : 'up' }} small"></i>@endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('support.maturities', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'product', 'dir' => ($sort ?? '') === 'product' && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Product @if(($sort ?? '') === 'product')<i class="bi bi-chevron-{{ ($dir ?? 'asc') === 'desc' ? 'down' : 'up' }} small"></i>@endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('support.maturities', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'maturity', 'dir' => ($sort ?? 'maturity') === 'maturity' && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Maturity Date @if(($sort ?? 'maturity') === 'maturity')<i class="bi bi-chevron-{{ ($dir ?? 'asc') === 'desc' ? 'down' : 'up' }} small"></i>@endif
                        </a>
                    </th>
                    <th class="text-nowrap">Days to maturity</th>
                    @if(!empty($trackingEnabled))
                    <th>
                        <a href="{{ route('support.maturities', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'renewal_status', 'dir' => ($sort ?? '') === 'renewal_status' && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Renewal @if(($sort ?? '') === 'renewal_status')<i class="bi bi-chevron-{{ ($dir ?? 'asc') === 'desc' ? 'down' : 'up' }} small"></i>@endif
                        </a>
                    </th>
                    @endif
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
                        $daysToMaturity = ($maturity ?? null)
                            ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($maturity)->startOfDay(), false)
                            : null;
                        $renewalKey = $row->renewal_status ?? null;
                        if ($renewalKey === null || $renewalKey === '') {
                            $renewalKey = 'pending';
                        }
                        $renewalLabel = ($renewalStatusLabels[$renewalKey] ?? ucfirst(str_replace('_', ' ', $renewalKey)));
                        $dvMaturityYmd = $maturity ? \Carbon\Carbon::parse($maturity)->format('Y-m-d') : null;
                        $dvPrefillEmail = trim((string) ($row->email_adr ?? $row->client_email ?? $row->email ?? ''));
                        if ($dvPrefillEmail !== '' && ! filter_var($dvPrefillEmail, FILTER_VALIDATE_EMAIL)) {
                            $dvPrefillEmail = '';
                        }
                    @endphp
                    <tr>
                        <td>
                            <span class="font-monospace fw-semibold">{{ $policy }}</span>
                        </td>
                        <td>{{ $clientName }}</td>
                        <td><span class="text-muted small">{{ $product }}</span></td>
                        <td>{{ $maturityFormatted }}</td>
                        <td class="text-nowrap small">
                            @if($daysToMaturity !== null)
                                @if($daysToMaturity < 0)
                                    <span class="text-danger fw-semibold">Overdue {{ abs($daysToMaturity) }}d</span>
                                @elseif($daysToMaturity === 0)
                                    <span class="text-warning fw-semibold">Today</span>
                                @else
                                    {{ $daysToMaturity }} days
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        @if(!empty($trackingEnabled))
                        <td class="align-top small">
                            @php
                                $badgeClass = match ($renewalKey) {
                                    'renewed' => 'bg-success',
                                    'lapsed', 'not_renewing' => 'bg-secondary',
                                    'in_progress' => 'bg-primary',
                                    default => 'bg-light text-dark border',
                                };
                            @endphp
                            <div><span class="badge {{ $badgeClass }}">{{ $renewalLabel }}</span></div>
                            @if($maturity)
                                <details class="maturity-renewal-details">
                                    <summary>Update renewal</summary>
                                    <div class="renewal-form-panel bg-light rounded-2 p-2 p-md-3">
                                        <form method="post" action="{{ route('support.maturities.renewal-status') }}" class="row g-2 align-items-end">
                                            @csrf
                                            <input type="hidden" name="policy_number" value="{{ $policy }}">
                                            <input type="hidden" name="maturity" value="{{ \Carbon\Carbon::parse($maturity)->format('Y-m-d') }}">
                                            <div class="col-12 col-md-4 col-lg-2">
                                                <label class="form-label small text-muted mb-0">Status</label>
                                                <select name="renewal_status" class="form-select form-select-sm" required>
                                                    @foreach($renewalStatusLabels as $key => $lbl)
                                                        <option value="{{ $key }}" {{ $renewalKey === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-2">
                                                <label class="form-label small text-muted mb-0">Renewal date</label>
                                                <input type="date" name="renewal_date" class="form-control form-control-sm" value="{{ !empty($row->renewal_date) ? \Carbon\Carbon::parse($row->renewal_date)->format('Y-m-d') : '' }}">
                                            </div>
                                            <div class="col-12 col-md-12 col-lg-5">
                                                <label class="form-label small text-muted mb-0">Notes</label>
                                                <input type="text" name="notes" class="form-control form-control-sm" maxlength="5000" value="{{ $row->renewal_notes ?? '' }}" placeholder="Internal notes">
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-3 text-lg-end">
                                                <button type="submit" class="btn btn-primary btn-sm">Save renewal</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        </td>
                        @endif
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex flex-wrap align-items-center justify-content-end gap-1">
                                @if($dvMaturityYmd)
                                    <a href="{{ route('support.maturities.discharge-voucher.pdf', ['policy_number' => $policy, 'maturity' => $dvMaturityYmd]) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" title="Download discharge voucher (PDF)">
                                        <i class="bi bi-file-pdf"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Email discharge voucher to client"
                                        data-bs-toggle="modal" data-bs-target="#dvEmailModal"
                                        data-dv-policy="{{ $policy }}" data-dv-maturity="{{ $dvMaturityYmd }}" data-dv-email="{{ $dvPrefillEmail }}">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                @endif
                                <a href="{{ route('support.clients.create-ticket', ['policy' => $policy]) }}" class="btn btn-sm btn-success" title="Create ticket for this policy">
                                    <i class="bi bi-ticket-perforated me-1"></i> Ticket
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $maturitiesColspan }}">
                            <div class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-calendar-event" style="font-size: 2.5rem"></i></div>
                                <h6 class="fw-semibold mb-1">{{ request('search') || request('product') ? 'No matching policies' : 'No maturing policies' }}</h6>
                                <p class="text-muted small mb-0">
                                    @if(request('search'))
                                        No policies match "{{ request('search') }}". <a href="{{ route('support.maturities', request()->except(['search', 'page'])) }}">Clear search</a>
                                    @elseif(request('product'))
                                        No maturing policies for "{{ request('product') }}". <a href="{{ route('support.maturities', request()->except(['product', 'page'])) }}">Show all products</a>
                                    @else
                                        No policies maturing within the next {{ $days }} days. Try increasing the period or check ERP sync.
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($policies->hasPages() || $policies->total() > 0)
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top bg-light">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="small text-muted">Showing {{ $policies->firstItem() ?? 0 }}–{{ $policies->lastItem() ?? 0 }} of {{ $policies->total() }}</span>
                <form method="GET" action="{{ route('support.maturities') }}" class="d-inline">
                    @foreach(request()->except(['per_page', 'page']) as $k => $v)
                        @if($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                    @endforeach
                    <select name="per_page" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                        <option value="25" {{ ($perPage ?? 50) == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ ($perPage ?? 50) == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ ($perPage ?? 50) == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </form>
            </div>
            @if($policies->hasPages())
                {{ $policies->withQueryString()->links('pagination::bootstrap-5') }}
            @endif
        </div>
    @endif
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Data from {{ \Illuminate\Support\Facades\Schema::hasTable('maturities_cache') && \Illuminate\Support\Facades\DB::table('maturities_cache')->exists() ? 'maturities cache' : (\Illuminate\Support\Facades\Schema::hasTable('erp_clients_cache') ? 'ERP cache' : 'ERP API') }}.
    Auto-tickets use the same source order: maturities cache, then ERP cache, then API.
    @if(!empty($trackingEnabled))
        Renewal status is stored in the CRM database and kept when the cache is refreshed.
    @else
        Run <code class="small">php artisan migrate</code> on the app DB (same connection as <code class="small">maturities_cache</code>) to enable renewal tracking.
    @endif
    Discharge voucher PDF/email uses ERP or maturities cache for policy details; maturity must match the listed date.
</p>

{{-- Email discharge voucher (one modal for all rows) --}}
<div class="modal fade" id="dvEmailModal" tabindex="-1" aria-labelledby="dvEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('support.maturities.discharge-voucher.email') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="dvEmailModalLabel">Email discharge voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="policy_number" id="dv_modal_policy" value="">
                    <input type="hidden" name="maturity" id="dv_modal_maturity" value="">
                    <div class="mb-2 small text-muted">Policy <span class="font-monospace fw-semibold text-dark" id="dv_modal_policy_label"></span> · maturity <span id="dv_modal_maturity_label"></span></div>
                    <div class="mb-3">
                        <label for="dv_modal_to_email" class="form-label small mb-0">Recipient email</label>
                        <input type="email" class="form-control form-control-sm" name="to_email" id="dv_modal_to_email" required placeholder="client@example.com" value="{{ old('to_email') }}">
                    </div>
                    <div class="mb-3">
                        <label for="dv_modal_to_name" class="form-label small mb-0">Recipient name (optional)</label>
                        <input type="text" class="form-control form-control-sm" name="to_name" id="dv_modal_to_name" placeholder="Life assured name" value="{{ old('to_name') }}">
                    </div>
                    <div class="mb-0">
                        <label for="dv_modal_message" class="form-label small mb-0">Cover message (optional)</label>
                        <textarea class="form-control form-control-sm" name="message" id="dv_modal_message" rows="3" maxlength="5000" placeholder="Defaults to a short standard message if left blank.">{{ old('message') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> Send PDF</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('dvEmailModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn || !btn.getAttribute) return;
        var pol = btn.getAttribute('data-dv-policy') || '';
        var mat = btn.getAttribute('data-dv-maturity') || '';
        var em = btn.getAttribute('data-dv-email') || '';
        var polEl = document.getElementById('dv_modal_policy');
        var matEl = document.getElementById('dv_modal_maturity');
        var polLab = document.getElementById('dv_modal_policy_label');
        var matLab = document.getElementById('dv_modal_maturity_label');
        var emailEl = document.getElementById('dv_modal_to_email');
        if (polEl) polEl.value = pol;
        if (matEl) matEl.value = mat;
        if (polLab) polLab.textContent = pol;
        if (matLab) matLab.textContent = mat;
        if (emailEl) emailEl.value = em || @json(old('to_email', ''));
    });
})();
</script>
@endpush
