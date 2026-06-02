@extends('layouts.app')

@section('title', 'Investment Maturities')

@section('content')
<nav class="mb-3">
    <a href="{{ route('support') }}" class="text-muted small text-decoration-none">Support</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Investment maturities</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="app-page-title mb-1">Investment maturities (next {{ $days }} days)</h1>
        <p class="text-muted mb-0">Review policies maturing soon and send notifications from one place.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="GET" action="{{ route('support.investment-maturities') }}" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 text-muted small">Window</label>
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="7" {{ $days === 7 ? 'selected' : '' }}>7 days</option>
                <option value="14" {{ $days === 14 ? 'selected' : '' }}>14 days</option>
                <option value="21" {{ $days === 21 ? 'selected' : '' }}>21 days</option>
                <option value="30" {{ $days === 30 ? 'selected' : '' }}>30 days</option>
            </select>
        </form>
        <form method="POST" action="{{ route('support.investment-maturities.send') }}">
            @csrf
            <input type="hidden" name="days" value="{{ $days }}">
            <button type="submit" class="btn btn-sm btn-primary">Send new notifications</button>
        </form>
        <form method="POST" action="{{ route('support.investment-maturities.send') }}">
            @csrf
            <input type="hidden" name="days" value="{{ $days }}">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="btn btn-sm btn-outline-primary">Resend full list</button>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-warning">{{ session('error') }}</div>
@endif
@if ($error)
    <div class="alert alert-danger">{{ $error }}</div>
@endif
@if (! $trackingEnabled)
    <div class="alert alert-warning">Tracking table missing. Run <code>php artisan migrate</code> to prevent duplicate sends.</div>
@endif

<div class="app-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Policy</th>
                <th>Maturity date</th>
                <th>Full name</th>
                <th>Product</th>
                <th>Email status</th>
                <th>Sent at</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                @php
                    $maturity = '—';
                    try {
                        $maturity = \Carbon\Carbon::parse((string) ($row->pol_maturity_date ?? ''))->format('d M Y');
                    } catch (\Throwable $e) {
                        $maturity = (string) ($row->pol_maturity_date ?? '—');
                    }
                @endphp
                <tr>
                    <td class="font-monospace">{{ $row->pol_policy_no ?? '—' }}</td>
                    <td>{{ $maturity }}</td>
                    <td>{{ $row->full_name ?? '—' }}</td>
                    <td>{{ $row->product ?? '—' }}</td>
                    <td>
                        @if (!empty($row->email_sent))
                            <span class="badge bg-success">Sent</span>
                        @else
                            <span class="badge bg-secondary">Pending</span>
                        @endif
                    </td>
                    <td>
                        @if (!empty($row->email_sent_at))
                            {{ \Carbon\Carbon::parse($row->email_sent_at)->format('d M Y H:i') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No investment maturities in the selected window.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

