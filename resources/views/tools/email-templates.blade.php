@extends('layouts.app')

@section('title', 'Email Templates')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">EMAIL TEMPLATES <span class="text-muted fw-normal">> List</span></h1>
        <p class="page-subtitle mb-0">Create and manage email templates for campaigns and notifications.</p>
    </div>
    <a href="{{ route('tools.email-templates.create') }}" class="btn btn-light border">
        <i class="bi bi-plus-lg me-2"></i>Add Email Template
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card email-tpl-card overflow-hidden">
    <div class="card-body p-0">
        {{-- Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-bottom bg-light">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary active" title="List view">
                    <i class="bi bi-list-ul"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Grid view">
                    <i class="bi bi-grid-3x3-gap"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-danger" title="Delete selected">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <form action="{{ route('tools.email-templates') }}" method="GET" class="d-flex flex-wrap align-items-center gap-1">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Type to search" value="{{ request('search') }}" style="width: 160px;">
                    <select name="module" class="form-select form-select-sm" style="width: 150px;" title="Filter by module" onchange="this.form.submit()">
                        <option value="">All modules</option>
                        @foreach ($modules ?? [] as $mod)
                            <option value="{{ $mod }}" @selected(request('module') === $mod)>{{ $mod }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                </form>
                @if ($templates->total() > 0)
                    <div class="d-flex align-items-center gap-2 small text-muted">
                        <span>{{ $templates->firstItem() }} to {{ $templates->lastItem() }} of {{ $templates->total() }}</span>
                        <a href="{{ $templates->previousPageUrl() }}" class="btn btn-sm btn-link p-1 text-muted {{ $templates->onFirstPage() ? 'disabled' : '' }}"><i class="bi bi-chevron-left"></i></a>
                        <a href="{{ $templates->nextPageUrl() }}" class="btn btn-sm btn-link p-1 text-muted {{ !$templates->hasMorePages() ? 'disabled' : '' }}"><i class="bi bi-chevron-right"></i></a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table email-tpl-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="email-tpl-th" style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th class="email-tpl-th">Template Name</th>
                        <th class="email-tpl-th">Subject</th>
                        <th class="email-tpl-th">Description</th>
                        <th class="email-tpl-th">Module Name</th>
                        <th class="email-tpl-th text-end" style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input row-select" value="{{ $template->id }}">
                            </td>
                            <td>
                                <a href="{{ route('tools.email-templates.edit', $template) }}" class="text-decoration-none fw-semibold">{{ $template->template_name }}</a>
                            </td>
                            <td>{{ $template->subject }}</td>
                            <td class="text-muted">{{ Str::limit($template->description ?? '—', 40) }}</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $template->module_name ?? '—' }}</span></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('tools.email-templates.edit', $template) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                        <li>
                                            <form action="{{ route('tools.email-templates.destroy', $template) }}" method="POST" onsubmit="return confirm('Delete this template?');">
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
                            <td colspan="6" class="text-center py-5 text-muted">
                                No email templates yet. <a href="{{ route('tools.email-templates.create') }}">Add your first template</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($templates->hasPages())
            <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
                <span class="text-muted small">{{ $templates->firstItem() }} to {{ $templates->lastItem() }} of {{ $templates->total() }}</span>
                {{ $templates->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<style>
.email-tpl-card { border-radius: 12px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.email-tpl-th { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #fff; background: var(--primary, #0E4385); padding: 0.75rem 1rem; }
.email-tpl-table td { padding: 0.75rem 1rem; vertical-align: middle; }
.email-tpl-table tbody tr:nth-child(even) { background: rgba(14, 67, 133, 0.04); }
.email-tpl-table tbody tr:hover { background: rgba(14, 67, 133, 0.08); }
</style>
@endsection
