@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Contacts</h1>
        <p class="page-subtitle">Manage your customer and prospect contacts.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <input type="text" id="contactsSearch" class="form-control form-control-sm" placeholder="Search contact..." style="width: 220px;">
        <a href="{{ route('contacts.create') }}" class="btn btn-sm btn-primary-custom">
            <i class="bi bi-plus-lg me-1"></i> Add Contact
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show">{{ session('info') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Total Contacts</div>
                <div class="h5 mb-0">{{ number_format($contacts->total() ?? 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Showing This Page</div>
                <div class="h5 mb-0">{{ number_format($contacts->count() ?? 0) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="contactsTable">
            <thead>
                <tr>
                    <th><button type="button" class="btn btn-link p-0 text-decoration-none sort-btn" data-col="0">Name</button></th>
                    <th><button type="button" class="btn btn-link p-0 text-decoration-none sort-btn" data-col="1">Email</button></th>
                    <th><button type="button" class="btn btn-link p-0 text-decoration-none sort-btn" data-col="2">Phone</button></th>
                    <th><button type="button" class="btn btn-link p-0 text-decoration-none sort-btn" data-col="3">Mobile</button></th>
                    <th width="120"></th>
                </tr>
            </thead>
            <tbody id="contactsTableBody">
                @forelse ($contacts as $contact)
                    <tr class="contact-row">
                        <td><a href="{{ route('contacts.show', $contact->contactid) }}" class="text-decoration-none fw-semibold">{{ $contact->full_name }}</a></td>
                        <td>{{ personal_email_only($contact->email ?? null) ?? '—' }}</td>
                        <td>{{ $contact->phone ?: '—' }}</td>
                        <td>{{ $contact->mobile ?: '—' }}</td>
                        <td>
                            <a href="{{ route('contacts.edit', $contact->contactid) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">No contacts found. <a href="{{ route('contacts.create') }}">Add your first contact</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($contacts->hasPages())
        <div class="card-footer bg-transparent border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small">Showing {{ $contacts->firstItem() ?? 0 }}–{{ $contacts->lastItem() ?? 0 }} of {{ $contacts->total() }}</span>
            {{ $contacts->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('contactsSearch');
    const tbody = document.getElementById('contactsTableBody');
    const rows = () => Array.from(tbody?.querySelectorAll('tr.contact-row') || []);

    searchInput?.addEventListener('input', function () {
        const q = (this.value || '').toLowerCase().trim();
        rows().forEach(row => {
            const txt = (row.textContent || '').toLowerCase();
            row.style.display = txt.includes(q) ? '' : 'none';
        });
    });

    let sortState = { col: -1, asc: true };
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const col = parseInt(this.dataset.col || '-1', 10);
            if (col < 0) return;
            sortState.asc = sortState.col === col ? !sortState.asc : true;
            sortState.col = col;

            const sorted = rows().sort((a, b) => {
                const av = (a.children[col]?.innerText || '').trim().toLowerCase();
                const bv = (b.children[col]?.innerText || '').trim().toLowerCase();
                return sortState.asc ? av.localeCompare(bv) : bv.localeCompare(av);
            });
            sorted.forEach(r => tbody.appendChild(r));
        });
    });
});
</script>
@endsection
