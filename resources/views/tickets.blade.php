@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Tickets</h1>
        <p class="page-subtitle">Support tickets and customer issues.</p>
    </div>
    <button class="btn btn-sm btn-primary-custom mt-2 mt-md-0">
        <i class="bi bi-plus-lg me-1"></i> New Ticket
    </button>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Tickets by Status</h6>
                <div class="card-actions dropdown">
                    <a href="#" class="btn-link" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Refresh</a></li>
                        <li><a class="dropdown-item" href="#">Settings</a></li>
                    </ul>
                </div>
            </div>
            <div class="d-flex gap-4 align-items-end" style="height:160px">
                <div class="flex-fill text-center">
                    <div class="rounded-3 mx-auto" style="height:60px;width:100%;background:linear-gradient(180deg,var(--primary),var(--primary-hover))"></div>
                    <span class="d-block mt-2 fw-bold">2</span>
                    <span class="small text-muted">Response</span>
                </div>
                <div class="flex-fill text-center">
                    <div class="rounded-3 mx-auto" style="height:90px;width:100%;background:linear-gradient(180deg,#f59e0b,#d97706)"></div>
                    <span class="d-block mt-2 fw-bold">17</span>
                    <span class="small text-muted">In Progress</span>
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
                <h6>Open Tickets</h6>
                <div class="card-actions dropdown">
                    <a href="#" class="btn-link" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Refresh</a></li>
                    </ul>
                </div>
            </div>
            <h2 class="stat-value mb-1">2,316</h2>
            <p class="text-muted small mb-0">Awaiting response or in progress</p>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <div class="card-header-custom">
                <h6>Overdue Activities</h6>
                <select class="form-select form-select-sm" style="width:auto"><option>Mine</option><option>All</option></select>
            </div>
            <div class="form-check py-3 border-bottom">
                <input class="form-check-input" type="checkbox" id="t1">
                <label class="form-check-label ms-2" for="t1">
                    <span class="d-block fw-semibold">Download and review statement</span>
                    <small class="text-muted">1 year ago</small>
                </label>
            </div>
            <div class="form-check py-3">
                <input class="form-check-input" type="checkbox" id="t2">
                <label class="form-check-label ms-2" for="t2">
                    <span class="d-block fw-semibold">Send Statement to Client</span>
                    <small class="text-muted">1 year ago</small>
                </label>
            </div>
        </div>
    </div>
</div>
@endsection
