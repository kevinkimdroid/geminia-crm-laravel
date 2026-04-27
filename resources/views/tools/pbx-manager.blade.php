@extends('layouts.app')

@section('title', 'PBX Manager')

@section('content')
<div class="pbx-page">
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

    {{-- Main card - unified professional layout --}}
    <div class="pbx-card">
        {{-- Card header: title + actions --}}
        <div class="pbx-card-header">
            <div class="pbx-card-title-group">
                <h1 class="pbx-card-title">Call Logs</h1>
                <p class="pbx-card-subtitle">View call history and recordings</p>
            </div>
            <div class="pbx-card-actions">
                @auth
                <button type="button" class="pbx-btn pbx-btn-ghost pbx-claim-latest-btn" title="I received the most recent unclaimed call">
                    <i class="bi bi-person-check"></i>I received the last call
                </button>
                @endauth
                @if($pbxCanCall ?? false)
                <button type="button" class="pbx-btn pbx-btn-primary pbx-make-call-btn" data-number="" data-customer="">
                    <i class="bi bi-telephone-outbound-fill"></i>Make Call
                </button>
                @endif
                @if(($pbxSource ?? '') !== 'vtiger')
                <form action="{{ route('tools.pbx-manager.fetch') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="pbx-btn pbx-btn-ghost">
                        <i class="bi bi-download"></i>Fetch Calls
                    </button>
                </form>
                @else
                <span class="pbx-status-indicator">
                    <span class="pbx-status-dot"></span>Connected to CRM
                </span>
                @endif
            </div>
        </div>

        {{-- Filters + Search toolbar --}}
        <div class="pbx-toolbar">
            <nav class="pbx-filters">
                <a href="{{ route('tools.pbx-manager') }}" class="pbx-filter-tab {{ !$currentList ? 'active' : '' }}">All</a>
                <a href="{{ route('tools.pbx-manager', ['list' => 'Received Calls']) }}" class="pbx-filter-tab {{ $currentList === 'Received Calls' ? 'active' : '' }}">Received</a>
                <a href="{{ route('tools.pbx-manager', ['list' => 'Completed Calls']) }}" class="pbx-filter-tab {{ $currentList === 'Completed Calls' ? 'active' : '' }}">Completed</a>
                <a href="{{ route('tools.pbx-manager', ['list' => 'No Response Calls']) }}" class="pbx-filter-tab {{ $currentList === 'No Response Calls' ? 'active' : '' }}">No Response</a>
            </nav>
            <form action="{{ route('tools.pbx-manager') }}" method="GET" class="pbx-search">
                @if($currentList)<input type="hidden" name="list" value="{{ $currentList }}">@endif
                <div class="pbx-search-inner">
                    <i class="bi bi-search pbx-search-icon"></i>
                    <input type="text" name="search" class="pbx-search-input" placeholder="Search calls..." value="{{ request('search') }}">
                </div>
                <button type="submit" class="pbx-btn pbx-btn-ghost pbx-search-submit"><i class="bi bi-search"></i></button>
            </form>
            @if ($calls->total() > 0)
                <span class="pbx-result-count">{{ $calls->firstItem() }}-{{ $calls->lastItem() }} of {{ number_format($calls->total()) }}</span>
            @endif
        </div>

        {{-- Table --}}
        <div class="table-responsive pbx-table-wrap">
            <table class="pbx-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Direction</th>
                        <th>Number</th>
                        <th class="d-none d-lg-table-cell">Reason</th>
                        <th class="d-none d-xl-table-cell">Customer</th>
                        <th>User</th>
                        <th>Recording</th>
                        <th>Duration</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                            @forelse ($calls as $call)
                                @php
                                    $loggedInName = auth()->check()
                                        ? trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')))
                                        : '';
                                    $rawNumber = trim((string) ($call->customer_number ?? ''));
                                    $displayNumber = $rawNumber;
                                    if (preg_match('/^00254(\d{9})$/', $rawNumber, $m)) {
                                        $displayNumber = '0' . $m[1];
                                    } elseif (preg_match('/^254(\d{9})$/', $rawNumber, $m)) {
                                        $displayNumber = '0' . $m[1];
                                    }
                                    $receivedByMe = auth()->check() && !empty($call->received_by_user_id) && (int) $call->received_by_user_id === (int) auth()->id();
                                    $rawStatus = strtolower(trim((string) ($call->call_status ?? '')));
                                    $displayStatus = $rawStatus !== '' ? $rawStatus : 'unknown';
                                    $rawDuration = (int) ($call->duration_sec ?? 0);
                                    if ($displayStatus === 'ringing' && $rawDuration > 0) {
                                        $displayStatus = 'completed';
                                    }
                                    $rawReason = trim((string) ($call->reason_for_calling ?? ''));
                                    $displayReason = $rawReason !== '' ? $rawReason : (($displayStatus === 'completed') ? 'Inbound call' : 'Awaiting sync');
                                    $rawCustomer = trim((string) ($call->customer_name ?? ''));
                                    $displayCustomer = $rawCustomer !== '' ? $rawCustomer : ($displayNumber !== '' ? $displayNumber : 'Unknown');
                                    $rawUser = trim((string) ($call->user_name ?? ''));
                                    $displayUser = $rawUser !== '' ? $rawUser : 'Unassigned';
                                    $userLooksLikeMe = auth()->check() && $loggedInName !== '' && strcasecmp($displayUser, $loggedInName) === 0;
                                    if (($receivedByMe || $userLooksLikeMe) && in_array($displayStatus, ['ringing', 'missed'], true)) {
                                        $displayStatus = 'received';
                                    }
                                    if (($receivedByMe || $userLooksLikeMe) && ($rawReason === '' || $displayReason === 'Awaiting sync')) {
                                        $displayReason = 'Received by ' . ($loggedInName !== '' ? $loggedInName : 'you');
                                    }
                                    $displayDuration = $rawDuration;
                                    $clientSearchValue = preg_replace('/\s+/', '', $displayNumber !== '' ? $displayNumber : $rawNumber);
                                @endphp
                                <tr>
                                    <td>
                                        <span class="pbx-badge pbx-badge-{{ Str::slug($displayStatus) }}">
                                            {{ $displayStatus }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="pbx-direction pbx-direction-{{ strtolower($call->direction ?? 'unknown') }}">
                                            {{ $call->direction ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($call->customer_number))
                                        <a href="tel:{{ tel_href($call->customer_number) }}" class="pbx-number-link" title="Call">
                                            <i class="bi bi-telephone-outbound"></i>
                                        </a>
                                        @endif
                                        <span class="pbx-number">{{ $displayNumber !== '' ? $displayNumber : '—' }}</span>
                                    </td>
                                    <td class="d-none d-lg-table-cell pbx-cell-muted">{{ Str::limit($displayReason, 24) }}</td>
                                    <td class="d-none d-xl-table-cell">
                                        @if($clientSearchValue !== '')
                                            <a href="{{ route('support.serve-client', ['search' => $clientSearchValue]) }}" class="text-decoration-none">
                                                {{ Str::limit($displayCustomer, 22) }}
                                            </a>
                                        @else
                                            {{ Str::limit($displayCustomer, 22) }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="pbx-user-display">{{ $displayUser }}</span>
                                        @if($receivedByMe || $userLooksLikeMe)
                                            <span class="badge bg-success-subtle text-success-emphasis border ms-1">You</span>
                                        @endif
                                        @auth
                                        <button type="button" class="pbx-claim-btn" data-call-id="{{ $call->id }}" data-source="{{ $pbxSource ?? 'vtiger' }}" title="I received this call">
                                            <i class="bi bi-person-plus"></i>
                                        </button>
                                        @endauth
                                    </td>
                                    <td>
                                        @php
                                            $recordingRoute = null;
                                            if (($call->from_vtiger ?? false) && !empty($call->id)) {
                                                $recordingRoute = route('tools.pbx-manager.recording.vtiger', $call->id);
                                            } elseif (method_exists($call, 'hasRecording') && $call->hasRecording()) {
                                                $recordingRoute = route('tools.pbx-manager.recording', $call);
                                            } elseif (!empty($call->recording_url)) {
                                                $recordingRoute = route('tools.pbx-manager.recording', $call);
                                            }
                                        @endphp
                                        @if($recordingRoute)
                                            <div class="pbx-recording-cell" data-recording-url="{{ $recordingRoute }}">
                                                <audio class="pbx-inline-audio" controls preload="none" title="Play recording">
                                                    <source src="{{ $recordingRoute }}" type="audio/mpeg">
                                                    Your browser does not support audio playback.
                                                </audio>
                                                <button type="button" class="pbx-play-btn pbx-play-modal-btn" data-recording-url="{{ $recordingRoute }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ optional($call->start_time)->format('d/m H:i') ?: '' }}" title="Open in player">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </button>
                                            </div>
                                        @else
                                            @if($displayStatus === 'completed')
                                                <span class="pbx-cell-muted">Pending recording</span>
                                            @else
                                                <span class="pbx-cell-muted">—</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td><span class="pbx-duration">{{ $displayDuration > 0 ? $displayDuration . 's' : '—' }}</span></td>
                                    <td class="pbx-time">{{ optional($call->start_time)->format('d M Y, h:i A') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="pbx-empty-state">
                                        <div class="pbx-empty-icon"><i class="bi bi-telephone-x"></i></div>
                                        <p class="pbx-empty-title">No calls yet</p>
                                        <p class="pbx-empty-text">
                                            @if(($pbxSource ?? '') === 'vtiger')
                                                No call records in the old CRM.
                                            @else
                                                Click "Fetch Calls" to pull from your PBX, or ensure <code>vtiger_pbxmanager_gateway</code> is configured in the old CRM.
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                </tbody>
            </table>
        </div>

        @if ($calls->hasPages())
            <div class="pbx-card-footer">
                <span class="pbx-result-count">{{ $calls->firstItem() }}-{{ $calls->lastItem() }} of {{ number_format($calls->total()) }}</span>
                {{ $calls->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<style>
/* Call Logs – Professional layout */
.pbx-page { padding-bottom: 2rem; }

.pbx-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--geminia-border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

/* Card header */
.pbx-card-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 1.75rem;
    border-bottom: 1px solid var(--geminia-border);
}
.pbx-card-title { font-size: 1.5rem; font-weight: 700; color: var(--geminia-text); margin: 0 0 0.15rem; }
.pbx-card-subtitle { font-size: 0.875rem; color: var(--geminia-text-muted); margin: 0; }
.pbx-card-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}
.pbx-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.pbx-btn-primary {
    background: var(--geminia-primary);
    color: #fff;
}
.pbx-btn-primary:hover { background: var(--geminia-primary-dark); }
.pbx-btn-ghost {
    background: transparent;
    color: var(--geminia-text-muted);
}
.pbx-btn-ghost:hover { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.pbx-status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: #059669;
    background: rgba(5, 150, 105, 0.1);
    border-radius: 6px;
}
.pbx-status-dot {
    width: 6px; height: 6px;
    background: #059669;
    border-radius: 50%;
}
.pbx-status-indicator .pbx-status-dot { animation: pbx-pulse 2s ease-in-out infinite; }
@keyframes pbx-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* Toolbar: filters + search */
.pbx-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.75rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--geminia-border);
}
.pbx-filters {
    display: flex;
    gap: 0.25rem;
}
.pbx-filter-tab {
    display: inline-block;
    padding: 0.5rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--geminia-text-muted);
    text-decoration: none;
    border-radius: 8px;
    border: 1px solid var(--geminia-border);
    background: #fff;
    transition: all 0.2s;
}
.pbx-filter-tab:hover {
    color: var(--geminia-primary);
    border-color: var(--geminia-primary);
    background: var(--geminia-primary-muted);
}
.pbx-filter-tab.active {
    color: #fff;
    background: var(--geminia-primary);
    border-color: var(--geminia-primary);
}
.pbx-filter-tab.active:hover {
    color: #fff;
    background: var(--geminia-primary-dark);
    border-color: var(--geminia-primary-dark);
}
.pbx-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}
.pbx-search-inner {
    position: relative;
    display: flex;
    align-items: center;
}
.pbx-search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--geminia-text-muted);
    font-size: 0.95rem;
}
.pbx-search-input {
    width: 240px;
    height: 36px;
    padding: 0 1rem 0 2.25rem;
    border: 1px solid var(--geminia-border);
    border-radius: 6px;
    font-size: 0.875rem;
    background: #fff;
}
.pbx-search-input:focus { outline: none; border-color: var(--geminia-primary); }
.pbx-search-submit { padding: 0.4rem 0.6rem; }
.pbx-result-count {
    font-size: 0.8rem;
    color: var(--geminia-text-muted);
    font-weight: 500;
}

