@extends('layouts.app')

@section('title', 'Complaint ' . $complaint->complaint_ref)

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('compliance.complaints.index') }}" class="text-muted small text-decoration-none">Complaint Register</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">{{ $complaint->complaint_ref }}</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1">{{ $complaint->complaint_ref }}</h1>
        <p class="app-page-sub mb-0">Received {{ $complaint->date_received?->format('d M Y') ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('compliance.complaints.edit', $complaint) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="{{ route('compliance.complaints.index') }}" class="btn btn-outline-secondary">Back to Register</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="app-card mb-4">
            <div class="p-4">
                <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Complaint Details</h6>
                <p class="mb-0" style="white-space:pre-wrap;">{{ e($complaint->description) }}</p>
            </div>
        </div>
        @if($complaint->resolution_notes)
        <div class="app-card mb-4">
            <div class="p-4">
                <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-success);letter-spacing:0.08em">Resolution</h6>
                <p class="mb-0" style="white-space:pre-wrap;">{{ e($complaint->resolution_notes) }}</p>
                @if($complaint->date_resolved)
                    <p class="text-muted small mt-2 mb-0">Resolved on {{ $complaint->date_resolved->format('d M Y') }}</p>
                @endif
            </div>
        </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="app-card mb-4">
            <div class="p-4">
                <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Summary</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted py-1">Complainant</td><td class="fw-medium">{{ $complaint->complainant_name }}</td></tr>
                    <tr><td class="text-muted py-1">Phone</td><td>{{ $complaint->complainant_phone ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Email</td><td>{{ $complaint->complainant_email ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Policy</td><td class="font-monospace small">{{ $complaint->policy_number ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Nature</td><td>{{ $complaint->nature ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Source</td><td>{{ $complaint->source ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Status</td><td><span class="badge bg-{{ in_array($complaint->status, ['Resolved','Closed']) ? 'success' : 'warning' }}">{{ $complaint->status }}</span></td></tr>
                    <tr><td class="text-muted py-1">Priority</td><td>{{ $complaint->priority ?: '—' }}</td></tr>
                    <tr><td class="text-muted py-1">Assigned To</td><td>{{ $complaint->assigned_to ?: '—' }}</td></tr>
                </table>
                @if($complaint->contact_id && ($contact ?? null))
                    <div class="mt-3 pt-3 border-top">
                        <a href="{{ route('contacts.show', $complaint->contact_id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-person me-1"></i>View Contact</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
