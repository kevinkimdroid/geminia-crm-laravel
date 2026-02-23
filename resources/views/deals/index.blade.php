@extends('layouts.app')

@section('title', 'Deals')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Deals</h1>
        <p class="page-subtitle">Track and manage your sales pipeline.</p>
    </div>
    <a href="{{ route('deals.create') }}" class="btn btn-sm btn-primary-custom mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i> New Deal
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card p-4">
            <p class="text-muted small mb-0">Pipeline Value</p>
            <h2 class="stat-value mb-0" style="color: var(--primary);">KES {{ number_format($pipelineValue ?? 0, 0) }}</h2>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Deal Name</th>
                    <th>Amount</th>
                    <th>Stage</th>
                    <th>Closing Date</th>
                    <th width="120"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deals as $deal)
                    <tr>
                        <td><a href="{{ route('deals.show', $deal->potentialid) }}" class="text-decoration-none fw-semibold">{{ $deal->potentialname ?? 'Untitled' }}</a></td>
                        <td>KES {{ number_format($deal->amount ?? 0, 0) }}</td>
                        <td><span class="badge bg-secondary">{{ $deal->sales_stage ?? '—' }}</span></td>
                        <td>{{ $deal->closingdate ? \Carbon\Carbon::parse($deal->closingdate)->format('M d, Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('deals.edit', $deal->potentialid) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">No deals found. <a href="{{ route('deals.create') }}">Add your first deal</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($deals->hasPages())
        <div class="card-footer bg-transparent border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small">Showing {{ $deals->firstItem() ?? 0 }}–{{ $deals->lastItem() ?? 0 }} of {{ $deals->total() }}</span>
            {{ $deals->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
