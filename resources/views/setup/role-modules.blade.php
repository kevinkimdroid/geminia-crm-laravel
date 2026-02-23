@extends('layouts.app')

@section('title', 'Module Permissions — ' . $role->rolename)

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('settings.crm') }}">Settings</a></li>
                <li class="breadcrumb-item"><a href="{{ route('setup.roles') }}">Roles</a></li>
                <li class="breadcrumb-item active">{{ $role->rolename }}</li>
            </ol>
        </nav>
        <h1 class="page-title">Module Permissions</h1>
        <p class="page-subtitle">Select which modules users with role "{{ $role->rolename }}" can access.</p>
    </div>
    <a href="{{ route('setup.roles') }}" class="btn btn-outline-secondary">Back to Roles</a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card setup-modules-card">
    <div class="card-body p-4">
        <form action="{{ route('setup.roles.modules.update', $role->roleid) }}" method="POST">
            @csrf
            <div class="row g-3">
                @foreach ($moduleList as $mod)
                <div class="col-md-6 col-lg-4">
                    <label class="setup-module-checkbox d-block m-0" for="mod_{{ $mod['tabid'] }}">
                        <input type="checkbox" name="modules[]" value="{{ $mod['tabid'] }}" id="mod_{{ $mod['tabid'] }}"
                            {{ in_array($mod['tabid'], $allowedTabIds) ? 'checked' : '' }}>
                        <div class="setup-module-card">
                            <strong>{{ $mod['label'] }}</strong>
                            <span class="text-muted small d-block">{{ $mod['tab_name'] }}</span>
                        </div>
                    </label>
                </div>
                @endforeach
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary-custom">Save Module Permissions</button>
            </div>
        </form>
    </div>
</div>

<style>
.setup-modules-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.setup-module-checkbox input { display: none; }
.setup-module-checkbox .setup-module-card { padding: 1rem 1.25rem; border: 2px solid var(--card-border, #e2e8f0); border-radius: 12px; cursor: pointer; transition: all .2s; }
.setup-module-checkbox input:checked + .setup-module-card { border-color: var(--primary, #0E4385); background: var(--primary-muted, rgba(14, 67, 133, 0.06)); }
.setup-module-checkbox:hover .setup-module-card { border-color: var(--primary, #0E4385); }
</style>
@endsection
