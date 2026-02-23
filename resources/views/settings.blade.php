@extends('layouts.app')

@section('title', 'CRM Settings')

@section('content')
<div class="settings-page">
    <div class="settings-header mb-4">
        <h1 class="app-page-title mb-1">CRM Settings</h1>
        <p class="app-page-sub mb-0">Configure your CRM and system preferences.</p>
    </div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="settings-layout">
    {{-- Left sidebar --}}
    <aside class="settings-sidebar">
        <div class="settings-sidebar-inner">
            <div class="settings-search-wrapper p-3 border-bottom">
                <input type="text" class="form-control form-control-sm" placeholder="Search settings..." id="settingsSearch" aria-label="Search settings">
            </div>
            <nav class="settings-nav">
                {{-- User Management --}}
                @php $userMgmtOpen = in_array($section ?? '', ['users', 'roles', 'profiles', 'sharing-rules', 'groups', 'login-history']); @endphp
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navUserMgmt" aria-expanded="{{ $userMgmtOpen ? 'true' : 'false' }}">
                        <span class="settings-nav-label">User Management</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel {{ $userMgmtOpen ? 'open' : '' }}" id="navUserMgmt">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=users" class="settings-nav-link {{ ($section ?? '') === 'users' ? 'active' : '' }}">Users</a>
                            <a href="{{ route('settings.crm') }}?section=roles" class="settings-nav-link {{ ($section ?? '') === 'roles' ? 'active' : '' }}">Roles</a>
                            <a href="{{ route('settings.crm') }}?section=profiles" class="settings-nav-link {{ ($section ?? '') === 'profiles' ? 'active' : '' }}">Profiles</a>
                            <a href="{{ route('settings.crm') }}?section=sharing-rules" class="settings-nav-link {{ ($section ?? '') === 'sharing-rules' ? 'active' : '' }}">Sharing Rules</a>
                            <a href="{{ route('settings.crm') }}?section=groups" class="settings-nav-link {{ ($section ?? '') === 'groups' ? 'active' : '' }}">Groups</a>
                            <a href="{{ route('settings.crm') }}?section=login-history" class="settings-nav-link {{ ($section ?? '') === 'login-history' ? 'active' : '' }}">Login History</a>
                        </div>
                    </div>
                </div>

                {{-- Module Management --}}
                @php $isModuleSection = in_array($section ?? '', ['modules', 'module-numbering']) || request()->routeIs('settings.layout-editor*'); @endphp
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navModuleMgmt" aria-expanded="{{ $isModuleSection ? 'true' : 'false' }}">
                        <span class="settings-nav-label">Module Management</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel {{ $isModuleSection ? 'open' : '' }}" id="navModuleMgmt">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=modules" class="settings-nav-link {{ ($section ?? '') === 'modules' ? 'active' : '' }}">Modules</a>
                            <a href="{{ route('settings.layout-editor') }}" class="settings-nav-link {{ request()->routeIs('settings.layout-editor*') ? 'active' : '' }}">Module Layouts & Fields</a>
                            <a href="{{ route('settings.crm') }}?section=module-numbering" class="settings-nav-link {{ ($section ?? '') === 'module-numbering' ? 'active' : '' }}">Module Numbering</a>
                        </div>
                    </div>
                </div>

                {{-- Automation --}}
                @php $automationOpen = in_array($section ?? '', ['scheduler', 'ticket-automation', 'ticket-sla', 'workflows']); @endphp
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navAutomation" aria-expanded="{{ $automationOpen ? 'true' : 'false' }}">
                        <span class="settings-nav-label">Automation</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel {{ $automationOpen ? 'open' : '' }}" id="navAutomation">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=scheduler" class="settings-nav-link {{ ($section ?? '') === 'scheduler' ? 'active' : '' }}">Scheduler</a>
                            <a href="{{ route('settings.crm') }}?section=ticket-automation" class="settings-nav-link {{ ($section ?? '') === 'ticket-automation' ? 'active' : '' }}">Ticket Assignment Rules</a>
                            <a href="{{ route('settings.crm') }}?section=ticket-sla" class="settings-nav-link {{ ($section ?? '') === 'ticket-sla' ? 'active' : '' }}">Ticket SLA & TAT</a>
                            <a href="{{ route('settings.crm') }}?section=workflows" class="settings-nav-link {{ ($section ?? '') === 'workflows' ? 'active' : '' }}">Workflows</a>
                        </div>
                    </div>
                </div>

                {{-- Configuration --}}
                @php $configOpen = ($section ?? '') === 'configuration'; @endphp
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navConfig" aria-expanded="{{ $configOpen ? 'true' : 'false' }}">
                        <span class="settings-nav-label">Configuration</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel {{ $configOpen ? 'open' : '' }}" id="navConfig">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=configuration" class="settings-nav-link {{ ($section ?? '') === 'configuration' ? 'active' : '' }}">General</a>
                        </div>
                    </div>
                </div>

                {{-- Marketing & Sales --}}
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navMarketing" aria-expanded="false">
                        <span class="settings-nav-label">Marketing & Sales</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel" id="navMarketing">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=marketing" class="settings-nav-link">Marketing</a>
                        </div>
                    </div>
                </div>

                {{-- Integration --}}
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navIntegration" aria-expanded="false">
                        <span class="settings-nav-label">Integration</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel" id="navIntegration">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=integration" class="settings-nav-link">Integrations</a>
                        </div>
                    </div>
                </div>

                {{-- Other Settings --}}
                @php $otherOpen = ($section ?? '') === 'other'; @endphp
                <div class="settings-nav-group">
                    <button type="button" class="settings-nav-toggle w-100 text-start d-flex align-items-center justify-content-between py-2 px-3" data-settings-toggle="navOther" aria-expanded="{{ $otherOpen ? 'true' : 'false' }}">
                        <span class="settings-nav-label">Other Settings</span>
                        <i class="bi bi-chevron-down settings-nav-chevron"></i>
                    </button>
                    <div class="settings-nav-panel {{ $otherOpen ? 'open' : '' }}" id="navOther">
                        <div class="settings-nav-links">
                            <a href="{{ route('settings.crm') }}?section=other" class="settings-nav-link {{ ($section ?? '') === 'other' ? 'active' : '' }}">Other</a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </aside>

    {{-- Right content area --}}
    <main class="settings-content">
        <div class="settings-content-inner">
            @php
                $section = $section ?? 'users';
                $validSections = ['users', 'roles', 'profiles', 'sharing-rules', 'groups', 'login-history', 'modules', 'module-layouts', 'module-numbering', 'scheduler', 'workflows', 'ticket-automation', 'ticket-sla', 'configuration', 'marketing', 'integration', 'other'];
                $section = in_array($section, $validSections) ? $section : 'users';
            @endphp
            @include('settings.sections.' . $section)
        </div>
    </main>
