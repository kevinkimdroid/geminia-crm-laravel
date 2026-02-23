@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
<div class="campaigns-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="app-page-title mb-1">Campaigns</h1>
            <p class="app-page-sub mb-0">Create and manage your marketing campaigns</p>
        </div>
        <a href="{{ route('marketing.campaigns.create') }}" class="app-topbar-add">
            <i class="bi bi-plus-lg"></i> New Campaign
        </a>
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
            <div class="campaigns-stat-card campaigns-stat-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="campaigns-stat-label mb-0">Total</p>
                        <h2 class="campaigns-stat-value mb-0">{{ number_format($totalCampaigns ?? 0) }}</h2>
                    </div>
                    <div class="campaigns-stat-icon"><i class="bi bi-megaphone-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="campaigns-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="campaigns-stat-label text-muted mb-0">Active</p>
                        <h2 class="campaigns-stat-value mb-0" style="color:var(--geminia-success);">{{ number_format($activeCampaigns ?? 0) }}</h2>
                    </div>
                    <div class="campaigns-stat-icon campaigns-stat-icon-success"><i class="bi bi-check-circle-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="campaigns-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="campaigns-stat-label text-muted mb-0">Planning</p>
                        <h2 class="campaigns-stat-value mb-0" style="color:var(--geminia-warning);">{{ number_format($planningCampaigns ?? 0) }}</h2>
                    </div>
                    <div class="campaigns-stat-icon campaigns-stat-icon-warning"><i class="bi bi-clock-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="campaigns-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="campaigns-stat-label text-muted mb-0">Expected Revenue</p>
                        <h2 class="campaigns-stat-value mb-0" style="color:var(--geminia-primary);font-size:1.15rem;">KES {{ number_format($totalExpectedRevenue ?? 0, 0) }}</h2>
                    </div>
                    <div class="campaigns-stat-icon campaigns-stat-icon-primary"><i class="bi bi-currency-dollar"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status pills --}}
    <div class="campaigns-status-pills mb-4">
        <a href="{{ route('marketing.campaigns.index') }}" class="campaigns-pill {{ !request('status') ? 'active' : '' }}">
            All <span class="campaigns-pill-count">{{ number_format($totalCampaigns ?? 0) }}</span>
        </a>
        <a href="{{ route('marketing.campaigns.index', ['status' => 'Active']) }}" class="campaigns-pill campaigns-pill-active {{ request('status') === 'Active' ? 'active' : '' }}">
            Active <span class="campaigns-pill-count">{{ number_format($activeCampaigns ?? 0) }}</span>
        </a>
        <a href="{{ route('marketing.campaigns.index', ['status' => 'Planning']) }}" class="campaigns-pill campaigns-pill-planning {{ request('status') === 'Planning' ? 'active' : '' }}">
            Planning <span class="campaigns-pill-count">{{ number_format($planningCampaigns ?? 0) }}</span>
        </a>
        <a href="{{ route('marketing.campaigns.index', ['status' => 'Completed']) }}" class="campaigns-pill campaigns-pill-completed {{ request('status') === 'Completed' ? 'active' : '' }}">
            Completed <span class="campaigns-pill-count">{{ number_format($completedCampaigns ?? 0) }}</span>
        </a>
    </div>

    <div class="app-card overflow-hidden">
        {{-- Search & filters --}}
        <form action="{{ route('marketing.campaigns.index') }}" method="GET" class="campaigns-toolbar">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            @if(request('list'))<input type="hidden" name="list" value="{{ request('list') }}">@endif
            <div class="campaigns-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent" placeholder="Search campaigns by name, type, or assignee..." value="{{ request('search') }}">
            </div>
            <select name="list" class="form-select campaigns-list-select" onchange="this.form.submit()">
                <option value="">All lists</option>
                @foreach($lists ?? [] as $list)
                    <option value="{{ $list }}" {{ request('list') == $list ? 'selected' : '' }}>{{ $list }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm" style="background:var(--geminia-primary);color:#fff;border-radius:8px;padding:.4rem 1rem">Search</button>
            <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-sm app-topbar-add">Add Campaign</a>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover campaigns-table mb-0">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Expected Revenue</th>
                        <th>Close Date</th>
                        <th>Assigned To</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td>
                                <a href="{{ route('marketing.campaigns.edit', $campaign) }}" class="campaigns-name-link">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="campaigns-row-icon"><i class="bi bi-megaphone"></i></div>
                                        <span class="fw-semibold">{{ $campaign->campaign_name }}</span>
                                    </div>
                                </a>
                            </td>
                            <td><span class="text-muted small">{{ $campaign->campaign_type ?: '—' }}</span></td>
                            <td>
                                <span class="campaigns-status-badge campaigns-status-{{ Str::slug($campaign->campaign_status ?? '') }}">
                                    {{ $campaign->campaign_status ?? '—' }}
                                </span>
                            </td>
                            <td><strong class="campaigns-revenue">KES {{ number_format($campaign->expected_revenue ?? 0, 0) }}</strong></td>
                            <td><span class="text-muted small">{{ $campaign->expected_close_date ? $campaign->expected_close_date->format('d M Y') : '—' }}</span></td>
                            <td><span class="text-muted small">{{ $campaign->assigned_to ?: '—' }}</span></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted p-1" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('marketing.campaigns.edit', $campaign) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                        <li>
                                            <form action="{{ route('marketing.campaigns.destroy', $campaign) }}" method="POST" onsubmit="return confirm('Delete this campaign?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-5">
                                    <div class="campaigns-empty-icon mb-3"><i class="bi bi-megaphone"></i></div>
                                    <h5 class="fw-bold mb-2">No campaigns yet</h5>
                                    <p class="text-muted mb-3">Get started by creating your first marketing campaign.</p>
                                    <a href="{{ route('marketing.campaigns.create') }}" class="app-topbar-add"><i class="bi bi-plus-lg me-1"></i>Create Campaign</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($campaigns->hasPages())
            <div class="campaigns-pagination">
                <span class="text-muted small">Showing {{ $campaigns->firstItem() ?? 0 }}–{{ $campaigns->lastItem() ?? 0 }} of {{ $campaigns->total() }}</span>
                {{ $campaigns->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<style>
.campaigns-stat-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--geminia-border);
    padding: 1.25rem 1.5rem;
    transition: all 0.2s;
}
.campaigns-stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.campaigns-stat-primary {
    background: linear-gradient(135deg, var(--geminia-primary) 0%, var(--geminia-primary-dark) 100%);
    border: none;
    color: #fff;
}
.campaigns-stat-primary .campaigns-stat-label { opacity: 0.9; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.campaigns-stat-value { font-size: 1.5rem; font-weight: 700; }
.campaigns-stat-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--geminia-text-muted); }
.campaigns-stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
}
.campaigns-stat-icon-success { background: rgba(5, 150, 105, 0.15); color: var(--geminia-success); }
.campaigns-stat-icon-warning { background: rgba(217, 119, 6, 0.15); color: var(--geminia-warning); }
.campaigns-stat-icon-primary { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.campaigns-stat-primary .campaigns-stat-icon { background: rgba(255,255,255,0.2); }

.campaigns-status-pills { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.campaigns-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; border-radius: 9999px;
    background: #fff; border: 1px solid var(--geminia-border);
    color: var(--geminia-text); text-decoration: none; font-size: 0.875rem; font-weight: 500;
    transition: all 0.2s;
}
.campaigns-pill:hover { border-color: var(--geminia-primary); color: var(--geminia-primary); }
.campaigns-pill.active { background: var(--geminia-primary); border-color: var(--geminia-primary); color: #fff; }
.campaigns-pill-count { font-size: 0.75rem; opacity: 0.85; }
.campaigns-pill-active { border-color: rgba(5, 150, 105, 0.4); }
.campaigns-pill-active.active { background: var(--geminia-success); border-color: var(--geminia-success); }
.campaigns-pill-planning { border-color: rgba(217, 119, 6, 0.4); }
.campaigns-pill-planning.active { background: var(--geminia-warning); border-color: var(--geminia-warning); }
.campaigns-pill-completed { border-color: rgba(26, 85, 158, 0.4); }
.campaigns-pill-completed.active { background: var(--geminia-primary); border-color: var(--geminia-primary); }

.campaigns-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;
    padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid var(--geminia-border);
}
.campaigns-search {
    flex: 1; min-width: 200px; max-width: 360px;
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--geminia-border); border-radius: 8px;
}
.campaigns-search i { color: var(--geminia-text-muted); }
.campaigns-search input { padding: 0; font-size: 0.9rem; }
.campaigns-search input:focus { box-shadow: none; }
.campaigns-list-select { max-width: 160px; border-radius: 8px; }

