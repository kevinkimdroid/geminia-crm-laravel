@extends('layouts.app')

@section('title', 'CRM Settings')

@section('content')
<div class="settings-page">
    <header class="settings-header">
        <div class="settings-header-top">
            <div>
                <h1 class="settings-title">Settings</h1>
                <p class="settings-subtitle">Configure your CRM</p>
            </div>
        </div>
        <nav class="settings-tabs" role="tablist">
            <a href="{{ route('settings.crm') }}?section=overview" class="settings-tab {{ ($section ?? '') === 'overview' ? 'active' : '' }}">
                <i class="bi bi-house"></i> Overview
            </a>
            <div class="settings-tab-group dropdown">
                <button type="button" class="settings-tab settings-tab-dropdown {{ in_array($section ?? '', ['users','departments','roles','profiles','sharing-rules','groups','login-history']) ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-people"></i> People <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=users"><i class="bi bi-person me-2"></i>Users</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=departments"><i class="bi bi-building me-2"></i>Departments</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=roles"><i class="bi bi-shield-lock me-2"></i>Roles</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=profiles"><i class="bi bi-person-vcard me-2"></i>Profiles</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=sharing-rules"><i class="bi bi-share me-2"></i>Sharing Rules</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=groups"><i class="bi bi-people me-2"></i>Groups</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=login-history"><i class="bi bi-clock-history me-2"></i>Login History</a></li>
                </ul>
            </div>
            <div class="settings-tab-group dropdown">
                <button type="button" class="settings-tab settings-tab-dropdown {{ in_array($section ?? '', ['ticket-automation','ticket-sla','scheduler','workflows']) ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-ticket-perforated"></i> Tickets <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=ticket-sla"><i class="bi bi-clock-history me-2"></i>SLA & TAT</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=ticket-automation"><i class="bi bi-arrow-left-right me-2"></i>Assignment Rules</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=scheduler"><i class="bi bi-calendar-check me-2"></i>Scheduler</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=workflows"><i class="bi bi-diagram-3 me-2"></i>Workflows</a></li>
                </ul>
            </div>
            <div class="settings-tab-group dropdown">
                <button type="button" class="settings-tab settings-tab-dropdown {{ in_array($section ?? '', ['modules','module-numbering']) || request()->routeIs('settings.layout-editor*') ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-grid-3x3-gap"></i> Modules <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=modules"><i class="bi bi-grid me-2"></i>Modules</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.layout-editor') }}"><i class="bi bi-layout-text-sidebar me-2"></i>Layouts & Fields</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=module-numbering"><i class="bi bi-hash me-2"></i>Numbering</a></li>
                </ul>
            </div>
            <div class="settings-tab-group dropdown">
                <button type="button" class="settings-tab settings-tab-dropdown {{ in_array($section ?? '', ['configuration','pbx-extension-mapping','marketing','integration','other']) ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i> System <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=configuration"><i class="bi bi-sliders me-2"></i>General</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=pbx-extension-mapping"><i class="bi bi-telephone me-2"></i>PBX Mapping</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=marketing"><i class="bi bi-bullseye me-2"></i>Marketing</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=integration"><i class="bi bi-plug me-2"></i>Integrations</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.crm') }}?section=other"><i class="bi bi-three-dots me-2"></i>Other</a></li>
                </ul>
            </div>
            <div class="settings-tab-spacer"></div>
            <div class="settings-search-trigger">
                <div class="input-group input-group-sm" style="max-width:200px">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control form-control-sm border-start-0 bg-transparent" placeholder="Search..." id="settingsSearch" aria-label="Search settings">
                </div>
            </div>
        </nav>
    </header>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <main class="settings-main">
        <div class="settings-main-inner">
            @if(($section ?? '') !== 'overview')
            <a href="{{ route('settings.crm') }}?section=overview" class="settings-back-link">
                <i class="bi bi-arrow-left"></i> Back to overview
            </a>
            @endif
            @php
                $section = $section ?? 'overview';
                $validSections = ['overview', 'users', 'departments', 'roles', 'profiles', 'sharing-rules', 'groups', 'login-history', 'modules', 'module-layouts', 'module-numbering', 'scheduler', 'workflows', 'ticket-automation', 'ticket-sla', 'configuration', 'pbx-extension-mapping', 'marketing', 'integration', 'other'];
                $section = in_array($section, $validSections) ? $section : 'overview';
            @endphp
            @include('settings.sections.' . $section)
        </div>
    </main>
</div>

