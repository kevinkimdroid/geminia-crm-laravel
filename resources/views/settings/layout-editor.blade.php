@extends('layouts.app')

@section('title', 'Layout Editor — Module Layouts & Fields')

@section('content')
<nav class="mb-3">
    <a href="{{ route('settings') }}" class="text-muted small">Settings</a>
    <span class="text-muted mx-1">/</span>
    <a href="{{ route('settings.crm') }}?section=modules" class="text-muted small">Module Management</a>
    <span class="text-muted mx-1">/</span>
    <span class="text-dark fw-semibold">Layout Editor</span>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="page-title">Layout Editor</h1>
        <p class="page-subtitle">Customize field layouts and options per module.</p>
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

{{-- Module selector --}}
<div class="card mb-4">
    <div class="card-body p-4">
        <label class="form-label fw-semibold">Select Module</label>
        <form method="GET" action="{{ route('settings.layout-editor') }}" id="moduleSelectForm" class="d-flex gap-2 align-items-end">
            <select name="module" class="form-select" style="max-width: 280px;" id="moduleSelect">
                <option value="">— Choose a module —</option>
                @foreach ($modules ?? [] as $mod)
                <option value="{{ $mod['tabid'] }}" {{ ($selectedTabid ?? 0) == $mod['tabid'] ? 'selected' : '' }}>{{ $mod['label'] }}</option>
                @endforeach
            </select>
            @if ($selectedTabid ?? 0)
            <a href="{{ route('settings.layout-editor') }}" class="btn btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
</div>

@if ($layout && $layout['tab'])
{{-- Tabs --}}
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" href="#detail-layout"><i class="bi bi-layout-text-sidebar me-1"></i> Detail View Layout</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-muted" href="#relationships"><i class="bi bi-link-45deg me-1"></i> Relationships</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-muted" href="#duplicate-prevention"><i class="bi bi-shield-check me-1"></i> Duplicate Prevention</a>
    </li>
</ul>

{{-- Detail View Layout --}}
<div id="detail-layout">
    @foreach ($layout['blocks'] ?? [] as $block)
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4 bg-light">
            <h6 class="mb-0 fw-semibold">{{ $block['label'] }}</h6>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Add Custom Field">+ ADD CUSTOM FIELD</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="View Hidden Fields">VIEW HIDDEN FIELDS</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @foreach ($block['fields'] ?? [] as $field)
                <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3 py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-medium">{{ $field['fieldlabel'] }}</span>
                        @if ($field['mandatory'])
                        <span class="text-danger">*</span>
                        @endif
                        <span class="badge bg-light text-dark border">{{ $field['uitype_label'] }}</span>
                    </div>
                    <form action="{{ route('settings.layout-editor.field.update') }}" method="POST" class="d-flex flex-wrap align-items-center gap-2 field-options-form">
                        @csrf
                        <input type="hidden" name="fieldid" value="{{ $field['fieldid'] }}">
                        <input type="hidden" name="redirect_tabid" value="{{ $layout['tab']['tabid'] }}">
                        <label class="d-inline-flex align-items-center gap-1 small mb-0">
                            <input type="hidden" name="mandatory" value="0">
                            <input type="checkbox" name="mandatory" value="1" {{ $field['mandatory'] ? 'checked' : '' }} onchange="this.form.submit()"> Mandatory
                        </label>
                        <label class="d-inline-flex align-items-center gap-1 small mb-0">
                            <input type="hidden" name="quickcreate" value="0">
                            <input type="checkbox" name="quickcreate" value="1" {{ $field['quickcreate'] ? 'checked' : '' }} onchange="this.form.submit()"> Quick Create
                        </label>
                        <label class="d-inline-flex align-items-center gap-1 small mb-0">
                            <input type="hidden" name="masseditable" value="0">
                            <input type="checkbox" name="masseditable" value="1" {{ $field['masseditable'] ? 'checked' : '' }} onchange="this.form.submit()"> Mass Edit
                        </label>
                        <label class="d-inline-flex align-items-center gap-1 small mb-0">
                            <input type="hidden" name="headerfield" value="0">
                            <input type="checkbox" name="headerfield" value="1" {{ $field['headerfield'] ? 'checked' : '' }} onchange="this.form.submit()"> Header
                        </label>
                        <label class="d-inline-flex align-items-center gap-1 small mb-0">
                            <input type="hidden" name="summaryfield" value="0">
                            <input type="checkbox" name="summaryfield" value="1" {{ $field['summaryfield'] ? 'checked' : '' }} onchange="this.form.submit()"> Summary
                        </label>
                        @if ($field['defaultvalue'] === '' || $field['defaultvalue'] === null)
                        <span class="text-muted small">Default value not set</span>
                        @endif
                    </form>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Relationships & Duplicate Prevention placeholders --}}
<div id="relationships" class="card mb-4">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-link-45deg" style="font-size: 2rem; opacity: .5;"></i>
        <p class="mb-0 mt-2">Relationships configuration coming soon.</p>
    </div>
</div>
<div id="duplicate-prevention" class="card mb-4">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-shield-check" style="font-size: 2rem; opacity: .5;"></i>
        <p class="mb-0 mt-2">Duplicate prevention rules coming soon.</p>
    </div>
</div>
@else
<div class="card">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-layout-text-sidebar" style="font-size: 3rem; opacity: .4;"></i>
        <h5 class="mt-3 mb-2">Select a module</h5>
        <p class="mb-0">Choose a module from the dropdown above to edit its layout and field options.</p>
    </div>
</div>
@endif

<style>
.field-options-form label { cursor: pointer; }
.field-options-form input[type="checkbox"] { cursor: pointer; }
</style>
<script>
document.getElementById('moduleSelect')?.addEventListener('change', function() {
    const v = this.value;
    if (v) {
        window.location.href = '{{ url("/settings/layout-editor/module") }}/' + v;
    } else {
        window.location.href = '{{ route("settings.layout-editor") }}';
    }
});
</script>
@endsection
