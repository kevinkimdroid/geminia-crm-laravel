@extends('layouts.app')

@section('title', 'Edit ' . $contact->full_name)

@section('content')
<div class="page-header">
    <nav class="mb-2"><a href="{{ route('contacts.index') }}" class="text-muted small">Contacts</a> / <a href="{{ route('contacts.show', $contact->contactid) }}">{{ $contact->full_name }}</a> / Edit</nav>
    <h1 class="page-title">Edit Contact</h1>
</div>

<div class="card p-4" style="max-width: 500px;">
    <form method="POST" action="{{ route('contacts.update', $contact->contactid) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">First Name *</label>
            <input type="text" name="firstname" class="form-control" value="{{ old('firstname', $contact->firstname) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Last Name *</label>
            <input type="text" name="lastname" class="form-control" value="{{ old('lastname', $contact->lastname) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $contact->email) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone', $contact->phone) }}">
        </div>
        <div class="mb-4">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $contact->mobile) }}">
        </div>
        <button type="submit" class="btn btn-primary-custom">Update</button>
        <a href="{{ route('contacts.show', $contact->contactid) }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
</div>
@endsection
