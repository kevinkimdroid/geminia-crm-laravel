@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Contacts</h1>
        <p class="page-subtitle">Manage your customer and prospect contacts.</p>
    </div>
    <a href="{{ route('contacts.create') }}" class="btn btn-sm btn-primary-custom mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i> Add Contact
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Mobile</th>
                    <th width="120"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td><a href="{{ route('contacts.show', $contact->contactid) }}" class="text-decoration-none fw-semibold">{{ $contact->full_name }}</a></td>
                        <td>{{ personal_email_only($contact->email ?? null) ?? '—' }}</td>
                        <td>{{ $contact->phone }}</td>
                        <td>{{ $contact->mobile }}</td>
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
@endsection
