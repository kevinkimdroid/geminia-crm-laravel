@extends('layouts.app')

@section('title', 'Reporting Lines - Work Tickets')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title mb-1">Reporting Lines</h1>
        <p class="page-subtitle mb-0">Set who each team member reports to for automatic manager assignment in Work Tickets.</p>
    </div>
    <a href="{{ route('work-tickets.index') }}" class="btn btn-outline-secondary">Back to Work Tickets</a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <form method="POST" action="{{ route('work-tickets.reporting-lines.save') }}">
            @csrf
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45%">Team Member</th>
                            <th style="width: 55%">Reports To (Manager)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $member)
                        <tr>
                            <td>{{ $member->full_name }}</td>
                            <td>
                                <select name="manager[{{ $member->id }}]" class="form-select form-select-sm">
                                    <option value="">— Not set —</option>
                                    @foreach($users as $manager)
                                        @continue((int) $manager->id === (int) $member->id)
                                        <option value="{{ $manager->id }}" {{ (int) ($reportingMap[$member->id] ?? 0) === (int) $manager->id ? 'selected' : '' }}>
                                            {{ $manager->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary-custom">
                <i class="bi bi-save me-1"></i> Save Reporting Lines
            </button>
        </form>
    </div>
</div>
@endsection
