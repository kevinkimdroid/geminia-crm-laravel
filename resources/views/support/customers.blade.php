@extends('layouts.app')

@section('title', ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Contacts' : 'Clients')

@section('content')
<nav class="breadcrumb-nav mb-3">
    @if(($listRoute ?? 'support.customers') === 'contacts.index')
    <a href="{{ route('dashboard') }}" class="text-muted small text-decoration-none">Home</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Contacts</span>
    @else
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Clients</span>
    @endif
</nav>
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">{{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Contacts' : 'Clients' }}</h1>
        <p class="page-subtitle">{{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Manage your customer and prospect contacts.' : 'Manage your clients and policy assignments.' }}</p>
    </div>
    <a href="{{ route('contacts.create') }}" class="btn btn-primary-custom">
        <i class="bi bi-plus-lg me-2"></i>Add {{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Contact' : 'Client' }}
    </a>
</div>

@if ($clientsError ?? null)
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>
        <strong>Oracle connection issue</strong><br>
        <span class="small">{{ $clientsError }}</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- Search & Stats --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <form method="GET" action="{{ route($listRoute ?? 'support.customers') }}" class="clients-search-form" id="customersSearchForm">
            <div class="input-group clients-search-input-group">
                <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" id="customersSearchInput" class="form-control" placeholder="{{ in_array($clientsSource ?? 'crm', ['erp_sync', 'erp_http']) ? 'Search by policy number, ID number, phone, life assured, intermediary...' : 'Search by name, email, or mobile...' }}" value="{{ $search ?? '' }}" autocomplete="off">
                <button type="submit" class="btn btn-primary-custom">Search</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="clients-stat-card">
            <span class="clients-stat-value" id="clientsTotalValue">{{ number_format($total ?? 0) }}</span>
            <span class="clients-stat-label">Total {{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Contacts' : 'Clients' }}</span>
        </div>
    </div>
</div>

{{-- Clients Table --}}
<div class="clients-table-card">
    <div class="clients-table-wrapper">
        <table class="clients-table">
            <thead>
                <tr>
                    @if(in_array($clientsSource ?? 'crm', ['erp_sync', 'erp_http']))
                    <th>Policy Number</th>
                    <th>Who Prepared Policy</th>
                    <th>Intermediary (Agent)</th>
                    <th>Life Assured (Client)</th>
                    <th>Product</th>
                    <th>Policy Status</th>
                    <th class="text-end">Actions</th>
                    @else
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    @if(in_array($clientsSource ?? 'crm', ['erp']))
                    <th>Product</th>
                    @endif
                    <th>Assigned To</th>
                    <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody id="clientsTableBody">
                @if($clientsLazyLoad ?? false)
                <tr id="clientsLoadingRow">
                    <td colspan="7" class="text-center py-5">
                        <div class="clients-empty">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <p class="text-muted mb-0">Loading clients...</p>
                        </div>
                    </td>
                </tr>
                @else
                @forelse ($customers as $customer)
                    @php
                        $rowPolicy = $customer->policy_no ?? $customer->policy_number ?? (is_array($customer) ? ($customer['policy_no'] ?? $customer['policy_number'] ?? '') : '');
                    @endphp
                    <tr>
                        @if(($customer->_erp_source ?? false) && in_array($clientsSource ?? 'crm', ['erp_sync', 'erp_http']))
                        <td>
                            <a href="{{ route('support.serve-client', ['search' => $rowPolicy]) }}" class="clients-policy-link">
                                {{ $rowPolicy ?: '—' }}
                            </a>
                        </td>
                        <td>{{ $customer->pol_prepared_by ?? '—' }}</td>
                        <td>{{ Str::limit($customer->intermediary ?? '—', 25) }}</td>
                        <td>
                            <span class="clients-name">{{ $customer->life_assur ?? $customer->client_name ?? '—' }}</span>
                        </td>
                        <td class="clients-product">{{ Str::limit($customer->product ?? '—', 40) }}</td>
                        <td>
                            @php $st = $customer->status ?? ''; @endphp
                            <span class="clients-status-badge clients-status-{{ $st === 'A' ? 'active' : ($st === 'FL' ? 'lapsed' : 'other') }}">
                                {{ $st ?: '—' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="clients-actions">
                                <a href="{{ route('support.clients.create-ticket', ['policy' => $rowPolicy]) }}" class="btn btn-sm btn-success" title="Create ticket">
                                    <i class="bi bi-ticket-perforated"></i> Ticket
                                </a>
                                <form method="GET" action="{{ route('support.clients.show') }}" class="d-inline" style="display:inline!important">
                                    <input type="hidden" name="policy" value="{{ $rowPolicy }}">
                                    <button type="submit" class="btn btn-sm clients-btn-view" title="View full details">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </form>
                                <a href="{{ route('support.serve-client', ['search' => $rowPolicy]) }}" class="btn btn-sm clients-btn-serve" title="Serve client">
                                    <i class="bi bi-person-plus"></i> Serve
                                </a>
                            </div>
                        </td>
                        @else
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="clients-avatar">{{ strtoupper(substr($customer->firstname ?? '?', 0, 1)) }}{{ strtoupper(substr($customer->lastname ?? '', 0, 1)) }}</div>
                                @if(($customer->_erp_source ?? false))
                                <span class="clients-name">{{ trim(($customer->firstname ?? '') . ' ' . ($customer->lastname ?? '')) ?: ($rowPolicy ?: '—') }}</span>
                                @else
                                <a href="{{ route('contacts.show', $customer->contactid) }}" class="clients-name-link">{{ trim(($customer->firstname ?? '') . ' ' . ($customer->lastname ?? '')) ?: '—' }}</a>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($customer->email)
                            <a href="mailto:{{ $customer->email }}" class="text-decoration-none">{{ Str::limit($customer->email, 35) }}</a>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->mobile ?? $customer->phone)
                            <a href="tel:{{ tel_href($customer->mobile ?? $customer->phone) }}" class="text-decoration-none">{{ $customer->mobile ?? $customer->phone }}</a>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        @if(($clientsSource ?? 'crm') === 'erp')
                        <td><span class="badge bg-secondary">{{ Str::limit($customer->product ?? '—', 35) }}</span></td>
                        @endif
                        <td><span class="text-muted small">{{ trim(($customer->owner_first ?? '') . ' ' . ($customer->owner_last ?? '')) ?: ($customer->owner_username ?? '—') ?: '—' }}</span></td>
                        <td class="text-end">
                            @if(($customer->_erp_source ?? false))
                            <a href="{{ route('support.serve-client', ['search' => $rowPolicy]) }}" class="btn btn-sm clients-btn-serve"><i class="bi bi-person-plus"></i></a>
                            @else
                            <a href="{{ route('contacts.show', $customer->contactid) }}" class="btn btn-sm clients-btn-view"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ in_array($clientsSource ?? 'crm', ['erp_sync', 'erp_http']) ? 7 : (in_array($clientsSource ?? 'crm', ['erp']) ? 6 : 5) }}" class="text-center py-5">
                            <div class="clients-empty">
                                <div class="clients-empty-icon"><i class="bi bi-people"></i></div>
                                <h6 class="mt-3 mb-2">No {{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'contacts' : 'clients' }} found</h6>
                                <p class="text-muted mb-3">
                                    @if($search ?? '')
                                    Try a different search or <a href="{{ route($listRoute ?? 'support.customers') }}">view all</a>.
                                    @else
                                    Get started by adding your first {{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'contact' : 'client' }}.
                                    @endif
                                </p>
                                @if(!($search ?? ''))
                                <a href="{{ route('contacts.create') }}" class="btn btn-primary-custom"><i class="bi bi-plus-lg me-1"></i>Add {{ ($listRoute ?? 'support.customers') === 'contacts.index' ? 'Contact' : 'Client' }}</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                @endif
            </tbody>
        </table>
    </div>
    @if ($clientsLazyLoad ?? false)
    <div class="clients-table-footer" id="clientsTableFooter" style="display:none">
        <span class="clients-pagination-info" id="clientsPaginationInfo">—</span>
        <nav id="clientsPaginationNav" aria-label="Clients pagination"></nav>
    </div>
    @elseif ($customers->hasPages())
    <div class="clients-table-footer">
        <span class="clients-pagination-info">Showing {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} of {{ number_format($customers->total()) }}</span>
        {{ $customers->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

<style>
/* Clients page - modern, fast, presentable */
.clients-search-input-group { border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(26, 85, 158, 0.08); border: 1px solid var(--geminia-border); }
.clients-search-input-group .input-group-text { background: #fff; border: none; padding: 0.75rem 1rem; }
.clients-search-input-group .form-control { border: none; padding: 0.75rem 1rem; }
.clients-search-input-group .form-control:focus { box-shadow: none; }
.clients-search-input-group .btn { padding: 0.75rem 1.25rem; border-radius: 0 10px 10px 0; }

.clients-stat-card {
    background: linear-gradient(135deg, var(--geminia-primary) 0%, var(--geminia-primary-dark) 100%);
    color: #fff; padding: 1rem 1.5rem; border-radius: 14px; text-align: center; box-shadow: 0 4px 14px rgba(26, 85, 158, 0.25);
}
.clients-stat-value { display: block; font-size: 1.75rem; font-weight: 700; }
.clients-stat-label { font-size: 0.75rem; opacity: 0.9; }

.clients-table-card {
    position: relative;
    background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); overflow: hidden; border: 1px solid var(--geminia-border);
}
.clients-table-wrapper { overflow-x: auto; }
.clients-table {
    width: 100%; border-collapse: collapse; font-size: 0.9rem;
}
.clients-table thead { background: linear-gradient(180deg, #1e3a5f 0%, #1A559E 100%); color: #fff; }
.clients-table th {
    padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;
}
.clients-table tbody tr {
    border-bottom: 1px solid var(--geminia-border); transition: background 0.15s;
}
.clients-table tbody tr:hover { background: var(--geminia-primary-muted); }
.clients-table tbody tr:last-child { border-bottom: none; }
.clients-table td { padding: 1rem 1.25rem; vertical-align: middle; }

.clients-policy-link { font-weight: 600; color: var(--geminia-primary); text-decoration: none; }
.clients-policy-link:hover { color: var(--geminia-primary-dark); text-decoration: underline; }
.clients-name { font-weight: 500; color: var(--geminia-text); }
.clients-product { color: var(--geminia-text-muted); font-size: 0.85rem; }
.clients-kra { font-family: ui-monospace, monospace; font-size: 0.85rem; }

.clients-status-badge {
    display: inline-block; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;
}
.clients-status-active { background: #dcfce7; color: #166534; }
.clients-status-lapsed { background: #fee2e2; color: #991b1b; }
.clients-status-other { background: #f1f5f9; color: #475569; }

.clients-actions { display: flex; gap: 0.35rem; justify-content: flex-end; flex-wrap: wrap; }
.clients-btn-view { background: var(--geminia-primary-muted); color: var(--geminia-primary) !important; border: none; padding: 0.35rem 0.65rem; border-radius: 8px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; }
.clients-btn-view:hover { background: var(--geminia-primary-light); color: var(--geminia-primary-dark) !important; }
.clients-btn-serve { background: var(--geminia-primary); color: #fff !important; border: none; padding: 0.35rem 0.65rem; border-radius: 8px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; }
.clients-btn-serve:hover { background: var(--geminia-primary-dark); color: #fff !important; }

.clients-avatar { width: 36px; height: 36px; border-radius: 10px; background: var(--geminia-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
.clients-name-link { color: var(--geminia-text); text-decoration: none; font-weight: 500; }
.clients-name-link:hover { color: var(--geminia-primary); }

.clients-table-footer {
    padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid var(--geminia-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;
}
.clients-pagination-info { font-size: 0.85rem; color: var(--geminia-text-muted); }

.clients-empty { padding: 2rem; }
.clients-empty-icon { width: 72px; height: 72px; margin: 0 auto; background: var(--geminia-primary-muted); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: var(--geminia-primary); }

/* Modal */
.clients-modal-content { border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.clients-modal-header { background: linear-gradient(135deg, var(--geminia-primary) 0%, var(--geminia-primary-dark) 100%); color: #fff; padding: 1.25rem 1.5rem; border: none; }
.clients-modal-body { padding: 1.5rem; }
.clients-detail-row { display: flex; flex-direction: column; gap: 0.25rem; }
.clients-detail-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--geminia-text-muted); font-weight: 600; }
.clients-detail-value { font-size: 0.95rem; color: var(--geminia-text); font-weight: 500; }
.clients-search-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center; z-index: 10; border-radius: inherit; }
.clients-search-overlay-inner { text-align: center; color: var(--geminia-text-muted); font-size: 0.9rem; }
.clients-ajax-page { padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none; color: var(--geminia-primary); font-weight: 500; }
.clients-ajax-page:hover { background: var(--geminia-primary-muted); color: var(--geminia-primary-dark); }
</style>

<script>
(function() {
    const form = document.getElementById('customersSearchForm');
    const input = document.getElementById('customersSearchInput');
    const tableCard = document.querySelector('.clients-table-card');
    const submitBtn = form?.querySelector('button[type="submit"]');
    if (form && input) {
        let debounceTimer;
        form.addEventListener('submit', function() {
            if (tableCard) {
                const existing = document.getElementById('clientsSearchOverlay');
                if (existing) existing.remove();
                const overlay = document.createElement('div');
                overlay.className = 'clients-search-overlay';
                overlay.innerHTML = '<div class="clients-search-overlay-inner"><div class="spinner-border text-primary mb-2" role="status"></div><div>Searching clients...</div></div>';
                overlay.id = 'clientsSearchOverlay';
                tableCard.appendChild(overlay);
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Searching...';
                }
            }
        });
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var val = (input.value || '').trim();
            if (val.length >= 2) {
                debounceTimer = setTimeout(function() { form.submit(); }, 800);
            }
        });
    }
})();
</script>
@if($clientsLazyLoad ?? false)
<script>
(function() {
    var apiUrl = '{{ route("api.support.clients") }}';
    var serveUrl = '{{ route("support.serve-client") }}';
    var showUrl = '{{ route("support.clients.show") }}';
    var ticketUrl = '{{ route("support.clients.create-ticket") }}';
    var search = @json($search ?? '');
    var initialPage = {{ (int) ($page ?? 1) }};
    var listRoute = '{{ $listRoute ?? "support.customers" }}';

    function esc(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function loadClients(page) {
        page = page || 1;
        var url = apiUrl + '?page=' + page + (search ? '&search=' + encodeURIComponent(search) : '');
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var tbody = document.getElementById('clientsTableBody');
                var loadingRow = document.getElementById('clientsLoadingRow');
                var footer = document.getElementById('clientsTableFooter');
                var totalEl = document.getElementById('clientsTotalValue');
                var paginationInfo = document.getElementById('clientsPaginationInfo');
                var paginationNav = document.getElementById('clientsPaginationNav');

                if (loadingRow) loadingRow.remove();
                if (totalEl) totalEl.textContent = (d.total || 0).toLocaleString();

                var rows = (d.customers || []).map(function(c) {
                    var policy = c.policy || '';
                    var statusClass = (c.status === 'A') ? 'active' : ((c.status === 'FL') ? 'lapsed' : 'other');
                    return '<tr><td><a href="' + serveUrl + '?search=' + encodeURIComponent(policy) + '" class="clients-policy-link">' + esc(policy || '—') + '</a></td>' +
                        '<td>' + esc(c.pol_prepared_by || '—') + '</td>' +
                        '<td>' + esc((c.intermediary || '—').substring(0, 25)) + '</td>' +
                        '<td><span class="clients-name">' + esc(c.life_assur || '—') + '</span></td>' +
                        '<td class="clients-product">' + esc((c.product || '—').substring(0, 40)) + '</td>' +
                        '<td><span class="clients-status-badge clients-status-' + statusClass + '">' + esc(c.status || '—') + '</span></td>' +
                        '<td class="text-end"><div class="clients-actions">' +
                        '<a href="' + ticketUrl + '?policy=' + encodeURIComponent(policy) + '" class="btn btn-sm btn-success" title="Create ticket"><i class="bi bi-ticket-perforated"></i> Ticket</a> ' +
                        '<form method="GET" action="' + showUrl + '" class="d-inline" style="display:inline!important">' +
                        '<input type="hidden" name="policy" value="' + esc(policy) + '">' +
                        '<button type="submit" class="btn btn-sm clients-btn-view" title="View full details"><i class="bi bi-eye"></i> View</button></form> ' +
                        '<a href="' + serveUrl + '?search=' + encodeURIComponent(policy) + '" class="btn btn-sm clients-btn-serve" title="Serve client"><i class="bi bi-person-plus"></i> Serve</a>' +
                        '</div></td></tr>';
                }).join('');

                if (rows) {
                    tbody.insertAdjacentHTML('beforeend', rows);
                } else if (d.error) {
                    tbody.insertAdjacentHTML('beforeend', '<tr><td colspan="7" class="text-center py-4 text-warning">' + esc(d.error) + '</td></tr>');
                } else {
                    tbody.insertAdjacentHTML('beforeend', '<tr><td colspan="7" class="text-center py-5"><div class="clients-empty"><div class="clients-empty-icon"><i class="bi bi-people"></i></div><h6 class="mt-3 mb-2">No clients found</h6></div></td></tr>');
                }

                var perPage = d.per_page || 25;
                var total = d.total || 0;
                var first = total ? ((d.page - 1) * perPage + 1) : 0;
                var last = Math.min(d.page * perPage, total);
                if (paginationInfo) paginationInfo.textContent = 'Showing ' + first + '–' + last + ' of ' + (total).toLocaleString();
                if (footer) footer.style.display = 'flex';

                var lastPage = Math.ceil(total / perPage) || 1;
                var pg = d.page || 1;
                var pagHtml = '';
                if (lastPage > 1) {
                    var base = '{{ route("support.customers") }}' + (search ? '?search=' + encodeURIComponent(search) + '&' : '?');
                    if (pg > 1) pagHtml += '<a href="' + base + 'page=' + (pg-1) + '" class="page-link clients-ajax-page" data-page="' + (pg-1) + '">Previous</a> ';
                    pagHtml += '<span class="mx-2">Page ' + pg + ' of ' + lastPage + '</span> ';
                    if (pg < lastPage) pagHtml += '<a href="' + base + 'page=' + (pg+1) + '" class="page-link clients-ajax-page" data-page="' + (pg+1) + '">Next</a>';
                }
                if (paginationNav) paginationNav.innerHTML = pagHtml;

                paginationNav.querySelectorAll('.clients-ajax-page').forEach(function(a) {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        tbody.innerHTML = '<tr id="clientsLoadingRow"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div><p class="text-muted mt-2">Loading...</p></td></tr>';
                        loadClients(parseInt(a.dataset.page, 10));
                    });
                });
            })
            .catch(function() {
                var loadingRow = document.getElementById('clientsLoadingRow');
                var tbody = document.getElementById('clientsTableBody');
                if (loadingRow) loadingRow.remove();
                if (tbody) tbody.insertAdjacentHTML('beforeend', '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load clients. <a href="">Refresh</a></td></tr>');
            });
    }
    loadClients(initialPage);
})();
</script>
@endif
@endsection
