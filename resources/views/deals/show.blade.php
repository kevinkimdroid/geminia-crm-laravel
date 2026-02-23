@extends('layouts.app')

@section('title', ($deal->potentialname ?? 'Deal') . ' — Deal')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <nav class="mb-2"><a href="{{ route('deals.index') }}" class="text-muted small">Deals</a> / <span class="text-dark">{{ $deal->potentialname ?? 'Deal' }}</span></nav>
        <h1 class="page-title">{{ $deal->potentialname ?? 'Untitled Deal' }}</h1>
        <p class="page-subtitle">KES {{ number_format($deal->amount ?? 0, 0) }} • {{ $deal->sales_stage ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('deals.edit', $deal->potentialid) }}" class="btn btn-sm btn-primary-custom">Edit</a>
        <form action="{{ route('deals.destroy', $deal->potentialid) }}" method="POST" onsubmit="return confirm('Delete this deal?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-secondary text-danger">Delete</button>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <h6 class="text-muted text-uppercase small mb-3">Deal Details</h6>
            <dl class="row mb-0">
                <dt class="col-sm-4">Name</dt>
                <dd class="col-sm-8">{{ $deal->potentialname ?? '—' }}</dd>
                <dt class="col-sm-4">Amount</dt>
                <dd class="col-sm-8">KES {{ number_format($deal->amount ?? 0, 2) }}</dd>
                <dt class="col-sm-4">Stage</dt>
                <dd class="col-sm-8">{{ $deal->sales_stage ?? '—' }}</dd>
                <dt class="col-sm-4">Closing Date</dt>
                <dd class="col-sm-8">{{ $deal->closingdate ? \Carbon\Carbon::parse($deal->closingdate)->format('M d, Y') : '—' }}</dd>
                <dt class="col-sm-4">Source</dt>
                <dd class="col-sm-8">{{ $deal->leadsource ?? '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