.campaigns-table th { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--geminia-text-muted); padding: 1rem 1.25rem; white-space: nowrap; }
.campaigns-table td { padding: 1rem 1.25rem; vertical-align: middle; }
.campaigns-table tbody tr:hover { background: var(--geminia-primary-muted); }
.campaigns-name-link { font-weight: 500; color: var(--geminia-text); text-decoration: none; }
.campaigns-name-link:hover { color: var(--geminia-primary); }
.campaigns-row-icon {
    width: 36px; height: 36px;
    background: var(--geminia-primary-muted); color: var(--geminia-primary);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.campaigns-status-badge {
    font-size: 0.7rem; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 9999px; display: inline-block;
}
.campaigns-status-active { background: rgba(5, 150, 105, 0.15); color: var(--geminia-success); }
.campaigns-status-planning { background: rgba(217, 119, 6, 0.15); color: var(--geminia-warning); }
.campaigns-status-completed { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.campaigns-status- { background: rgba(100, 116, 139, 0.15); color: var(--geminia-text-muted); }
.campaigns-revenue { color: var(--geminia-primary); font-size: 0.9rem; }

.campaigns-empty-icon {
    width: 72px; height: 72px; margin: 0 auto;
    background: var(--geminia-primary-muted); color: var(--geminia-primary);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
}
.campaigns-pagination {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid var(--geminia-border);
}
.campaigns-pagination .pagination { margin: 0; }
.campaigns-pagination .page-link { border-radius: 8px; border-color: var(--geminia-border); color: var(--geminia-text); }
.campaigns-pagination .page-link:hover { background: var(--geminia-primary-muted); border-color: var(--geminia-primary); color: var(--geminia-primary); }
.campaigns-pagination .page-item.active .page-link { background: var(--geminia-primary); border-color: var(--geminia-primary); }
</style>
@endsection
