@extends('layouts.app')

@section('title', 'Credit Life Statements')

@section('content')
<div class="page-header mb-4">
    <h1 class="page-title">Credit Life Statements</h1>
    <p class="page-subtitle mb-0">Dedicated statement dispatch workflow with period and to-date controls.</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="GET" action="{{ route('marketing.credit-life-statements') }}" class="mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small text-muted">Search contacts</label>
            <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="Name, policy number, email, or phone">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted d-block">&nbsp;</label>
            <button type="submit" class="btn btn-primary-custom">Apply</button>
        </div>
        @if (($search ?? '') !== '')
            <div class="col-auto">
                <label class="form-label small text-muted d-block">&nbsp;</label>
                <a href="{{ route('marketing.credit-life-statements') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        @endif
    </div>
</form>

<form method="POST" action="{{ route('marketing.credit-life-statements.send') }}" id="creditLifeStatementsForm" enctype="multipart/form-data">
    @csrf

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Statement period <span class="text-danger">*</span></label>
                    <input type="text" name="statement_period" class="form-control" value="{{ old('statement_period') }}" placeholder="e.g. Jan 2026 - Mar 2026">
                    @error('statement_period')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Statement to date <span class="text-danger">*</span></label>
                    <input type="date" name="statement_to_date" class="form-control" value="{{ old('statement_to_date') }}">
                    @error('statement_to_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Email subject (optional override)</label>
                    <input type="text" name="subject" class="form-control" maxlength="200" value="{{ old('subject') }}" placeholder="Defaults to: Your Credit Life statement for @{{statement_period}}">
                    @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Email message (optional override)</label>
                    <textarea name="body" class="form-control" rows="6" placeholder="Defaults to a Credit Life statement message. Placeholders supported: @{{firstname}}, @{{statement_period}}, @{{statement_to_date}}">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Statement attachment <span class="text-danger">*</span></label>
                    <input type="file" name="email_attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx">
                    <small class="text-muted d-block mt-1">This attachment is sent to every selected recipient.</small>
                    @error('email_attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="fw-semibold">Recipients</span>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clsSelectAllEmail">Select all (with email)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clsSelectNone">Clear</button>
                <span class="small text-muted align-self-center"><span id="clsCount">0</span> selected</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="sticky-top bg-light">
                        <tr>
                            <th style="width:40px"></th>
                            <th>Name</th>
                            <th>Policy</th>
                            <th>Email</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers ?? [] as $c)
                            @php
                                $cid = (int) $c->contactid;
                                $fullName = trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? ''));
                                if ($fullName === '') {
                                    $fullName = 'Contact #' . $cid;
                                }
                                $policyNo = trim((string) ($c->policy_number ?? $c->policy_no ?? ''));
                                $email = trim((string) ($c->email ?? ''));
                                $phone = trim((string) (($c->mobile ?? '') ?: ($c->phone ?? '')));
                                $hasEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input cls-check" name="contact_ids[]" value="{{ $cid }}" data-has-email="{{ $hasEmail ? '1' : '0' }}">
                                </td>
                                <td>{{ $fullName }}</td>
                                <td class="small">{{ $policyNo !== '' ? $policyNo : '—' }}</td>
                                <td><span class="{{ $hasEmail ? '' : 'text-muted' }}">{{ $email !== '' ? $email : '—' }}</span></td>
                                <td><span class="{{ $phone !== '' ? '' : 'text-muted' }}">{{ $phone !== '' ? $phone : '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No contacts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('contact_ids')<div class="px-3 py-2 text-danger small">{{ $message }}</div>@enderror
            @error('contact_ids.*')<div class="px-3 py-2 text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>

    <button type="submit" class="btn btn-primary-custom btn-lg">
        <i class="bi bi-send-fill me-1"></i> Send Credit Life statements
    </button>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checks = document.querySelectorAll('.cls-check');
    var countEl = document.getElementById('clsCount');

    function updateCount() {
        if (!countEl) return;
        countEl.textContent = document.querySelectorAll('.cls-check:checked').length;
    }

    document.getElementById('clsSelectAllEmail')?.addEventListener('click', function () {
        checks.forEach(function (cb) {
            cb.checked = cb.getAttribute('data-has-email') === '1';
        });
        updateCount();
    });

    document.getElementById('clsSelectNone')?.addEventListener('click', function () {
        checks.forEach(function (cb) { cb.checked = false; });
        updateCount();
    });

    checks.forEach(function (cb) { cb.addEventListener('change', updateCount); });
    updateCount();

    document.getElementById('creditLifeStatementsForm')?.addEventListener('submit', function (e) {
        var selected = document.querySelectorAll('.cls-check:checked').length;
        if (selected < 1) {
            e.preventDefault();
            alert('Select at least one contact.');
        }
    });
});
</script>
@endpush
@endsection