/* Table */
.pbx-table-wrap { background: #fff; }
.pbx-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.pbx-table th {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--geminia-text-muted);
    background: #fafbfc;
    padding: 0.85rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid var(--geminia-border);
}
.pbx-table td {
    padding: 0.85rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.pbx-table tbody tr:hover { background: #fafbfc; }
.pbx-table tbody tr:last-child td { border-bottom: none; }

/* Badges & cells */
.pbx-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.3em 0.65em;
    border-radius: 4px;
}
.pbx-badge-completed { background: #dcfce7; color: #166534; }
.pbx-badge-received { background: #dcfce7; color: #166534; }
.pbx-badge-missed, .pbx-badge-no-answer, .pbx-badge-no-response, .pbx-badge-busy { background: #fef3c7; color: #b45309; }
.pbx-badge-ringing { background: #dbeafe; color: #1d4ed8; }
.pbx-badge-unknown { background: #e2e8f0; color: #334155; }
.pbx-direction { font-size: 0.8rem; font-weight: 500; text-transform: capitalize; }
.pbx-direction-inbound { color: #2563eb; }
.pbx-direction-outbound { color: #059669; }
.pbx-number-link { display: inline-flex; margin-right: 0.35rem; color: var(--geminia-primary); text-decoration: none; }
.pbx-number-link:hover { color: var(--geminia-primary-dark); }
.pbx-number { font-family: ui-monospace, monospace; font-size: 0.85rem; }
.pbx-cell-muted { color: var(--geminia-text-muted); font-size: 0.85rem; }
.pbx-duration { font-weight: 500; }
.pbx-time { white-space: nowrap; color: var(--geminia-text-muted); font-size: 0.85rem; }
.pbx-claim-btn {
    display: inline-flex; align-items: center; padding: 0.2rem; margin-left: 0.25rem;
    background: none; border: none; color: var(--geminia-text-muted); cursor: pointer; border-radius: 4px;
}
.pbx-claim-btn:hover { color: #059669; background: rgba(5, 150, 105, 0.1); }
.pbx-play-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 500;
    background: var(--geminia-primary-muted); color: var(--geminia-primary);
    border: none; border-radius: 4px; cursor: pointer;
}
.pbx-play-btn:hover { background: rgba(26, 70, 138, 0.12); }
.pbx-recording-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.pbx-inline-audio {
    min-width: 120px;
    max-width: 180px;
    height: 32px;
}
.pbx-inline-audio::-webkit-media-controls-panel { background: var(--geminia-primary-muted); }
.pbx-play-modal-btn {
    flex-shrink: 0;
    padding: 0.3rem 0.5rem;
    font-size: 0.75rem;
}

/* Empty state */
.pbx-empty-state { text-align: center; padding: 3.5rem 2rem !important; }
.pbx-empty-icon { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.75rem; }
.pbx-empty-title { font-weight: 600; font-size: 1rem; margin: 0 0 0.4rem; color: var(--geminia-text); }
.pbx-empty-text { font-size: 0.875rem; color: var(--geminia-text-muted); margin: 0; }

/* Footer */
.pbx-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.9rem 1.75rem;
    background: #fafbfc;
    border-top: 1px solid var(--geminia-border);
}
.pbx-card-footer .pagination { margin: 0; }

@media (max-width: 768px) {
    .pbx-card-header, .pbx-toolbar { padding: 1rem 1.25rem; }
    .pbx-toolbar { flex-direction: column; align-items: stretch; }
    .pbx-search { margin-left: 0; }
    .pbx-search-input { width: 100%; }
}
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
document.querySelectorAll('.pbx-inline-audio').forEach(audio => {
    audio.addEventListener('error', function() {
        const cell = this.closest('.pbx-recording-cell');
        if (cell && !cell.querySelector('.pbx-no-recording')) {
            cell.innerHTML = '<span class="pbx-cell-muted pbx-no-recording">No recording</span>';
        }
    });
});

document.querySelectorAll('.pbx-play-modal-btn').forEach(btn => {
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

// Auto-refresh call logs for near real-time updates.
// Keeps page stable by pausing while user is interacting with form fields/modals.
(function () {
    var REFRESH_MS = 10000;
    function shouldSkipRefresh() {
        if (document.hidden) return true;
        if (document.querySelector('.modal.show')) return true;
        var active = document.activeElement;
        if (!active) return false;
        var tag = (active.tagName || '').toUpperCase();
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || active.isContentEditable;
    }
    setInterval(function () {
        if (shouldSkipRefresh()) return;
        window.location.reload();
    }, REFRESH_MS);
})();
</script>
@endsection
