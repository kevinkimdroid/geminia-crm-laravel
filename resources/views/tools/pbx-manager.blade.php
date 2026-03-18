@extends('layouts.app')

@section('title', 'PBX Manager')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">Call Logs</h1>
        <p class="page-subtitle mb-0 text-muted">View call history and recordings</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @auth
        <button type="button" class="btn btn-outline-secondary btn-sm pbx-claim-latest-btn" title="I received the most recent unclaimed call">
            <i class="bi bi-person-check me-1"></i>I received the last call
        </button>
        @endauth
        @if($pbxCanCall ?? false)
        <button type="button" class="btn btn-success btn-sm pbx-make-call-btn" data-number="" data-customer="">
            <i class="bi bi-telephone-outbound-fill me-1"></i>Make Call
        </button>
        @endif
        @if(($pbxSource ?? '') !== 'vtiger')
        <form action="{{ route('tools.pbx-manager.fetch') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-download me-1"></i>Fetch Calls
            </button>
        </form>
        @else
        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Connected to CRM</span>
        @endif
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    {{-- Left sidebar --}}
    <div class="col-lg-3">
        <div class="card pbx-sidebar-card">
            <div class="card-body p-3">
                <h6 class="text-uppercase small fw-semibold text-muted mb-2">View</h6>
                <div class="list-group list-group-flush">
                    <a href="{{ route('tools.pbx-manager') }}" class="list-group-item list-group-item-action py-2 rounded {{ !$currentList ? 'active' : '' }}">All</a>
                    <a href="{{ route('tools.pbx-manager', ['list' => 'Completed Calls']) }}" class="list-group-item list-group-item-action py-2 rounded {{ $currentList === 'Completed Calls' ? 'active' : '' }}">Completed</a>
                    <a href="{{ route('tools.pbx-manager', ['list' => 'No Response Calls']) }}" class="list-group-item list-group-item-action py-2 rounded {{ $currentList === 'No Response Calls' ? 'active' : '' }}">No Response</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Main table --}}
    <div class="col-lg-9">
        <div class="card pbx-table-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-bottom">
                    <form action="{{ route('tools.pbx-manager') }}" method="GET" class="d-flex align-items-center">
                        @if($currentList)<input type="hidden" name="list" value="{{ $currentList }}">@endif
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search calls..." value="{{ request('search') }}" style="width: 220px;">
                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-search"></i></button>
                    </form>
                    @if ($calls->total() > 0)
                        <span class="text-muted small">{{ $calls->firstItem() }} to {{ $calls->lastItem() }} of {{ $calls->total() }}</span>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle pbx-table mb-0">
                        <thead>
                            <tr>
                                <th class="pbx-th">Status</th>
                                <th class="pbx-th">Direction</th>
                                <th class="pbx-th">Number</th>
                                <th class="pbx-th d-none d-lg-table-cell">Reason</th>
                                <th class="pbx-th d-none d-xl-table-cell">Customer</th>
                                <th class="pbx-th">User</th>
                                <th class="pbx-th">Recording</th>
                                <th class="pbx-th">Duration</th>
                                <th class="pbx-th">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($calls as $call)
                                <tr>
                                    <td>
                                        <span class="badge pbx-badge pbx-badge-{{ Str::slug($call->call_status ?? '') }}">
                                            {{ $call->call_status ?? '—' }}
                                        </span>
                                    </td>
                                    <td>{{ $call->direction ?? '—' }}</td>
                                    <td>
                                        @if(!empty($call->customer_number))
                                        <a href="tel:{{ tel_href($call->customer_number) }}" class="btn btn-sm btn-link p-0 text-primary text-decoration-none" title="Call (opens MicroSIP)">
                                            <i class="bi bi-telephone-outbound me-1"></i>
                                        </a>
                                        @endif
                                        {{ $call->customer_number ?? '—' }}
                                    </td>
                                    <td class="text-muted small d-none d-lg-table-cell">{{ Str::limit($call->reason_for_calling ?? '—', 20) }}</td>
                                    <td class="d-none d-xl-table-cell">{{ Str::limit($call->customer_name ?? '—', 15) }}</td>
                                    <td>
                                        <span class="pbx-user-display">{{ $call->user_name ?? '—' }}</span>
                                        @auth
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted pbx-claim-btn" data-call-id="{{ $call->id }}" data-source="{{ $pbxSource ?? 'vtiger' }}" title="I received this call">
                                            <i class="bi bi-person-plus"></i>
                                        </button>
                                        @endauth
                                    </td>
                                    <td>
                                        @php $hasDuration = ($call->duration_sec ?? 0) > 0; @endphp
                                        @if($hasDuration && (($call->from_vtiger ?? false) && !empty($call->id)))
                                            <button type="button" class="btn btn-sm btn-outline-secondary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording.vtiger', $call->id) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ optional($call->start_time)->format('d/m H:i') ?: '' }}">
                                                <i class="bi bi-play-fill me-1"></i>Listen
                                            </button>
                                        @elseif($hasDuration && !empty($call->recording_url))
                                            <button type="button" class="btn btn-sm btn-outline-secondary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording', $call) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ optional($call->start_time)->format('d/m H:i') ?: '' }}">
                                                <i class="bi bi-play-fill me-1"></i>Listen
                                            </button>
                                        @elseif($hasDuration && method_exists($call, 'hasRecording') && $call->hasRecording())
                                            <button type="button" class="btn btn-sm btn-outline-secondary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording', $call) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ optional($call->start_time)->format('d/m H:i') ?: '' }}">
                                                <i class="bi bi-play-fill me-1"></i>Listen
                                            </button>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $call->duration_sec ?? 0 }}</td>
                                    <td class="text-nowrap">{{ optional($call->start_time)->format('d-m-Y h:i A') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        No calls yet.
                                @if(($pbxSource ?? '') === 'vtiger')
                                    No call records in the old CRM.
                                @else
                                    Click "Fetch Calls" to pull from your PBX, or ensure <code>vtiger_pbxmanager_gateway</code> is configured in the old CRM.
                                @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($calls->hasPages())
                    <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted small">{{ $calls->firstItem() }} to {{ $calls->lastItem() }} of {{ $calls->total() }}</span>
                        {{ $calls->withQueryString()->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.pbx-sidebar-card { border-radius: 10px; border: 1px solid #e9ecef; }
.pbx-table-card { border-radius: 10px; border: 1px solid #e9ecef; overflow: hidden; }
.pbx-th { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; background: #f8fafc; padding: 0.65rem 1rem; border-bottom: 1px solid #e5e7eb; }
.pbx-table td { padding: 0.65rem 1rem; vertical-align: middle; font-size: .875rem; }
.pbx-table tbody tr { border-bottom: 1px solid #f1f5f9; }
.pbx-table tbody tr:hover { background: #f8fafc; }
.pbx-badge { font-size: .65rem; font-weight: 500; padding: 0.25em 0.5em; }
.pbx-badge-completed { background: #dcfce7; color: #166534; }
.pbx-badge-busy, .pbx-badge-no-response, .pbx-badge-no-answer { background: #fef3c7; color: #b45309; }
.pbx-claim-btn:hover { color: var(--success, #059669) !important; }
</style>

{{-- Make Call modal is in layout (partials.pbx-tel-handler) for use app-wide including tel: links --}}

{{-- Recording player modal --}}
<div class="modal fade" id="pbxRecordingModal" tabindex="-1" aria-labelledby="pbxRecordingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pbxRecordingModalLabel">
                    <i class="bi bi-mic-fill me-2"></i>Call Recording
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="pbxRecordingInfo">—</p>
                <audio id="pbxRecordingAudio" controls preload="metadata" class="w-100" style="height: 48px;">
                    Your browser does not support the audio element.
                </audio>
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>Click play to listen. Recording loads from the PBX server.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.pbx-play-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const url = this.dataset.recordingUrl;
        const info = this.dataset.callInfo || '—';
        if (!url) return;

        const modal = new bootstrap.Modal(document.getElementById('pbxRecordingModal'));
        const audio = document.getElementById('pbxRecordingAudio');
        document.getElementById('pbxRecordingInfo').textContent = info;

        audio.pause();
        audio.src = url;
        audio.load();
        modal.show();

        audio.addEventListener('canplaythrough', () => audio.play(), { once: true });
        audio.addEventListener('error', () => {
            console.error('Recording failed to load');
            alert('Could not load recording. The file may be missing or the PBX server may be unreachable.');
        });
    });
});

document.getElementById('pbxRecordingModal')?.addEventListener('hidden.bs.modal', function() {
    const audio = document.getElementById('pbxRecordingAudio');
    if (audio) {
        audio.pause();
        audio.removeAttribute('src');
    }
});

// "I received this call" — claim specific call as received by logged-in user
document.querySelectorAll('.pbx-claim-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const callId = this.dataset.callId;
        const source = this.dataset.source || 'vtiger';
        const row = this.closest('tr');
        const userDisplay = row?.querySelector('.pbx-user-display');
        if (!callId || !row) return;

        if (this.disabled) return;
        this.disabled = true;
        this.title = 'Saving...';

        fetch('{{ route("tools.pbx-manager.claim") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
            },
            body: JSON.stringify({ call_id: parseInt(callId, 10), source: source })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && userDisplay) {
                userDisplay.textContent = data.user_name || 'You';
            }
            if (data.message) {
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                alert.style.zIndex = '9999';
                alert.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.body.appendChild(alert);
                setTimeout(() => alert.remove(), 3000);
            }
        })
        .catch(() => {
            this.disabled = false;
            this.title = 'I received this call';
            alert('Could not save. Please try again.');
        })
        .finally(() => {
            this.disabled = false;
            this.title = 'I received this call';
        });
    });
});

// "I received the last call" — session-based, one-click for most recent unclaimed
document.querySelector('.pbx-claim-latest-btn')?.addEventListener('click', function() {
    if (this.disabled) return;
    this.disabled = true;
    const originalHtml = this.innerHTML;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch('{{ route("tools.pbx-manager.claim-latest") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
        },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Could not claim call.');
        }
    })
    .catch(() => {
        alert('Could not save. Please try again.');
    })
    .finally(() => {
        this.disabled = false;
        this.innerHTML = originalHtml;
    });
});
</script>
@endsection
