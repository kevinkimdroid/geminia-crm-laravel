@extends('layouts.app')

@section('title', 'Edit User — ' . ($user->full_name ?? $user->user_name))

@section('content')
<div class="page-header">
    <nav class="breadcrumb-nav mb-2">
        <a href="{{ route('settings.crm') }}" class="text-muted">Settings</a>
        <span class="mx-2 text-muted">/</span>
        <a href="{{ route('settings.crm') }}?section=users" class="text-muted">User Management</a>
        <span class="mx-2 text-muted">/</span>
        <span class="text-dark fw-semibold">Edit {{ $user->full_name ?? $user->user_name }}</span>
    </nav>
    <h1 class="page-title">Edit User</h1>
    <p class="page-subtitle">Update user details.</p>
</div>

<div class="row">
    <div class="col-lg-6 col-xl-5">
        <div class="card p-4">
            <form method="POST" action="{{ route('settings.users.update', $user->id) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="{{ $user->user_name }}" readonly disabled>
                    <small class="text-muted">Username cannot be changed. Used for login.</small>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
                        @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
                        @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-4 mt-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email1" class="form-control" value="{{ old('email1', $user->email1) }}" required>
                    @error('email1')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background:var(--geminia-primary);color:#fff;">
                        <i class="bi bi-check-lg me-1"></i>Save changes
                    </button>
                    <a href="{{ route('settings.crm') }}?section=users" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
