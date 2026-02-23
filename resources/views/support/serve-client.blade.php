@extends('layouts.app')

@section('title', 'Serve Client')

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Serve Client</span>
</nav>
<div class="serve-client-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="app-page-title mb-1">Serve Client</h1>
            <p class="app-page-sub mb-0">Search clients by policy, name, or phone — view amounts paid, details, and take action (create ticket, email, call)</p>
        </div>
        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-ticket-perforated me-1"></i> View Tickets
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card p-4">
        <form method="GET" action="{{ route('support.serve-client') }}" id="serveClientSearchForm" class="serve-client-search mb-4">
            <div class="d-flex gap-2">
                <div class="position-relative flex-grow-1">
                    <i class="bi bi-search serve-client-search-icon"></i>
                    <input type="text"
                           name="search"
                           id="clientSearch"
                           class="form-control form-control-lg serve-client-input"
                           placeholder="Type policy number, client name, or phone..."
                           value="{{ old('search', $initialSearch ?? request('search', '')) }}"
                           autocomplete="off">
                </div>
                <button type="submit" id="searchBtn" class="btn btn-lg app-btn-primary align-self-stretch px-4" title="Search">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
            <p class="text-muted small mt-2 mb-0">Search in both ERP (Oracle) and CRM. Minimum 2 characters. Press Enter or click Search.</p>
        </form>

        <div id="searchResults" class="serve-client-results" style="{{ ($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2 ? '' : 'display:none' }}">
            <div class="row">
                <div class="col-lg-6">
                    <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.06em">
                        <i class="bi bi-database me-1"></i> ERP — Clients & Policies
                    </h6>
                    <div id="erpResults" class="serve-client-list">
                        @if(($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2)
                            @include('support.serve-client-erp-results', ['items' => $initialErp ?? []])
                        @endif
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.06em">
                        <i class="bi bi-person me-1"></i> CRM — Contacts
                    </h6>
                    <div id="crmResults" class="serve-client-list">
                        @if(($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2)
                            @include('support.serve-client-crm-results', ['items' => $initialCrm ?? []])
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($initialError ?? null)
        <div id="searchError" class="alert alert-danger mt-3" role="alert">{{ $initialError }} (Ensure ERP API is running if using erp_http)</div>
        @else
        <div id="searchError" class="alert alert-danger mt-3" style="display:none" role="alert"></div>
        @endif

        <div id="searchEmpty" class="text-center py-5 text-muted" style="{{ ($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2 ? 'display:none' : '' }}">
            <i class="bi bi-search fs-1 d-block mb-2"></i>
            <p class="mb-0">Start typing to search for clients</p>
        </div>
    </div>
</div>

<form id="createTicketForm" method="POST" action="{{ route('serve-client.create-ticket') }}" style="display:none">
    @csrf
    <input type="hidden" name="source" id="formSource">
    <input type="hidden" name="contact_id" id="formContactId">
    <input type="hidden" name="erp_data" id="formErpData">
</form>

<style>
.serve-client-input {
    padding-left: 2.75rem;
    border-radius: 12px;
    border: 1px solid var(--geminia-border);
}
.serve-client-input:focus {
    border-color: var(--geminia-primary);
    box-shadow: 0 0 0 3px var(--geminia-primary-muted);
}
.serve-client-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.2rem;
    color: var(--geminia-text-muted);
}
.serve-client-list { max-height: 500px; overflow-y: auto; }
.serve-client-item {
    display: block;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--geminia-border);
    margin-bottom: 0.75rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    background: #fff;
    position: relative;
    z-index: 2;
}
.serve-client-item:hover {
    border-color: var(--geminia-primary);
    box-shadow: 0 4px 12px rgba(26, 85, 158, 0.08);
}
.serve-client-item-name { font-weight: 600; font-size: 1.05rem; color: var(--geminia-text); margin-bottom: 0.35rem; }
.serve-client-item-meta { font-size: 0.8rem; color: var(--geminia-text-muted); margin-bottom: 0.5rem; }
.serve-client-item-details {
    font-size: 0.8rem;
    color: var(--geminia-text-muted);
    padding: 0.75rem;
    background: var(--geminia-bg);
    border-radius: 8px;
    margin: 0.5rem 0 0.75rem 0;
}
.serve-client-detail-row { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 0.25rem; }
.serve-client-detail-row:last-child { margin-bottom: 0; }
.serve-client-detail-label { color: var(--geminia-text-muted); }
.serve-client-detail-value { font-weight: 600; color: var(--geminia-text); }
.serve-client-detail-value.amount { color: #059669; }
.serve-client-detail-value.status-active { color: #059669; }
.serve-client-detail-value.status-lapsed { color: #dc2626; }
.serve-client-actions {
    display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem;
    padding-top: 1rem; border-top: 1px solid var(--geminia-border);
    position: relative; z-index: 1;
}
.serve-client-cta {
    padding: 0.5rem 1rem;
    background: var(--geminia-primary);
    color: #fff !important;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.05);
    min-height: 38px;
}
.serve-client-cta:hover { background: var(--geminia-primary-dark); color: #fff !important; }
.serve-client-cta-outline {
    background: #e8f4fc !important;
    color: #1A559E !important;
    border: 2px solid #1A559E;
}
.serve-client-cta-outline:hover { background: #cce5f7 !important; color: #144177 !important; }
.serve-client-cta-success {
    background: #059669;
    border: 2px solid #047857;
}
.serve-client-cta-success:hover { background: #047857; color: #fff !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('clientSearch');
    const searchResults = document.getElementById('searchResults');
    const searchEmpty = document.getElementById('searchEmpty');
    const searchError = document.getElementById('searchError');
    const erpResults = document.getElementById('erpResults');
    const crmResults = document.getElementById('crmResults');
    const form = document.getElementById('createTicketForm');
    const formSource = document.getElementById('formSource');
    const formContactId = document.getElementById('formContactId');
    const formErpData = document.getElementById('formErpData');

    if (!searchInput || !erpResults) return;

    var searchBtn = document.getElementById('searchBtn');
    function doSearch() {
        var q = searchInput.value.trim();
        if (q.length < 2) {
            showError('Type at least 2 characters to search.');
            return;
        }
        fetchResults(q);
    }

    function esc(s) {
        if (s == null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function telHref(phone) {
        if (!phone) return '';
        var d = String(phone).replace(/\D/g, '');
        if (!d) return '';
        if (d.indexOf('254') === 0 && d.length === 12) return d.slice(3);
        if (d.indexOf('0') === 0 && d.length === 10) return d.slice(1);
        if (d.indexOf('00254') === 0 && d.length >= 14) return d.slice(5);
        return d;
    }

    function formatAmount(val) {
        if (val == null || val === '' || val === undefined) return '—';
        const n = parseFloat(String(val).replace(/[^0-9.-]/g, ''));
        if (isNaN(n)) return String(val);
        return 'KES ' + n.toLocaleString('en-KE', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function showError(msg) {
        searchError.textContent = msg;
        searchError.style.display = msg ? 'block' : 'none';
    }

    function fetchResults(q) {
        showError('');
        searchEmpty.style.display = 'none';
        searchResults.style.display = 'block';
        erpResults.innerHTML = '<div class="text-muted small py-3"><i class="bi bi-hourglass-split me-1"></i> Searching...</div>';
        crmResults.innerHTML = '<div class="text-muted small py-3"><i class="bi bi-hourglass-split me-1"></i> Searching...</div>';

        const base = '{{ route("serve-client.search") }}';
        const encoded = encodeURIComponent(q);
        // Fetch ERP and CRM in parallel for faster results
        var opts = { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' };
        Promise.all([
            fetch(base + '?q=' + encoded + '&source=erp', opts).then(function(r) { return r.json(); }),
            fetch(base + '?q=' + encoded + '&source=crm', opts).then(function(r) { return r.json(); })
        ])
        .then(function([erpData, crmData]) {
            return { erp: erpData.erp || [], crm: crmData.crm || [], error: erpData.error || crmData.error };
        })
        .then(function(data) {
            try {
                renderErp(data.erp || []);
                renderCrm(data.crm || []);
                if (data.error) {
                    showError(data.error + ' (Ensure ERP API is running if using erp_http)');
                } else if ((!data.erp || data.erp.length === 0) && (!data.crm || data.crm.length === 0)) {
                    showError('');
                }
            } catch (e) {
                showError('Error displaying results. Please try again.');
                console.error(e);
            }
        })
        .catch(function(err) {
            showError('Search failed. Check your connection. If using ERP API, ensure it is running (python app.py in erp-clients-api).');
            erpResults.innerHTML = '<div class="text-warning small py-3"><i class="bi bi-exclamation-triangle me-1"></i> Search could not complete. Check browser console (F12) for details.</div>';
            crmResults.innerHTML = '<div class="text-muted small">Search could not complete.</div>';
            console.error('Serve Client search error:', err);
        });
    }

    var erpDataStore = {};
    @if(($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2 && !empty($initialErp ?? []))
    (function() {
        var initial = @json($initialErp ?? []);
        initial.forEach(function(item, idx) { erpDataStore['erp_' + idx] = item; });
    })();
    @endif
    function renderErp(items) {
        erpDataStore = {};
        if (!items || items.length === 0) {
            erpResults.innerHTML = '<div class="text-muted small py-3">No ERP clients found. Try a different search term.</div>';
            return;
        }
        const detailUrl = '{{ url("/support/clients/show") }}';
        erpResults.innerHTML = items.map(function(item, idx) {
            var storeId = 'erp_' + idx;
            erpDataStore[storeId] = item;
            const name = item.name || item.client_name || item.life_assur || item.life_assured || ((item.first_name || '') + ' ' + (item.last_name || '')).trim() || 'Client';
            const policy = item.policy_no || item.policy_number || item.POLICY_NO || item.POLICY_NUMBER || '';
            const phone = item.mobile || item.phone || item.MOBILE || item.PHONE || '';
            const email = item.email || item.email_adr || item.EMAIL || '';
            const product = item.product || '';
            const status = item.status || '';
            const paidAmt = item.bal ?? item.paid_mat_amt;
            const maturity = item.maturity || item.maturity_date || '';
            const effectiveDate = item.effective_date || '';
            const intermediary = item.intermediary || '';
            const checkoff = item.checkoff || '';
            const statusClass = status === 'A' ? 'status-active' : (status === 'FL' ? 'status-lapsed' : '');
            var detailsHtml = '';
            if (product || status || (paidAmt != null && paidAmt !== '') || maturity || effectiveDate || intermediary) {
                detailsHtml = '<div class="serve-client-item-details">' +
                    (product ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Product</span><span class="serve-client-detail-value">' + esc(product) + '</span></div>' : '') +
                    (status ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Status</span><span class="serve-client-detail-value ' + statusClass + '">' + esc(status) + '</span></div>' : '') +
                    (paidAmt != null && paidAmt !== '' ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Total Paid Amount</span><span class="serve-client-detail-value amount">' + esc(formatAmount(paidAmt)) + '</span></div>' : '') +
                    (maturity ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Maturity</span><span class="serve-client-detail-value">' + esc(maturity) + '</span></div>' : '') +
                    (effectiveDate ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Effective</span><span class="serve-client-detail-value">' + esc(effectiveDate) + '</span></div>' : '') +
                    (intermediary ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Agent</span><span class="serve-client-detail-value">' + esc(intermediary) + '</span></div>' : '') +
                    (checkoff ? '<div class="serve-client-detail-row"><span class="serve-client-detail-label">Checkoff</span><span class="serve-client-detail-value">' + esc(checkoff) + '</span></div>' : '') +
                    '</div>';
            }
            const meta = [policy ? 'Policy: ' + policy : '', phone, email].filter(Boolean).join(' · ');
            const actions = '<div class="serve-client-actions">' +
                (policy ? '<form method="GET" action="' + detailUrl + '" class="d-inline"><input type="hidden" name="policy" value="' + esc(policy) + '"><input type="hidden" name="from" value="serve-client"><button type="submit" class="serve-client-cta serve-client-cta-outline" title="View full details"><i class="bi bi-eye"></i> View Details</button></form>' : '') +
                '<button type="button" class="serve-client-cta serve-client-cta-success serve-client-create-ticket" data-erp-store="' + storeId + '" title="Create support ticket"><i class="bi bi-ticket-perforated"></i> Create Ticket</button>' +
                (email ? '<a href="mailto:' + esc(email) + '" class="serve-client-cta serve-client-cta-outline" title="Send email"><i class="bi bi-envelope"></i> Email</a>' : '') +
                (phone ? '<a href="tel:' + esc(telHref(phone)) + '" class="serve-client-cta serve-client-cta-outline" title="Call"><i class="bi bi-telephone"></i> Call</a>' : '') +
                '</div>';
            return '<div class="serve-client-item">' +
                '<div class="serve-client-item-name">' + esc(name) + '</div>' +
                (meta ? '<div class="serve-client-item-meta">' + esc(meta) + '</div>' : '') +
                detailsHtml +
                actions +
                '</div>';
        }).join('');
    }

    function renderCrm(items) {
        if (!items || items.length === 0) {
            crmResults.innerHTML = '<div class="text-muted small py-3">No CRM contacts found.</div>';
            return;
        }
        const contactUrl = '{{ url("/contacts") }}';
        const ticketUrl = '{{ route("tickets.create") }}';
        crmResults.innerHTML = items.map(function(item) {
            const name = item.name || ('Contact #' + item.contactid);
            const meta = [item.phone, item.email].filter(Boolean).join(' · ');
            const actions = '<div class="serve-client-actions">' +
                '<a href="' + contactUrl + '/' + item.contactid + '" class="serve-client-cta serve-client-cta-outline" title="View contact"><i class="bi bi-eye"></i> View Details</a>' +
                '<a href="' + ticketUrl + '?contact_id=' + item.contactid + '&from=serve-client" class="serve-client-cta serve-client-cta-success" title="Create ticket"><i class="bi bi-ticket-perforated"></i> Create Ticket</a>' +
                (item.email ? '<a href="mailto:' + esc(item.email) + '" class="serve-client-cta serve-client-cta-outline" title="Email"><i class="bi bi-envelope"></i> Email</a>' : '') +
                (item.phone ? '<a href="tel:' + esc(telHref(item.phone)) + '" class="serve-client-cta serve-client-cta-outline" title="Call"><i class="bi bi-telephone"></i> Call</a>' : '') +
                '</div>';
            return '<div class="serve-client-item">' +
                '<div class="serve-client-item-name">' + esc(name) + '</div>' +
                (meta ? '<div class="serve-client-item-meta">' + esc(meta) + '</div>' : '') +
                actions +
                '</div>';
        }).join('');
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.serve-client-create-ticket');
        if (!btn) return;
        e.preventDefault();
        var storeId = btn.getAttribute('data-erp-store');
        if (!storeId) return;
        var data = erpDataStore[storeId];
        if (!data) return;
        try {
            formSource.value = 'erp';
            formContactId.value = '';
            formErpData.value = JSON.stringify(data);
            form.submit();
        } catch (err) {
            showError('Could not create ticket. Please try again.');
            console.error(err);
        }
    });

    var debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 2) {
            searchResults.style.display = 'none';
            searchEmpty.style.display = 'block';
            showError('');
            return;
        }
        debounceTimer = setTimeout(function() { fetchResults(q); }, 280);
    });
    // Form submit (Search button or Enter) triggers server-side search and full page reload with results

    var urlSearch = new URLSearchParams(window.location.search).get('search');
    var hasInitialResults = @json(($initialSearch ?? '') && strlen($initialSearch ?? '') >= 2);
    if (urlSearch && urlSearch.trim().length >= 2) {
        searchInput.value = urlSearch.trim();
    }
    if (!hasInitialResults && searchInput.value.trim().length >= 2) {
        searchEmpty.style.display = 'none';
        searchResults.style.display = 'block';
        fetchResults(searchInput.value.trim());
    }
});
</script>
@endsection
