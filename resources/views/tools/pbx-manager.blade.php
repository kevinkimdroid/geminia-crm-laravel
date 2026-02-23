@extends('layouts.app')

@section('title', 'PBX Manager')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">PBX MANAGER <span class="text-muted fw-normal">> {{ $currentList ?: 'All' }}</span></h1>
        <p class="page-subtitle mb-0">Call logs and recordings.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @if($pbxCanCall ?? false)
        <button type="button" class="btn btn-success pbx-make-call-btn" data-number="" data-customer="">
            <i class="bi bi-telephone-outbound-fill me-2"></i>Make Call
        </button>
        @endif
        @if(($pbxSource ?? '') !== 'vtiger')
        <form action="{{ route('tools.pbx-manager.fetch') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary-custom">
                <i class="bi bi-download me-2"></i>Fetch Calls
            </button>
        </form>
        @else
        <span class="badge bg-success bg-opacity-10 text-success">Using config from old CRM</span>
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
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold">LISTS</h6>
                    <button type="button" class="btn btn-sm btn-link p-0" title="Add list"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="mb-3">
                    <input type="text" class="form-control form-control-sm" placeholder="Search for List" id="listSearch">
                </div>
                <h6 class="text-uppercase small fw-bold text-muted mb-2">Shared List</h6>
                <div class="list-group list-group-flush">
                    <a href="{{ route('tools.pbx-manager') }}" class="list-group-item list-group-item-action py-2 {{ !$currentList ? 'active' : '' }}">All</a>
                    <a href="{{ route('tools.pbx-manager', ['list' => 'Completed Calls']) }}" class="list-group-item list-group-item-action py-2 {{ $currentList === 'Completed Calls' ? 'active' : '' }}">Completed Calls</a>
                    <a href="{{ route('tools.pbx-manager', ['list' => 'No Response Calls']) }}" class="list-group-item list-group-item-action py-2 {{ $currentList === 'No Response Calls' ? 'active' : '' }}">No Response Calls</a>
                </div>
                <h6 class="text-uppercase small fw-bold text-muted mb-2 mt-3">TAGS</h6>
                <p class="text-muted small mb-0">No tags</p>
            </div>
        </div>
    </div>

    {{-- Main table --}}
    <div class="col-lg-9">
        <div class="card pbx-table-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-bottom bg-light">
                    <form action="{{ route('tools.pbx-manager') }}" method="GET" class="d-flex">
                        @if($currentList)<input type="hidden" name="list" value="{{ $currentList }}">@endif
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Type to search" value="{{ request('search') }}" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-search"></i></button>
                    </form>
                    @if ($calls->total() > 0)
                        <span class="text-muted small">{{ $calls->firstItem() }} to {{ $calls->lastItem() }} of {{ $calls->total() }}</span>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle pbx-table mb-0">
                        <thead>
                            <tr>
                                <th class="pbx-th">Call Status</th>
                                <th class="pbx-th">Direction</th>
                                <th class="pbx-th">Customer Number</th>
                                <th class="pbx-th">Reason For Calling</th>
                                <th class="pbx-th">Customer</th>
                                <th class="pbx-th">User</th>
                                <th class="pbx-th">Recording</th>
                                <th class="pbx-th">Duration (sec)</th>
                                <th class="pbx-th">Start Time</th>
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
                                    <td class="text-muted">{{ Str::limit($call->reason_for_calling ?? '—', 25) }}</td>
                                    <td>{{ $call->customer_name ?? '—' }}</td>
                                    <td>{{ $call->user_name ?? '—' }}</td>
                                    <td>
                                        @if(($call->from_vtiger ?? false) && !empty($call->id))
                                            <button type="button" class="btn btn-sm btn-outline-primary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording.vtiger', $call->id) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ $call->start_time?->format('d/m H:i') ?? '' }}">
                                                <i class="bi bi-play-circle me-1"></i>Listen
                                            </button>
                                        @elseif(!empty($call->recording_url))
                                            <button type="button" class="btn btn-sm btn-outline-primary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording', $call) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ $call->start_time?->format('d/m H:i') ?? '' }}">
                                                <i class="bi bi-play-circle me-1"></i>Listen
                                            </button>
                                        @elseif(method_exists($call, 'hasRecording') && $call->hasRecording())
                                            <button type="button" class="btn btn-sm btn-outline-primary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording', $call) }}" data-call-info="{{ $call->customer_number ?? '' }} - {{ $call->start_time?->format('d/m H:i') ?? '' }}">
                                                <i class="bi bi-play-circle me-1"></i>Listen
                                            </button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $call->duration_sec ?? 0 }}</td>
                                    <td class="text-nowrap">{{ $call->start_time?->format('d-m-Y h:i A') ?? '—' }}</td>
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
.pbx-sidebar-card { border-radius: 12px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.pbx-table-card { border-radius: 12px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.pbx-th { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #fff; background: var(--primary, #0E4385); padding: 0.75rem 1rem; }
.pbx-table td { padding: 0.75rem 1rem; vertical-align: middle; }
.pbx-table tbody tr:nth-child(even) { background: rgba(14, 67, 133, 0.04); }
.pbx-table tbody tr:hover { background: rgba(14, 67, 133, 0.08); }
.pbx-badge { font-size: .7rem; }
.pbx-badge-completed { background: rgba(5, 150, 105, 0.15); color: var(--success, #059669); }
.pbx-badge-busy, .pbx-badge-no-response, .pbx-badge-no-answer { background: rgba(217, 119, 6, 0.15); color: var(--warning, #d97706); }
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
</script>
@endsection
