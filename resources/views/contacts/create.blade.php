@extends('layouts.app')

@section('title', 'Add Contact')

@section('content')
<div class="page-header">
    <nav class="mb-2"><a href="{{ route('contacts.index') }}" class="text-muted small">Contacts</a> / Add</nav>
    <h1 class="page-title">Add Contact</h1>
    <p class="page-subtitle">Create a new contact.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card p-4" style="max-width: 500px;">
    <form method="POST" action="{{ route('contacts.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">First Name *</label>
            <input type="text" name="firstname" class="form-control" value="{{ old('firstname') }}" required>
            @error('firstname')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Last Name *</label>
            <input type="text" name="lastname" class="form-control" value="{{ old('lastname') }}" required>
            @error('lastname')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
        </div>
        <div class="mb-4">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}">
        </div>
        <button type="submit" class="btn btn-primary-custom">Create Contact</button>
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
</div>
@endsection
