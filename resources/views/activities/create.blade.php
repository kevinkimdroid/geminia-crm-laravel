@extends('layouts.app')

@section('title', 'Schedule ' . ($type === 'Event' ? 'Event' : 'Task'))

@section('content')
<div class="page-header">
    <nav class="mb-2">
        <a href="{{ route('activities.index') }}" class="text-muted small">Calendar</a>
        <span class="text-muted mx-1">/</span>
        <span class="text-dark">Schedule {{ $type }}</span>
    </nav>
    <h1 class="page-title">Schedule {{ $type === 'Event' ? 'Meeting / Event' : 'Task' }}</h1>
    <p class="page-subtitle">Set the date and time for your {{ strtolower($type) }}.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card" style="max-width: 560px;">
    <div class="card-body p-4">
        <form action="{{ route('activities.store') }}" method="POST">
            @csrf
            <input type="hidden" name="activitytype" value="{{ $type }}">
            <div class="mb-3">
                <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" placeholder="e.g. Client meeting" value="{{ old('subject') }}" required>
                @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_start" class="form-control" value="{{ old('date_start', date('Y-m-d')) }}" required>
                    @error('date_start')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date', date('Y-m-d')) }}">
                </div>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Start Time</label>
                    <input type="time" name="time_start" class="form-control" value="{{ old('time_start') }}" placeholder="HH:MM">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">End Time</label>
                    <input type="time" name="time_end" class="form-control" value="{{ old('time_end') }}" placeholder="HH:MM">
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label fw-semibold">Related To (Contact)</label>
                <select name="related_to" class="form-select">
                    <option value="">— None —</option>
                    @foreach ($contacts ?? [] as $c)
                    <option value="{{ $c->contactid }}" {{ old('related_to', $relatedTo ?? null) == $c->contactid ? 'selected' : '' }}>
                        {{ trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: $c->contactid }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="bi bi-calendar-check me-1"></i> Schedule {{ $type }}
                </button>
                <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