<style>
.settings-page { width: calc(100% + 3.5rem); margin: 0 -1.75rem; max-width: none; padding: 0 1rem; }
.settings-header { margin-bottom: 2rem; }
.settings-title { font-size: 1.75rem; font-weight: 700; color: var(--geminia-text); margin: 0 0 0.25rem; }
.settings-subtitle { font-size: 0.95rem; color: var(--geminia-text-muted); margin: 0; }
.settings-tabs {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.25rem;
    margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--geminia-border);
}
.settings-tab {
    display: inline-flex; align-items: center;
    padding: 0.5rem 1rem; font-size: 0.9rem; font-weight: 500;
    color: var(--geminia-text-muted); text-decoration: none; border-radius: 8px;
    transition: color 0.2s, background 0.2s; border: none; background: transparent; cursor: pointer;
}
.settings-tab:hover { color: var(--geminia-primary); background: var(--geminia-primary-muted); }
.settings-tab.active { color: var(--geminia-primary); background: var(--geminia-primary-muted); font-weight: 600; }
.settings-tab i:first-child { margin-right: 0.4rem; opacity: 0.9; }
.settings-tab-group { position: relative; }
.settings-tab-dropdown { font-family: inherit; }
.settings-tab-group .dropdown-menu { margin-top: 0.25rem; border-radius: 10px; padding: 0.5rem; min-width: 200px; }
.settings-tab-group .dropdown-item { border-radius: 6px; padding: 0.5rem 0.75rem; }
.settings-tab-spacer { flex: 1; min-width: 1rem; }
.settings-search-trigger { }
.settings-search-trigger .input-group { border: 1px solid transparent; border-radius: 8px; transition: border-color 0.2s, box-shadow 0.2s; }
.settings-search-trigger .input-group:focus-within { border-color: var(--geminia-border); box-shadow: 0 0 0 2px var(--geminia-primary-muted); }

.settings-main { }
.settings-main-inner {
    background: #fff; border-radius: 14px;
    border: 1px solid var(--geminia-border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    padding: 2rem; min-height: 400px;
}
.settings-back-link {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.875rem; color: var(--geminia-text-muted); text-decoration: none;
    margin-bottom: 1.5rem; transition: color 0.2s;
}
.settings-back-link:hover { color: var(--geminia-primary); }

/* Tables in settings */
.settings-table { width: 100%; border-collapse: collapse; }
.settings-table thead th {
    background: #f8fafc; border-bottom: 1px solid var(--geminia-border);
    padding: 0.875rem 1.25rem; font-size: 0.7rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.05em; color: var(--geminia-text-muted); text-align: left;
}
.settings-table tbody td { padding: 0.875rem 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.settings-table tbody tr:hover { background: var(--geminia-primary-muted); }
.settings-table tbody tr:last-child td { border-bottom: none; }
.settings-table .col-user { min-width: 200px; white-space: nowrap; }
.settings-table .col-email { min-width: 220px; }
.settings-table .col-username { min-width: 120px; white-space: nowrap; }
.settings-table .col-role { min-width: 240px; }
.settings-table .col-action { min-width: 320px; white-space: nowrap; }

@media (max-width: 767px) {
    .settings-page { width: calc(100% + 2rem); margin: 0 -1rem; padding: 0 0.75rem; }
    .settings-tabs { flex-direction: column; align-items: stretch; }
    .settings-tab { justify-content: flex-start; }
    .settings-tab-spacer { display: none; }
    .settings-search-trigger { order: -1; margin-bottom: 0.5rem; }
    .settings-search-trigger .input-group { max-width: none !important; }
    .settings-main-inner { padding: 1.25rem; }
}
</style>

<script>
(function() {
    var search = document.getElementById('settingsSearch');
    if (!search) return;
    var overviewLink = document.querySelector('a.settings-tab[href*="overview"]');
    search.addEventListener('input', function() {
        var q = (this.value || '').toLowerCase().trim();
        if (q.length < 2) {
            document.querySelectorAll('.settings-tab-group').forEach(function(g) { g.style.display = ''; });
            if (overviewLink) overviewLink.style.display = '';
            return;
        }
        if (overviewLink) overviewLink.style.display = ('overview'.indexOf(q) >= 0) ? '' : 'none';
        document.querySelectorAll('.settings-tab-group').forEach(function(g) {
            var btnText = (g.querySelector('.settings-tab')?.textContent || '').toLowerCase();
            var anyMatch = btnText.indexOf(q) >= 0;
            g.querySelectorAll('.dropdown-item').forEach(function(a) {
                if ((a.textContent || '').toLowerCase().indexOf(q) >= 0) anyMatch = true;
            });
            g.style.display = anyMatch ? '' : 'none';
        });
    });
})();
</script>
@endsection
