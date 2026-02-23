@extends('layouts.app')

@section('title', 'Marketing')

@section('content')
<div class="page-header">
    <h1 class="page-title">Marketing</h1>
    <p class="page-subtitle">Campaigns, email marketing, and lead generation.</p>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Active Campaigns</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">12</h2>
            <p class="text-muted small mb-0">Running this month</p>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Email Opens</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">34.2%</h2>
            <span class="stat-change positive">+5.1%</span>
            <p class="text-muted small mb-0 mt-1">vs last campaign</p>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Leads Generated</h6>
                <a href="#" class="card-arrow"><i class="bi bi-arrow-right"></i></a>
            </div>
            <h2 class="stat-value mb-1">847</h2>
            <span class="stat-change positive">+22%</span>
            <p class="text-muted small mb-0 mt-1">this month</p>
        </div>
    </div>
    <div class="col-12">
        <div class="card p-4">
            <div class="text-center py-5">
                <i class="bi bi-megaphone-fill text-muted" style="font-size:3rem"></i>
                <p class="text-muted mt-2 mb-0">Marketing automation and campaigns coming soon.</p>
            </div>
        </div>
    </div>
</div>
@endsection
