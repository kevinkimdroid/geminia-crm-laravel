@extends('layouts.app')

@section('title', 'PDF Maker')

@section('content')
<div class="page-header">
    <h1 class="page-title">PDF Maker</h1>
    <p class="page-subtitle">Create professional PDFs for invoices, orders, and quotes. No coding required—just fill in the form and customize with simple options.</p>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card pdf-maker-card overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table pdf-maker-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="pdf-maker-th" style="width: 50px;"></th>
                                <th class="pdf-maker-th">Module</th>
                                <th class="pdf-maker-th">Description</th>
                                <th class="pdf-maker-th text-end" width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modules ?? [] as $module)
                                <tr>
                                    <td>
                                        <div class="pdf-maker-module-icon">
                                            <i class="bi {{ $module['icon'] ?? 'bi-file-pdf' }}"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('tools.pdf-maker.create', $module['slug']) }}" class="pdf-maker-module-link fw-semibold">
                                            {{ $module['name'] }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $module['description'] }}</td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-muted p-0 pdf-maker-actions" type="button" data-bs-toggle="dropdown" aria-label="Actions">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end pdf-maker-dropdown">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('tools.pdf-maker.create', $module['slug']) }}">
                                                        <i class="bi bi-file-earmark-pdf me-2"></i>Create PDF
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('tools.pdf-maker.template', $module['slug']) }}">
                                                        <i class="bi bi-gear me-2"></i>Customize Template
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer pdf-maker-footer bg-transparent border-top py-2 px-3">
                <span class="text-muted small">PDF Maker — Geminia CRM</span>
            </div>
        </div>
    </div>
</div>

<style>
.pdf-maker-card { border-radius: 14px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); box-shadow: 0 2px 12px rgba(14, 67, 133, 0.06); }
.pdf-maker-th { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted, #64748b); padding: 1rem 1.25rem; background: linear-gradient(180deg, #fafbfd 0%, #f8fafc 100%); border-bottom: 1px solid var(--card-border); }
.pdf-maker-table td { padding: 1rem 1.25rem; vertical-align: middle; transition: background .15s; }
.pdf-maker-table tbody tr:hover td { background: rgba(14, 67, 133, 0.04); }
.pdf-maker-module-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--primary-light, rgba(14, 67, 133, 0.12)); color: var(--primary, #0E4385); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.pdf-maker-module-link { color: var(--text, #1e3a5f); text-decoration: none; }
.pdf-maker-module-link:hover { color: var(--primary, #0E4385); }
.pdf-maker-actions:hover { color: var(--primary) !important; }
.pdf-maker-dropdown { border-radius: 12px; box-shadow: 0 8px 24px rgba(14, 67, 133, 0.12); border: 1px solid var(--card-border); padding: .5rem; }
.pdf-maker-dropdown .dropdown-item { border-radius: 8px; padding: .5rem 1rem; }
.pdf-maker-footer { font-size: .8rem; }
</style>
@endsection
