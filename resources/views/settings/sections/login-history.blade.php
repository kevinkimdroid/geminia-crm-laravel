<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">User Login History</h5>
        <p class="text-muted small mb-0">View user sign-in and sign-out activity.</p>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <form method="GET" action="{{ route('settings.crm') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="section" value="login-history">
        <label class="mb-0 small text-muted">Filter:</label>
        <select name="filter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <option value="all" {{ ($loginFilter ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
            <option value="signed_in" {{ ($loginFilter ?? '') === 'signed_in' ? 'selected' : '' }}>Signed in</option>
            <option value="signed_off" {{ ($loginFilter ?? '') === 'signed_off' ? 'selected' : '' }}>Signed off</option>
        </select>
    </form>
    <span class="text-muted small">
        {{ min(($loginPage ?? 1) * ($loginPerPage ?? 50) - ($loginPerPage ?? 50) + 1, $loginTotal ?? 0) }}
        to {{ min(($loginPage ?? 1) * ($loginPerPage ?? 50), $loginTotal ?? 0) }} of {{ $loginTotal ?? 0 }}
    </span>
</div>

<div class="app-card overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">User Name</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">User IP Address</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Sign-in Time</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Sign-out Time</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loginRecords ?? [] as $record)
                    <tr>
                        <td class="px-4 fw-semibold">{{ trim($record->full_name ?? '') ?: $record->user_name }}</td>
                        <td class="px-4">{{ $record->user_ip ?? '—' }}</td>
                        <td class="px-4">{{ $record->login_time ? \Carbon\Carbon::parse($record->login_time)->format('d-m-Y h:i A') : '—' }}</td>
                        <td class="px-4">{{ $record->logout_time ? \Carbon\Carbon::parse($record->logout_time)->format('d-m-Y h:i A') : '---' }}</td>
                        <td class="px-4">
                            @if (strtolower($record->status ?? '') === 'signed in')
                            <span class="badge bg-success">Signed in</span>
                            @else
                            <span class="badge bg-secondary">Signed off</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-5 text-center text-muted">
                            <i class="bi bi-person-check d-block mb-2" style="font-size: 2rem; opacity: .5;"></i>
                            No login records found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if (($loginTotal ?? 0) > ($loginPerPage ?? 50))
<nav class="mt-3 d-flex justify-content-between align-items-center">
    <span class="text-muted small">Page {{ $loginPage ?? 1 }} of {{ ceil(($loginTotal ?? 0) / ($loginPerPage ?? 50)) }}</span>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item {{ ($loginPage ?? 1) <= 1 ? 'disabled' : '' }}">
            <a class="page-link" href="{{ route('settings.crm', ['section' => 'login-history', 'filter' => $loginFilter ?? 'all', 'page' => ($loginPage ?? 1) - 1]) }}">←</a>
        </li>
        <li class="page-item {{ ($loginPage ?? 1) >= ceil(($loginTotal ?? 0) / ($loginPerPage ?? 50)) ? 'disabled' : '' }}">
            <a class="page-link" href="{{ route('settings.crm', ['section' => 'login-history', 'filter' => $loginFilter ?? 'all', 'page' => ($loginPage ?? 1) + 1]) }}">→</a>
        </li>
    </ul>
</nav>
@endif
