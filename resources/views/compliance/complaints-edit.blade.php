@extends('layouts.app')

@section('title', 'Edit Complaint — ' . $complaint->complaint_ref)

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('compliance.complaints.index') }}" class="text-muted small text-decoration-none">Complaint Register</a>
    <span class="text-muted mx-2">/</span>
    <a href="{{ route('compliance.complaints.show', $complaint) }}" class="text-muted small text-decoration-none">{{ $complaint->complaint_ref }}</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Edit</span>
</nav>
<h1 class="app-page-title mb-4">Edit Complaint</h1>

<form method="POST" action="{{ route('compliance.complaints.update', $complaint) }}">
    @csrf
    @method('PUT')

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-3" style="color:var(--geminia-primary);letter-spacing:0.08em">Reference</h6>
            <p class="text-muted mb-0 font-monospace">{{ $complaint->complaint_ref }}</p>
        </div>
    </div>

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Complainant Details</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date Received <span class="text-danger">*</span></label>
                    <input type="date" name="date_received" class="form-control" value="{{ old('date_received', $complaint->date_received?->format('Y-m-d')) }}" required>
                    @error('date_received')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Source</label>
                    <select name="source" class="form-select">
                        <option value="">Select source</option>
                        @foreach(\App\Models\Complaint::SOURCES as $val => $label)
                            <option value="{{ $val }}" {{ old('source', $complaint->source) == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Complainant Name <span class="text-danger">*</span></label>
                    <input type="text" name="complainant_name" class="form-control" value="{{ old('complainant_name', $complaint->complainant_name) }}" required>
                    @error('complainant_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="complainant_phone" class="form-control" value="{{ old('complainant_phone', $complaint->complainant_phone) }}">
                </div>
            </div>
            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="complainant_email" class="form-control" value="{{ old('complainant_email', $complaint->complainant_email) }}">
                    @error('complainant_email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Policy Number</label>
                    <input type="text" name="policy_number" class="form-control font-monospace" value="{{ old('policy_number', $complaint->policy_number) }}">
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label fw-semibold">Contact ID</label>
                <input type="number" name="contact_id" class="form-control" value="{{ old('contact_id', $complaint->contact_id) }}" placeholder="Contact ID if known client" min="1">
            </div>
        </div>
    </div>

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Complaint & Resolution</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nature</label>
                    <select name="nature" class="form-select">
                        <option value="">Select nature</option>
                        @foreach(\App\Models\Complaint::NATURES as $val => $label)
                            <option value="{{ $val }}" {{ old('nature', $complaint->nature) == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Models\Complaint::STATUSES as $val => $label)
                            <option value="{{ $val }}" {{ old('status', $complaint->status) == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Priority</label>
                    <select name="priority" class="form-select">
                        @foreach(\App\Models\Complaint::PRIORITIES as $val => $label)
                            <option value="{{ $val }}" {{ old('priority', $complaint->priority) == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date Resolved</label>
                    <input type="date" name="date_resolved" class="form-control" value="{{ old('date_resolved', $complaint->date_resolved?->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="5" required>{{ old('description', $complaint->description) }}</textarea>
                @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mt-4">
                <label class="form-label fw-semibold">Resolution Notes</label>
                <textarea name="resolution_notes" class="form-control" rows="4" placeholder="Actions taken, outcome, complainant response">{{ old('resolution_notes', $complaint->resolution_notes) }}</textarea>
            </div>
            <div class="mt-4">
                <label class="form-label fw-semibold">Assigned To</label>
                <input type="text" name="assigned_to" class="form-control" value="{{ old('assigned_to', $complaint->assigned_to) }}">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn app-btn-primary"><i class="bi bi-check-lg me-1"></i>Update</button>
        <a href="{{ route('compliance.complaints.show', $complaint) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
