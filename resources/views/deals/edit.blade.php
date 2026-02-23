@extends('layouts.app')

@section('title', 'Edit Deal')

@section('content')
<div class="page-header">
    <nav class="mb-2"><a href="{{ route('deals.index') }}" class="text-muted small">Deals</a> / <a href="{{ route('deals.show', $deal->potentialid) }}">{{ $deal->potentialname ?? 'Deal' }}</a> / Edit</nav>
    <h1 class="page-title">Edit Deal</h1>
</div>

<div class="card p-4" style="max-width: 500px;">
    <form method="POST" action="{{ route('deals.update', $deal->potentialid) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">Deal Name *</label>
            <input type="text" name="potentialname" class="form-control" value="{{ old('potentialname', $deal->potentialname) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" name="amount" class="form-control" value="{{ old('amount', $deal->amount) }}" step="0.01" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Sales Stage</label>
            <select name="sales_stage" class="form-select">
                <option value="Prospecting" {{ old('sales_stage', $deal->sales_stage) == 'Prospecting' ? 'selected' : '' }}>Prospecting</option>
                <option value="Qualification" {{ old('sales_stage', $deal->sales_stage) == 'Qualification' ? 'selected' : '' }}>Qualification</option>
                <option value="Proposal" {{ old('sales_stage', $deal->sales_stage) == 'Proposal' ? 'selected' : '' }}>Proposal</option>
                <option value="Negotiation" {{ old('sales_stage', $deal->sales_stage) == 'Negotiation' ? 'selected' : '' }}>Negotiation</option>
                <option value="Closed Won" {{ old('sales_stage', $deal->sales_stage) == 'Closed Won' ? 'selected' : '' }}>Closed Won</option>
                <option value="Closed Lost" {{ old('sales_stage', $deal->sales_stage) == 'Closed Lost' ? 'selected' : '' }}>Closed Lost</option>
            </select>
        </div>
        <div class="mb-4">
            <label class="form-label">Closing Date</label>
            <input type="date" name="closingdate" class="form-control" value="{{ old('closingdate', $deal->closingdate ? \Carbon\Carbon::parse($deal->closingdate)->format('Y-m-d') : '') }}">
        </div>
        <button type="submit" class="btn btn-primary-custom">Update</button>
        <a href="{{ route('deals.show', $deal->potentialid) }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
</div>
@endsection
