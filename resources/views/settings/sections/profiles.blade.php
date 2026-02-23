<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Profiles</h5>
        <p class="text-muted small mb-0">Manage user profiles and permissions.</p>
    </div>
    <a href="{{ route('profiles.index') }}" class="btn btn-primary-custom btn-sm">View All Profiles</a>
</div>

@php
    $profiles = \App\Models\VtigerProfile::on('vtiger')->orderBy('profilename')->withCount('roles')->get();
@endphp

<div class="row g-4">
    @forelse ($profiles as $profile)
    <div class="col-md-6 col-lg-4">
        <div class="app-card h-100 overflow-hidden">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold">{{ $profile->profilename }}</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary">{{ $profile->roles_count }} role(s)</span>
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
        <div class="app-card overflow-hidden">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-badge text-muted" style="font-size: 3rem;"></i>
                <h6 class="mt-3 mb-2">No profiles found</h6>
                <p class="text-muted mb-0">Profiles are managed in the Vtiger database.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
