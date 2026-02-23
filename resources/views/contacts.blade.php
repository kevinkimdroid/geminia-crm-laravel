@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Contacts</h1>
        <p class="page-subtitle">Manage your customer and prospect contacts.</p>
    </div>
    <button class="btn btn-sm btn-primary-custom mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i> Add Contact
    </button>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Total Contacts</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">5,758</h2>
            <span class="stat-change positive">+2.57%</span>
            <p class="text-muted small mb-0 mt-1">vs last month</p>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>New This Month</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">142</h2>
            <span class="stat-change positive">+18%</span>
            <p class="text-muted small mb-0 mt-1">vs last month</p>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Active</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">3,892</h2>
            <span class="stat-change positive">68%</span>
            <p class="text-muted small mb-0 mt-1">of total</p>
        </div>
    </div>
    <div class="col-12">
        <div class="card p-4">
            <div class="text-center py-5">
                <i class="bi bi-person-lines-fill text-muted" style="font-size:3rem"></i>
                <p class="text-muted mt-2 mb-0">Contact list and management coming soon.</p>
            </div>
        </div>
    </div>
</div>
@endsection
