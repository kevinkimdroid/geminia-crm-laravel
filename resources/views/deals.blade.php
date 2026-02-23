@extends('layouts.app')

@section('title', 'Deals')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Deals</h1>
        <p class="page-subtitle">Track and manage your sales pipeline.</p>
    </div>
    <button class="btn btn-sm btn-primary-custom mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i> New Deal
    </button>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card p-4">
            <div class="card-header-custom">
                <h6>Pipeline Overview</h6>
                <div class="card-actions">
                    <a href="#" class="btn-link"><i class="bi bi-three-dots"></i></a>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:var(--primary-muted)">
                        <p class="text-muted small mb-0">Qualification</p>
                        <h4 class="stat-value mb-0 mt-1">24</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:var(--primary-muted)">
                        <p class="text-muted small mb-0">Proposal</p>
                        <h4 class="stat-value mb-0 mt-1">18</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:var(--primary-muted)">
                        <p class="text-muted small mb-0">Negotiation</p>
                        <h4 class="stat-value mb-0 mt-1">12</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:var(--primary-muted)">
                        <p class="text-muted small mb-0">Closed Won</p>
                        <h4 class="stat-value mb-0 mt-1" style="color:var(--success)">156</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card p-4">
            <div class="text-center py-5">
                <i class="bi bi-briefcase text-muted" style="font-size:3rem"></i>
                <p class="text-muted mt-2 mb-0">Deal management coming soon. Connect to your CRM data.</p>
            </div>
        </div>
    </div>
</div>
@endsection
