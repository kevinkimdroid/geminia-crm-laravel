@extends('layouts.app')

@section('title', 'Tools')

@section('content')
<div class="page-header">
    <h1 class="page-title">Tools</h1>
    <p class="page-subtitle">Workflows, integrations, and utilities.</p>
</div>

<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <div class="card tools-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="tools-icon"><i class="bi bi-infinity"></i></div>
                <div>
                    <h6 class="fw-bold mb-0">Workflows</h6>
                    <p class="text-muted small mb-0">Automate repetitive tasks</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card tools-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="tools-icon"><i class="bi bi-grid-1x2"></i></div>
                <div>
                    <h6 class="fw-bold mb-0">Integrations</h6>
                    <p class="text-muted small mb-0">Connect external apps</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card tools-card h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="tools-icon"><i class="bi bi-code-slash"></i></div>
                <div>
                    <h6 class="fw-bold mb-0">API & Developer</h6>
                    <p class="text-muted small mb-0">Developer tools</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card tools-coming-card overflow-hidden">
            <div class="card-body p-5 text-center">
                <div class="tools-coming-icon"><i class="bi bi-tools"></i></div>
                <h4 class="mt-4 mb-2">Tools Coming Soon</h4>
                <p class="text-muted mb-0">Workflows, integrations, and developer tools will be available here.</p>
            </div>
        </div>
    </div>
</div>

<style>
.tools-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); transition: transform .2s, box-shadow .2s; }
.tools-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(14, 67, 133, 0.1); }
.tools-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--primary-light, rgba(14, 67, 133, 0.12)); color: var(--primary, #0E4385); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
.tools-coming-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.tools-coming-icon { width: 100px; height: 100px; margin: 0 auto; background: linear-gradient(135deg, var(--primary-light, rgba(14, 67, 133, 0.12)) 0%, rgba(14, 67, 133, 0.06) 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--primary, #0E4385); }
</style>
@endsection
