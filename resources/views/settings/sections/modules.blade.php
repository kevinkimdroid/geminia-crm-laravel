<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Module Management</h5>
        <p class="text-muted small mb-0">Enable or disable CRM modules. Disabled modules are hidden from the sidebar.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="app-card overflow-hidden">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach ($modules ?? [] as $module)
            <div class="list-group-item d-flex align-items-center justify-content-between py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: var(--primary-light, rgba(14, 67, 133, 0.12));">
                        <i class="bi {{ $module['icon'] }} text-primary" style="font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <span class="fw-semibold">{{ $module['label'] }}</span>
                    </div>
                </div>
                <div class="form-check form-switch mb-0">
                    <form action="{{ route('settings.modules.toggle') }}" method="POST" class="d-inline module-toggle-form">
                        @csrf
                        <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                        <input type="hidden" name="enabled" value="{{ $module['enabled'] ? '0' : '1' }}">
                        <input class="form-check-input" type="checkbox" role="switch" {{ $module['enabled'] ? 'checked' : '' }}
                            onchange="this.form.querySelector('input[name=enabled]').value = this.checked ? '1' : '0'; this.form.submit();"
                            style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
