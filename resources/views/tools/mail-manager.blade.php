@extends('layouts.app')

@section('title', 'Mail Manager')

@section('content')
<div class="page-header mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="page-title">Mail Manager</h1>
            <p class="page-subtitle mb-0">Emails from {{ $useMicrosoftGraph ?? false ? config('microsoft-graph.mailbox') . ' (Microsoft Graph)' : ($useEmailService ?? false ? config('email-service.sender') . ' (HTTP)' : config('email-service.sender', 'life@geminialife.co.ke')) }}</p>
        </div>
        <form action="{{ route('tools.mail-manager.fetch') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-download me-1"></i> Fetch Emails
            </button>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom bg-light">
            <form method="GET" action="{{ route('tools.mail-manager') }}" class="d-flex gap-2">
                <div class="input-group flex-grow-1">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search subject, from, or body..." value="{{ $search ?? '' }}">
                </div>
                <button type="submit" class="btn btn-outline-primary">Search</button>
                @if($search ?? null)
                    <a href="{{ route('tools.mail-manager') }}" class="btn btn-outline-secondary">Clear</a>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">From</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Subject</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Date</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="80"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails ?? [] as $email)
                    <tr>
                        <td class="px-4">
                            <span class="fw-semibold">{{ $email->from_name ?: $email->from_address }}</span>
                            @if($email->from_name && $email->from_address)
                                <br><small class="text-muted">{{ $email->from_address }}</small>
                            @endif
                        </td>
                        <td class="px-4">
                            <a href="{{ route('tools.mail-manager.show', $email->id) }}" class="text-decoration-none text-dark">
                                {{ Str::limit($email->subject ?? '(No subject)', 60) }}
                            </a>
                            @if($email->has_attachments)
                                <i class="bi bi-paperclip text-muted ms-1" title="Has attachments"></i>
                            @endif
                        </td>
                        <td class="px-4 text-muted small">{{ $email->date ? \Carbon\Carbon::parse($email->date)->format('M d, Y H:i') : '—' }}</td>
                        <td class="px-4">
                            <a href="{{ route('tools.mail-manager.show', $email->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            @if($search ?? null)
                                No emails match your search.
                            @else
                                No emails yet. Click "Fetch Emails" to pull emails{{ ($useMicrosoftGraph ?? false) ? ' via Microsoft Graph' : (($useEmailService ?? false) ? ' via HTTP' : ' from ' . (config('email-service.sender') ?: 'life@geminialife.co.ke')) }}.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(($total ?? 0) > 0)
        <div class="card-footer bg-transparent border-top d-flex justify-content-between align-items-center py-3 px-4">
            @php $from = ($page - 1) * $perPage + 1; $to = min($from + count($emails ?? []) - 1, $total); @endphp
            <span class="text-muted small">{{ $from }} to {{ $to }} of {{ number_format($total) }}</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('tools.mail-manager', ['page' => $page - 1, 'search' => $search]) }}"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <li class="page-item {{ $page * $perPage >= $total ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('tools.mail-manager', ['page' => $page + 1, 'search' => $search]) }}"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection
