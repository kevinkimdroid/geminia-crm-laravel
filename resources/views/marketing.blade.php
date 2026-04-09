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
        <div class="row g-4">
            @if($can('marketing.campaigns') || $can('marketing.social-media') || $can('marketing.broadcast') || $can('support.customers'))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('marketing.broadcast') }}" class="card p-4 h-100 text-decoration-none" style="border:2px solid var(--geminia-primary, #0E4385);border-radius:16px;color:inherit;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:var(--geminia-primary-muted, rgba(14,67,133,0.12));color:var(--geminia-primary, #0E4385);font-size:1.5rem;"><i class="bi bi-broadcast"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Email &amp; SMS broadcast</h6>
                            <p class="text-muted small mb-0">Mass message contacts (same mail &amp; Advanta SMS as elsewhere).</p>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-primary"></i>
                    </div>
                </a>
            </div>
            @endif
            @if($can('marketing.campaigns'))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('marketing.campaigns.index') }}" class="card p-4 h-100 text-decoration-none" style="border-radius:16px;border:1px solid var(--card-border, rgba(14,67,133,0.12));color:inherit;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center bg-light text-primary" style="width:56px;height:56px;font-size:1.5rem;"><i class="bi bi-megaphone"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Campaigns</h6>
                            <p class="text-muted small mb-0">Create and manage marketing campaigns.</p>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </a>
            </div>
            @endif
            @if($can('marketing.social-media'))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('marketing.social-media') }}" class="card p-4 h-100 text-decoration-none" style="border-radius:16px;border:1px solid var(--card-border, rgba(14,67,133,0.12));color:inherit;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center bg-light text-primary" style="width:56px;height:56px;font-size:1.5rem;"><i class="bi bi-facebook"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Social media</h6>
                            <p class="text-muted small mb-0">Schedule and monitor posts.</p>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </a>
            </div>
            @endif
        </div>
        @if(!($can('marketing.campaigns') || $can('marketing.social-media') || $can('marketing.broadcast') || $can('support.customers')))
        <div class="card p-4 mt-2">
            <div class="text-center py-4 text-muted small">You do not have access to marketing tools. Ask an administrator to enable Campaigns or related modules for your role.</div>
        </div>
        @endif
    </div>
</div>
@endsection
