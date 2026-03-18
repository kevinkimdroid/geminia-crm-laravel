@extends('layouts.app')

@section('title', 'Offboard User — ' . ($user->full_name ?? $user->user_name))

@section('content')
<div class="page-header">
    <nav class="breadcrumb-nav mb-2">
        <a href="{{ route('settings.crm') }}" class="text-muted">Settings</a>
        <span class="mx-2 text-muted">/</span>
        <a href="{{ route('settings.crm') }}?section=users" class="text-muted">Users</a>
        <span class="mx-2 text-muted">/</span>
        <span class="text-dark fw-semibold">Offboard {{ $user->full_name ?? $user->user_name }}</span>
    </nav>
    <h1 class="page-title">Offboard User</h1>
    <p class="page-subtitle">Reassign their records, then deactivate. Use when someone resigns or leaves the organization.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
    </div>
@endif

<div class="row">
    <div class="col-lg-7 col-xl-6">
        <div class="card p-4">
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">User</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--geminia-primary-muted);color:var(--geminia-primary)">
                        <i class="bi bi-person-fill fs-4"></i>
                    </div>
                    <div>
                        <strong class="fs-5">{{ $user->full_name ?? $user->user_name }}</strong>
                        <div class="text-muted small">{{ $user->email1 ?? '' }} · {{ $user->user_name ?? '' }}</div>
                    </div>
                </div>
            </div>

            @if ($totalRecords > 0)
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Records to reassign</h6>
                <div class="d-flex flex-wrap gap-3">
                    @foreach(['tickets' => 'Tickets', 'contacts' => 'Contacts', 'leads' => 'Leads', 'deals' => 'Deals'] as $key => $label)
                        @if(($recordCounts[$key] ?? 0) > 0)
                        <span class="badge bg-light text-dark border px-3 py-2">
                            {{ $label }}: <strong>{{ $recordCounts[$key] }}</strong>
                        </span>
                        @endif
                    @endforeach
                </div>
                <p class="text-muted small mt-2 mb-0">Choose who will receive these records.</p>
            </div>

            <form method="POST" action="{{ route('settings.users.offboard.submit', $user->id) }}">
                @csrf
                @if(request()->get('redirect'))<input type="hidden" name="redirect" value="{{ request()->get('redirect') }}">@endif
                <div class="mb-4">
                    <label class="form-label fw-semibold">Reassign to</label>
                    <select name="reassign_to" class="form-select" required>
                        <option value="">— Select a user —</option>
                        @foreach($users ?? [] as $u)
                            <option value="{{ $u->id }}" {{ old('reassign_to') == $u->id ? 'selected' : '' }}>
                                {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name }}
                            </option>
                        @endforeach
                        <option value="0" {{ old('reassign_to') === '0' || (($users ?? collect())->isEmpty()) ? 'selected' : '' }}>— Unassigned —</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-person-x-fill me-1"></i>Reassign & Deactivate
                    </button>
                    @php $backUrl = route('settings.crm') . '?section=users'; $r = request()->get('redirect'); if ($r && \Illuminate\Support\Str::startsWith($r, [url('/'), config('app.url')])) { $backUrl = $r; } @endphp
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
            @else
            <p class="text-muted mb-4">This user has no records assigned. You can deactivate directly.</p>
            <form method="POST" action="{{ route('settings.users.offboard.submit', $user->id) }}">
                @csrf
                @if(request()->get('redirect'))<input type="hidden" name="redirect" value="{{ request()->get('redirect') }}">@endif
                <input type="hidden" name="reassign_to" value="0">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-person-x-fill me-1"></i>Deactivate User
                    </button>
                    @php $backUrl = route('settings.crm') . '?section=users'; $r = request()->get('redirect'); if ($r && \Illuminate\Support\Str::startsWith($r, [url('/'), config('app.url')])) { $backUrl = $r; } @endphp
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
