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
            <label class="form-label mb-0 small text-muted">Within</label>
            <select name="days" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 days</option>
                <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 days</option>
                <option value="60" {{ $days == 60 ? 'selected' : '' }}>60 days</option>
                <option value="90" {{ $days == 90 ? 'selected' : '' }}>90 days</option>
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
    Use "Create Ticket" to open a new support ticket with policy pre-filled for each maturing policy.
</p>
@endsection
