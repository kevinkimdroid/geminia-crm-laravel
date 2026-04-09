@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<div class="page-header">
    <nav class="breadcrumb-nav mb-2">
        <a href="{{ route('settings.crm') }}" class="text-muted">Settings</a>
        <span class="mx-2 text-muted">/</span>
        <a href="{{ route('settings.crm') }}?section=users" class="text-muted">User Management</a>
        <span class="mx-2 text-muted">/</span>
        <span class="text-dark fw-semibold">Add User</span>
    </nav>
    <h1 class="page-title">Add User</h1>
    <p class="page-subtitle">Create a CRM sign-in (vTiger user) and assign a role. A password setup link is emailed to the address below.</p>
</div>

<div class="row">
    <div class="col-lg-6 col-xl-5">
        <div class="card p-4">
            <form method="POST" action="{{ route('settings.users.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="user_name" class="form-control" value="{{ old('user_name') }}" required autocomplete="username" autocapitalize="none">
                    <small class="text-muted">Used to sign in. Letters, numbers, dot, underscore, or hyphen; must start with a letter or number.</small>
                    @error('user_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                        @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                        @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email1" class="form-control" value="{{ old('email1') }}" required autocomplete="email">
                    <small class="text-muted">They will receive “Set your password” with a secure link (same flow as Reset on the user list).</small>
                    @error('email1')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <option value="">— Select role —</option>
                        @foreach ($roles ?? [] as $r)
                            <option value="{{ $r->roleid }}" {{ old('role_id') == $r->roleid ? 'selected' : '' }}>{{ $r->rolename }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="">— Not set —</option>
                        @foreach($departmentsList ?? [] as $d)
                            <option value="{{ $d }}" {{ old('department') === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                    @error('department')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background:var(--geminia-primary);color:#fff;">
                        <i class="bi bi-person-plus me-1"></i>Create user
                    </button>
                    <a href="{{ route('settings.crm') }}?section=users" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
