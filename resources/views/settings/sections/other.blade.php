<div class="row g-4">
    <div class="col-md-6">
        <div class="app-card overflow-hidden">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Profile</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:64px;height:64px"><i class="bi bi-person-fill fs-4"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0">{{ $currentUserName ?? 'User' }}</h5>
                        <p class="text-muted small mb-0">{{ $currentUserRole ?? '—' }}</p>
                        <p class="text-muted small mb-0">{{ $currentUserEmail ?? '' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="app-card overflow-hidden">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Database</h6>
                <span class="badge bg-success">Connected</span>
                <p class="text-muted small mt-2 mb-0">Vtiger MySQL</p>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="app-card overflow-hidden">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Application</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small">App Name</dt>
                    <dd class="col-sm-8 mb-2">Geminia Life Insurance CRM</dd>
                    <dt class="col-sm-4 text-muted small">Environment</dt>
                    <dd class="col-sm-8 mb-2">{{ config('app.env') }}</dd>
                    <dt class="col-sm-4 text-muted small">Timezone</dt>
                    <dd class="col-sm-8 mb-0">{{ config('app.timezone') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
