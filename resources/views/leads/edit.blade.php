@extends('layouts.app')

@section('title', 'Edit ' . $lead->full_name)

@section('content')
<div class="page-header">
    <nav class="breadcrumb-nav mb-2">
        <a href="{{ route('leads.index') }}" class="text-muted">Leads</a>
        <span class="mx-2 text-muted">/</span>
        <a href="{{ route('leads.show', $lead->leadid) }}" class="text-muted">{{ $lead->full_name }}</a>
        <span class="mx-2 text-muted">/</span>
        <span class="text-dark fw-semibold">Edit</span>
    </nav>
    <h1 class="page-title">Edit Lead</h1>
    <p class="page-subtitle">Update lead information.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card lead-form-card p-4">
            <form method="POST" action="{{ route('leads.update', $lead->leadid) }}">
                @csrf
                @method('PUT')
                <div class="form-section mb-4">
                    <h6 class="form-section-title"><i class="bi bi-person me-2"></i>Contact Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" value="{{ old('firstname', $lead->firstname) }}" required>
                            @error('firstname')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" value="{{ old('lastname', $lead->lastname) }}" required>
                            @error('lastname')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="form-section mb-4">
                    <h6 class="form-section-title"><i class="bi bi-building me-2"></i>Company</h6>
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control" value="{{ old('company', $lead->company) }}">
                    </div>
                </div>
                <div class="form-section mb-4">
                    <h6 class="form-section-title"><i class="bi bi-envelope me-2"></i>Contact Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $lead->email) }}">
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $lead->phone) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lead Source</label>
                            <select name="leadsource" class="form-select">
                                <option value="">— Select source —</option>
                                @php $sources = ['Website', 'Ads', 'Referral', 'Email Campaign', 'Event', 'Cold Call', 'Direct', 'Partner', 'Social Media', 'Other']; @endphp
                                @foreach($sources as $src)
                                <option value="{{ $src }}" {{ old('leadsource', $lead->leadsource ?? '') === $src ? 'selected' : '' }}>{{ $src }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Update Lead
                    </button>
                    <a href="{{ route('leads.show', $lead->leadid) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.breadcrumb-nav a { text-decoration: none; font-size: 0.9rem; }
.breadcrumb-nav a:hover { color: var(--primary) !important; }
.lead-form-card { border-radius: 16px; }
.form-section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--card-border); }
</style>
@endsection
