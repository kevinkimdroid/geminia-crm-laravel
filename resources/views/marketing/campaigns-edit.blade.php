@extends('layouts.app')

@section('title', 'Edit Campaign — ' . Str::limit($campaign->campaign_name, 40))

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('marketing.campaigns.index') }}" class="text-muted small text-decoration-none">Campaigns</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">{{ Str::limit($campaign->campaign_name, 40) }}</span>
</nav>
<h1 class="app-page-title mb-4">Edit Campaign</h1>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('marketing.campaigns.update', $campaign) }}">
    @csrf
    @method('PUT')

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Basic Information</h6>
            <div class="mb-4">
                <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                <input type="text" name="campaign_name" class="form-control form-control-lg" value="{{ old('campaign_name', $campaign->campaign_name) }}" required>
                @error('campaign_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Campaign Type</label>
                    <input type="text" name="campaign_type" class="form-control" value="{{ old('campaign_type', $campaign->campaign_type) }}" placeholder="e.g. Email, Social, Webinar">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="campaign_status" class="form-select">
                        <option value="Active" {{ old('campaign_status', $campaign->campaign_status) == 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Planning" {{ old('campaign_status', $campaign->campaign_status) == 'Planning' ? 'selected' : '' }}>Planning</option>
                        <option value="Completed" {{ old('campaign_status', $campaign->campaign_status) == 'Completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Revenue & Timeline</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Expected Revenue (KES)</label>
                    <input type="number" name="expected_revenue" class="form-control" value="{{ old('expected_revenue', $campaign->expected_revenue) }}" step="0.01" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Expected Close Date</label>
                    <input type="date" name="expected_close_date" class="form-control" value="{{ old('expected_close_date', $campaign->expected_close_date?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="app-card mb-4">
        <div class="p-4">
            <h6 class="text-uppercase small fw-bold mb-4" style="color:var(--geminia-primary);letter-spacing:0.08em">Assignment</h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control" value="{{ old('assigned_to', $campaign->assigned_to) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">List Name</label>
                    <input type="text" name="list_name" class="form-control" value="{{ old('list_name', $campaign->list_name) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn app-btn-primary"><i class="bi bi-check-lg me-1"></i>Update Campaign</button>
        <a href="{{ route('marketing.campaigns.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<style>
.breadcrumb-nav a:hover { color: var(--geminia-primary) !important; }
</style>
@endsection
