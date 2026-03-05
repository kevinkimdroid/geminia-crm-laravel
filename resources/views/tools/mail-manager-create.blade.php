@extends('layouts.app')

@section('title', 'Create Email Record')

@section('content')
<nav class="mb-3">
    <a href="{{ route('tools.mail-manager') }}" class="text-muted small text-decoration-none">Mail Manager</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Create Email</span>
</nav>

<h1 class="page-title mb-4">Create Email Record</h1>
<p class="text-muted mb-4">Log an email sent by a client to {{ $recipientAddress ?? 'life@geminialife.co.ke' }}. Use this when you receive an email outside the system (e.g. forwarded) and want to record it in the CRM.</p>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('tools.mail-manager.store') }}">
    @csrf
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">From (client email) <span class="text-danger">*</span></label>
                    <input type="email" name="from_address" class="form-control" placeholder="client@example.com" value="{{ old('from_address', $presetFrom ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">From name</label>
                    <input type="text" name="from_name" class="form-control" placeholder="Client full name" value="{{ old('from_name', $presetFromName ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">To</label>
                    <input type="text" name="to_addresses" class="form-control" value="{{ old('to_addresses', $recipientAddress ?? 'life@geminialife.co.ke') }}" placeholder="life@geminialife.co.ke">
                    <small class="text-muted">Defaults to life@geminialife.co.ke</small>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="Email subject" value="{{ old('subject') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="datetime-local" name="date" class="form-control" value="{{ old('date', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Body / Message</label>
                    <textarea name="body" class="form-control" rows="8" placeholder="Email content...">{{ old('body') }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create Email Record</button>
        <a href="{{ route('tools.mail-manager') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
