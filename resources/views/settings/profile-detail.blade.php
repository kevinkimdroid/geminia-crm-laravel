@extends('layouts.app')

@section('title', 'Profile view — ' . ($profile->profilename ?? ''))

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('settings.crm') }}">Settings</a></li>
                <li class="breadcrumb-item"><a href="{{ route('profiles.index') }}">Profiles</a></li>
                <li class="breadcrumb-item active">{{ $profile->profilename ?? 'Profile' }}</li>
            </ol>
        </nav>
        <h1 class="page-title">Profile view</h1>
    </div>
    <a href="{{ route('profiles.index') }}" class="btn btn-outline-secondary">Back to Profiles</a>
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

<form action="{{ route('profiles.update', $profile->profileid) }}" method="POST">
    @csrf
    @method('PUT')

    {{-- Profile info --}}
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="fw-bold mb-2">Profile name</h5>
                    <input type="text" name="profilename" class="form-control" value="{{ old('profilename', $profile->profilename ?? '') }}" maxlength="100">
                </div>
                <button type="submit" class="btn btn-primary-custom">Save</button>
            </div>
            <div>
                <h6 class="fw-bold mb-2">Description</h6>
                <input type="text" name="description" class="form-control" value="{{ old('description', $profile->description ?? '') }}" placeholder="e.g. Marketing Department" maxlength="255">
            </div>
        </div>
    </div>

    {{-- Modules --}}
    <div class="card mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4">Modules</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle profile-modules-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th class="text-center">View</th>
                            <th class="text-center">Create</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($moduleList ?? [] as $mod)
                        <tr>
                            <td><strong>{{ $mod['label'] }}</strong></td>
                            <td class="text-center">
                                <input type="hidden" name="modules[{{ $mod['tabid'] }}][view]" value="0">
                                <input type="checkbox" name="modules[{{ $mod['tabid'] }}][view]" value="1" {{ $mod['view'] ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="modules[{{ $mod['tabid'] }}][create]" value="0">
                                <input type="checkbox" name="modules[{{ $mod['tabid'] }}][create]" value="1" {{ $mod['create'] ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="modules[{{ $mod['tabid'] }}][edit]" value="0">
                                <input type="checkbox" name="modules[{{ $mod['tabid'] }}][edit]" value="1" {{ $mod['edit'] ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="modules[{{ $mod['tabid'] }}][delete]" value="0">
                                <input type="checkbox" name="modules[{{ $mod['tabid'] }}][delete]" value="1" {{ $mod['delete'] ? 'checked' : '' }}>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Field and Tool Privileges --}}
    <div class="card mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Field and Tool Privileges</h5>

            {{-- Legend --}}
            <div class="d-flex flex-wrap gap-4 mb-4">
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="profile-legend-dot profile-legend-invisible" title="Invisible"></span> Invisible
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="profile-legend-dot profile-legend-readonly" title="Read only"></span> Read only
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="profile-legend-dot profile-legend-write" title="Write"></span> Write
                </span>
            </div>

            {{-- Fields (grouped by module) --}}
            @foreach ($moduleList ?? [] as $mod)
                @if (!empty($mod['fields']))
                <div class="mb-4">
                    <h6 class="fw-semibold mb-2">Fields — {{ $mod['label'] }}</h6>
                    <div class="row g-2">
                        @foreach ($mod['fields'] as $field)
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="profile-field-label">{{ $field['label'] }}</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="fields[{{ $mod['tabid'] }}_{{ $field['fieldid'] }}]" value="invisible" id="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_inv" {{ ($field['access'] ?? '') === 'invisible' ? 'checked' : '' }}>
                                    <label for="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_inv" class="btn btn-outline-secondary" style="width:28px;height:28px;border-radius:50%;padding:0;background:#1e293b;" title="Invisible"></label>
                                    <input type="radio" class="btn-check" name="fields[{{ $mod['tabid'] }}_{{ $field['fieldid'] }}]" value="readonly" id="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_ro" {{ ($field['access'] ?? '') === 'readonly' ? 'checked' : '' }}>
                                    <label for="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_ro" class="btn btn-outline-secondary profile-legend-readonly" style="width:28px;height:28px;border-radius:50%;padding:0;background:#f59e0b;" title="Read only"></label>
                                    <input type="radio" class="btn-check" name="fields[{{ $mod['tabid'] }}_{{ $field['fieldid'] }}]" value="write" id="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_w" {{ ($field['access'] ?? 'write') === 'write' ? 'checked' : '' }}>
                                    <label for="f{{ $mod['tabid'] }}_{{ $field['fieldid'] }}_w" class="btn btn-outline-secondary profile-legend-write" style="width:28px;height:28px;border-radius:50%;padding:0;background:#eab308;" title="Write"></label>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Tools --}}
            <h6 class="fw-semibold mb-2 mt-4">Tools</h6>
            <div class="d-flex flex-wrap gap-4">
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="hidden" name="tools[Import]" value="0">
                    <input type="checkbox" name="tools[Import]" value="1" {{ ($tools['Import'] ?? false) ? 'checked' : '' }}>
                    Import
                </label>
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="hidden" name="tools[Export]" value="0">
                    <input type="checkbox" name="tools[Export]" value="1" {{ ($tools['Export'] ?? false) ? 'checked' : '' }}>
                    Export
                </label>
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="hidden" name="tools[DuplicatesHandling]" value="0">
                    <input type="checkbox" name="tools[DuplicatesHandling]" value="1" {{ ($tools['DuplicatesHandling'] ?? false) ? 'checked' : '' }}>
                    DuplicatesHandling
                </label>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary-custom">Save Profile Permissions</button>
    </div>
</form>

<style>
.profile-modules-table th, .profile-modules-table td { vertical-align: middle; }
.profile-legend-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; }
.profile-legend-invisible { background: #1e293b; }
.profile-legend-readonly { background: #f59e0b; }
.profile-legend-write { background: #eab308; }
.profile-field-label { font-size: 0.9rem; min-width: 120px; }
.profile-radio-label { cursor: pointer; font-size: 1rem; padding: 0.2rem 0.5rem; }
.profile-radio-readonly { color: #f59e0b; }
.profile-radio-write { color: #eab308; }
</style>
@endsection