</div>

<style>
/* Settings layout - CSS Grid for reliable side-by-side layout */
.settings-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 991px) {
    .settings-layout { grid-template-columns: 1fr; }
}

/* Sidebar */
.settings-sidebar {
    position: sticky;
    top: 1rem;
}
.settings-sidebar-inner {
    background: #fff;
    border: 1px solid var(--geminia-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    overflow: hidden;
}
.settings-search-wrapper input {
    border: 1px solid var(--geminia-border);
    border-radius: 8px;
}

/* Nav */
.settings-nav { max-height: calc(100vh - 12rem); overflow-y: auto; }
.settings-nav-group { border-bottom: 1px solid var(--geminia-border); }
.settings-nav-group:last-child { border-bottom: none; }
.settings-nav-toggle {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--geminia-text-muted);
    transition: color 0.15s, background 0.15s;
}
.settings-nav-toggle:hover { color: var(--geminia-primary); background: var(--geminia-primary-muted); }
.settings-nav-toggle[aria-expanded="true"] { color: var(--geminia-primary); }
.settings-nav-toggle[aria-expanded="true"] .settings-nav-chevron { transform: rotate(180deg); }
.settings-nav-chevron { font-size: 0.7rem; transition: transform 0.2s; color: inherit; opacity: 0.7; }
.settings-nav-label { text-transform: uppercase; }
/* Settings nav panels - open/close without Bootstrap collapse */
.settings-nav-panel {
    display: none;
    overflow: hidden;
}
.settings-nav-panel.open {
    display: block;
}
.settings-nav-links {
    padding: 0.35rem 0;
    background: #f1f5f9;
}
.settings-nav-link {
    display: block !important;
    padding: 0.5rem 1rem 0.5rem 1.25rem;
    color: #1e293b !important;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    border-left: 3px solid transparent;
    margin-left: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.settings-nav-link:hover { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
.settings-nav-link.active {
    background: var(--geminia-primary-muted);
    color: var(--geminia-primary);
    font-weight: 600;
    border-left-color: var(--geminia-primary);
}

/* Content area */
.settings-content {
    min-width: 0; /* Prevent grid blowout */
}
.settings-content-inner {
    background: #fff;
    border: 1px solid var(--geminia-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    padding: 1.5rem;
    min-height: 400px;
}

/* Tables in settings */
.settings-table { width: 100%; border-collapse: collapse; }
.settings-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid var(--geminia-border);
    padding: 0.75rem 1rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--geminia-text-muted);
    text-align: left;
}
.settings-table tbody td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.settings-table tbody tr:hover { background: var(--geminia-primary-muted); }
.settings-table tbody tr:last-child td { border-bottom: none; }
.settings-table { table-layout: auto; min-width: 800px; }
.settings-table .col-user { min-width: 200px; white-space: nowrap; }
.settings-table .col-email { min-width: 220px; }
.settings-table .col-username { min-width: 120px; white-space: nowrap; }
.settings-table .col-role { min-width: 240px; }
.settings-table .col-action { min-width: 320px; white-space: nowrap; }
</style>
<script>
document.querySelectorAll('[data-settings-toggle]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var t = document.getElementById(this.getAttribute('data-settings-toggle'));
        if (!t) return;
        var open = t.classList.toggle('open');
        this.setAttribute('aria-expanded', open);
    });
});
</script>
@endsection
