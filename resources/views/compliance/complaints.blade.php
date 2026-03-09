@extends('layouts.app')

@section('title', 'Complaint Register')

@section('content')
<div class="complaints-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="app-page-title mb-1">Complaint Register</h1>
            <p class="app-page-sub mb-0">IRA compliance — record and track customer complaints</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('compliance.complaints.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to Excel
            </a>
            <a href="{{ route('compliance.complaints.create') }}" class="app-topbar-add">
                <i class="bi bi-plus-lg"></i> Register Complaint
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="complaints-stat-card complaints-stat-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="complaints-stat-label mb-0">Total</p>
                        <h2 class="complaints-stat-value mb-0">{{ number_format($total ?? 0) }}</h2>
                    </div>
                    <div class="complaints-stat-icon"><i class="bi bi-clipboard2-data-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="complaints-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="complaints-stat-label text-muted mb-0">Open</p>
                        <h2 class="complaints-stat-value mb-0" style="color:var(--geminia-warning);">{{ number_format($received ?? 0) }}</h2>
                    </div>
                    <div class="complaints-stat-icon complaints-stat-icon-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="complaints-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="complaints-stat-label text-muted mb-0">Resolved</p>
                        <h2 class="complaints-stat-value mb-0" style="color:var(--geminia-success);">{{ number_format($resolved ?? 0) }}</h2>
                    </div>
                    <div class="complaints-stat-icon complaints-stat-icon-success"><i class="bi bi-check-circle-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="complaints-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="complaints-stat-label text-muted mb-0">Closed</p>
                        <h2 class="complaints-stat-value mb-0" style="color:var(--geminia-text-muted);">{{ number_format($closed ?? 0) }}</h2>
                    </div>
                    <div class="complaints-stat-icon complaints-stat-icon-muted"><i class="bi bi-x-circle-fill"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status pills --}}
    <div class="complaints-status-pills mb-4">
        <a href="{{ route('compliance.complaints.index') }}" class="complaints-pill {{ !request('status') ? 'active' : '' }}">
            All <span class="complaints-pill-count">{{ number_format($total ?? 0) }}</span>
        </a>
        <a href="{{ route('compliance.complaints.index', ['status' => 'Received']) }}" class="complaints-pill {{ request('status') === 'Received' ? 'active' : '' }}">
            Received <span class="complaints-pill-count">{{ $byStatus['Received'] ?? 0 }}</span>
        </a>
        <a href="{{ route('compliance.complaints.index', ['status' => 'Under Investigation']) }}" class="complaints-pill {{ request('status') === 'Under Investigation' ? 'active' : '' }}">
            Under Investigation <span class="complaints-pill-count">{{ $byStatus['Under Investigation'] ?? 0 }}</span>
        </a>
        <a href="{{ route('compliance.complaints.index', ['status' => 'Resolved']) }}" class="complaints-pill {{ request('status') === 'Resolved' ? 'active' : '' }}">
            Resolved <span class="complaints-pill-count">{{ $byStatus['Resolved'] ?? 0 }}</span>
        </a>
        <a href="{{ route('compliance.complaints.index', ['status' => 'Closed']) }}" class="complaints-pill {{ request('status') === 'Closed' ? 'active' : '' }}">
            Closed <span class="complaints-pill-count">{{ $byStatus['Closed'] ?? 0 }}</span>
        </a>
    </div>

    <div class="app-card overflow-hidden">
        <form action="{{ route('compliance.complaints.index') }}" method="GET" class="complaints-toolbar">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="complaints-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent" placeholder="Search by ref, complainant, policy..." value="{{ request('search') }}">
            </div>
            <select name="nature" class="form-select complaints-select" onchange="this.form.submit()">
                <option value="">All natures</option>
                @foreach(\App\Models\Complaint::NATURES as $val => $label)
                    <option value="{{ $val }}" {{ request('nature') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px;padding:.4rem 1rem">Search</button>
            <a href="{{ route('compliance.complaints.export', array_filter(request()->only(['search','status','nature']))) }}" class="btn btn-sm btn-outline-secondary" title="Export current view">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
            <a href="{{ route('compliance.complaints.create') }}" class="btn btn-sm app-topbar-add">Register Complaint</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover complaints-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Complainant</th>
                        <th>Policy</th>
                        <th>Nature</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($complaints as $c)
                        <tr>
                            <td>
                                <a href="{{ route('compliance.complaints.show', $c) }}" class="complaints-ref-link fw-semibold font-monospace">{{ $c->complaint_ref }}</a>
                            </td>
                            <td><span class="text-muted small">{{ $c->date_received?->format('d M Y') ?? '—' }}</span></td>
                            <td>
                                <div>
                                    <span class="fw-medium">{{ $c->complainant_name }}</span>
                                    @if($c->complainant_phone)<br><span class="text-muted small">{{ $c->complainant_phone }}</span>@endif
                                </div>
                            </td>
                            <td><span class="text-muted small font-monospace">{{ $c->policy_number ?: '—' }}</span></td>
                            <td><span class="text-muted small">{{ $c->nature ?: '—' }}</span></td>
                            <td>
                                <span class="complaints-status-badge complaints-status-{{ Str::slug($c->status ?? '') }}">
                                    {{ $c->status ?? '—' }}
                                </span>
                            </td>
                            <td><span class="text-muted small">{{ $c->assigned_to ?: '—' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('compliance.complaints.show', $c) }}" class="btn btn-sm btn-link text-muted p-1" title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('compliance.complaints.edit', $c) }}" class="btn btn-sm btn-link text-muted p-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="text-center py-5">
                                    <div class="complaints-empty-icon mb-3"><i class="bi bi-clipboard2-x"></i></div>
                                    <h5 class="fw-bold mb-2">No complaints recorded</h5>
                                    <p class="text-muted mb-3">Register complaints to meet IRA compliance requirements.</p>
                                    <a href="{{ route('compliance.complaints.create') }}" class="app-topbar-add"><i class="bi bi-plus-lg me-1"></i>Register Complaint</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($complaints->hasPages())
            <div class="complaints-pagination">
                <span class="text-muted small">Showing {{ $complaints->firstItem() ?? 0 }}–{{ $complaints->lastItem() ?? 0 }} of {{ $complaints->total() }}</span>
                {{ $complaints->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<style>
.complaints-stat-card { background:#fff; border-radius:12px; border:1px solid var(--geminia-border); padding:1.25rem 1.5rem; transition:all .2s; }
.complaints-stat-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.06); }
.complaints-stat-primary { background:linear-gradient(135deg, var(--geminia-primary), var(--geminia-primary-dark)); border:none; color:#fff; }
.complaints-stat-primary .complaints-stat-label { opacity:.9; font-size:.75rem; font-weight:600; text-transform:uppercase; }
.complaints-stat-value { font-size:1.5rem; font-weight:700; }
.complaints-stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; }
.complaints-stat-icon-warning { background:rgba(217,119,6,.15); color:var(--geminia-warning); }
.complaints-stat-icon-success { background:rgba(5,150,105,.15); color:var(--geminia-success); }
.complaints-stat-icon-muted { background:rgba(100,116,139,.15); color:var(--geminia-text-muted); }

.complaints-status-pills { display:flex; flex-wrap:wrap; gap:.5rem; }
.complaints-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1rem; border-radius:9999px; background:#fff; border:1px solid var(--geminia-border); color:var(--geminia-text); text-decoration:none; font-size:.875rem; font-weight:500; transition:all .2s; }
.complaints-pill:hover { border-color:var(--geminia-primary); color:var(--geminia-primary); }
.complaints-pill.active { background:var(--geminia-primary); border-color:var(--geminia-primary); color:#fff; }
.complaints-pill-count { font-size:.75rem; opacity:.85; }

.complaints-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; padding:1rem 1.25rem; background:#f8fafc; border-bottom:1px solid var(--geminia-border); }
.complaints-search { flex:1; min-width:200px; max-width:360px; display:flex; align-items:center; gap:.5rem; padding:.5rem 1rem; background:#fff; border:1px solid var(--geminia-border); border-radius:8px; }
.complaints-search input { padding:0; font-size:.9rem; }
.complaints-select { max-width:180px; border-radius:8px; }

.complaints-table th { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--geminia-text-muted); padding:1rem 1.25rem; }
.complaints-table td { padding:1rem 1.25rem; vertical-align:middle; }
.complaints-table tbody tr:hover { background:var(--geminia-primary-muted); }
.complaints-ref-link { color:var(--geminia-primary); text-decoration:none; }
.complaints-ref-link:hover { text-decoration:underline; }
.complaints-status-badge { font-size:.7rem; font-weight:600; padding:.3rem .6rem; border-radius:9999px; display:inline-block; }
.complaints-status-received { background:rgba(217,119,6,.15); color:var(--geminia-warning); }
.complaints-status-under-investigation { background:rgba(14,165,233,.15); color:#0284c7; }
.complaints-status-pending-response { background:rgba(217,119,6,.15); color:var(--geminia-warning); }
.complaints-status-resolved { background:rgba(5,150,105,.15); color:var(--geminia-success); }
.complaints-status-escalated-to-ira { background:rgba(220,38,38,.15); color:#dc2626; }
.complaints-status-closed { background:rgba(100,116,139,.15); color:var(--geminia-text-muted); }

.complaints-empty-icon { width:72px; height:72px; margin:0 auto; background:var(--geminia-primary-muted); color:var(--geminia-primary); border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:2rem; }
.complaints-pagination { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 1.25rem; background:#f8fafc; border-top:1px solid var(--geminia-border); }
</style>
@endsection
