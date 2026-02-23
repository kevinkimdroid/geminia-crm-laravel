@extends('layouts.app')

@section('title', 'New Deal')

@section('content')
<div class="page-header">
    <nav class="mb-2"><a href="{{ route('deals.index') }}" class="text-muted small">Deals</a> / Add</nav>
    <h1 class="page-title">New Deal</h1>
    <p class="page-subtitle">Create a new opportunity.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card p-4" style="max-width: 500px;">
    <form method="POST" action="{{ route('deals.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Deal Name *</label>
            <input type="text" name="potentialname" class="form-control" value="{{ old('potentialname') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" name="amount" class="form-control" value="{{ old('amount') }}" step="0.01" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Sales Stage</label>
            <select name="sales_stage" class="form-select">
                <option value="Prospecting" {{ old('sales_stage') == 'Prospecting' ? 'selected' : '' }}>Prospecting</option>
                <option value="Qualification" {{ old('sales_stage') == 'Qualification' ? 'selected' : '' }}>Qualification</option>
                <option value="Proposal" {{ old('sales_stage') == 'Proposal' ? 'selected' : '' }}>Proposal</option>
                <option value="Negotiation" {{ old('sales_stage') == 'Negotiation' ? 'selected' : '' }}>Negotiation</option>
                <option value="Closed Won" {{ old('sales_stage') == 'Closed Won' ? 'selected' : '' }}>Closed Won</option>
                <option value="Closed Lost" {{ old('sales_stage') == 'Closed Lost' ? 'selected' : '' }}>Closed Lost</option>
            </select>
        </div>
        <div class="mb-4">
            <label class="form-label">Closing Date</label>
            <input type="date" name="closingdate" class="form-control" value="{{ old('closingdate') }}">
        </div>
        <button type="submit" class="btn btn-primary-custom">Create Deal</button>
        <a href="{{ route('deals.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
</div>
@endsection
