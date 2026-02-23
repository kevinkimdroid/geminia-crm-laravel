@extends('layouts.app')

@section('title', 'Profiles')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('settings.crm') }}">Settings</a></li>
                <li class="breadcrumb-item active">Profiles</li>
            </ol>
        </nav>
        <h1 class="page-title">Profiles</h1>
        <p class="page-subtitle">Manage user profiles and permissions.</p>
    </div>
</div>

<div class="row g-4">
    @forelse ($profiles ?? [] as $profile)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm profile-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold">{{ $profile->profilename }}</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary">{{ $profile->roles_count ?? 0 }} role(s)</span>
                </div>
                @if ($profile->description)
                    <p class="text-muted small mb-3">{{ Str::limit($profile->description, 60) }}</p>
                @endif
                <a href="{{ route('profiles.show', $profile->profileid) }}" class="btn btn-primary-custom btn-sm w-100">View Profile</a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-badge text-muted" style="font-size: 3rem;"></i>
                <h6 class="mt-3 mb-2">No profiles found</h6>
                <p class="text-muted mb-0">Profiles are managed in the Vtiger database.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
