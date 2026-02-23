@extends('layouts.app')

@section('title', 'Leads')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Leads</h1>
        <p class="page-subtitle">Manage and track your sales leads.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle px-3 py-2" data-bs-toggle="dropdown">More</button>
            <ul class="dropdown-menu"><li><a class="dropdown-item" href="#">Export</a></li><li><a class="dropdown-item" href="#">Filter</a></li></ul>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-primary-custom" data-bs-toggle="dropdown">Add Widget</button>
            <ul class="dropdown-menu"><li><a class="dropdown-item" href="#">Tickets by Status</a></li><li><a class="dropdown-item" href="#">Revenue by Salesperson</a></li><li><a class="dropdown-item" href="#">Overdue Activities</a></li></ul>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Tickets by Status</h6>
                <div class="card-actions dropdown">
                    <a href="#" class="btn-link" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#">Refresh</a></li><li><a class="dropdown-item" href="#">Settings</a></li></ul>
                </div>
            </div>
            <div class="d-flex gap-4 align-items-end" style="height:160px">
                <div class="flex-fill text-center">
                    <div class="rounded-3 mx-auto" style="height:60px;width:100%;background:linear-gradient(180deg,var(--primary),var(--primary-hover))"></div>
                    <span class="d-block mt-2 fw-bold">2</span>
                    <span class="small text-muted">Response</span>
                </div>
                <div class="flex-fill text-center">
                    <div class="rounded-3 mx-auto" style="height:120px;width:100%;background:linear-gradient(180deg,#22c55e,#16a34a)"></div>
                    <span class="d-block mt-2 fw-bold">524</span>
                    <span class="small text-muted">Closed</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Revenue by Salesperson</h6>
                <div class="card-actions dropdown">
                    <a href="#" class="btn-link" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#">Refresh</a></li><li><a class="dropdown-item" href="#">Settings</a></li></ul>
                </div>
            </div>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size:3rem"></i>
                <p class="text-muted mt-2 mb-0 small">No Opportunities matched this criteria</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Overdue Activities</h6>
                <select class="form-select form-select-sm" style="width:auto"><option>Mine</option><option>All</option></select>
            </div>
            <div class="form-check py-3 border-bottom">
                <input class="form-check-input" type="checkbox" id="c1">
                <label class="form-check-label ms-2" for="c1">
                    <span class="d-block fw-semibold">Download and review statement</span>
                    <small class="text-muted">1 year ago</small>
                </label>
            </div>
            <div class="form-check py-3">
                <input class="form-check-input" type="checkbox" id="c2">
                <label class="form-check-label ms-2" for="c2">
                    <span class="d-block fw-semibold">Send Statement to Client</span>
                    <small class="text-muted">1 year ago</small>
                </label>
            </div>
        </div>
    </div>
</div>
@endsection
