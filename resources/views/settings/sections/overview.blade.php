<div class="settings-overview">
    <div class="settings-overview-intro mb-5">
        <h2 class="settings-overview-title">Quick access</h2>
        <p class="settings-overview-desc">Jump directly to the setting you need.</p>
    </div>
    <div class="settings-overview-grid">
        <a href="{{ route('settings.crm') }}?section=users" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-people"></i></span>
            <span class="settings-overview-name">Users</span>
            <span class="settings-overview-hint">Manage users and roles</span>
        </a>
        <a href="{{ route('settings.crm') }}?section=ticket-sla" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-clock-history"></i></span>
            <span class="settings-overview-name">Ticket SLA</span>
            <span class="settings-overview-hint">Department TAT & close permissions</span>
        </a>
        <a href="{{ route('settings.crm') }}?section=ticket-automation" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-arrow-left-right"></i></span>
            <span class="settings-overview-name">Ticket Assignment</span>
            <span class="settings-overview-hint">Auto-assign by keywords</span>
        </a>
        <a href="{{ route('settings.crm') }}?section=pbx-extension-mapping" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-telephone"></i></span>
            <span class="settings-overview-name">PBX Mapping</span>
            <span class="settings-overview-hint">Map extensions to users</span>
        </a>
        <a href="{{ route('settings.crm') }}?section=modules" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-grid-3x3-gap"></i></span>
            <span class="settings-overview-name">Modules</span>
            <span class="settings-overview-hint">Enable or disable modules</span>
        </a>
        <a href="{{ route('settings.crm') }}?section=roles" class="settings-overview-card">
            <span class="settings-overview-icon"><i class="bi bi-shield-lock"></i></span>
            <span class="settings-overview-name">Roles</span>
            <span class="settings-overview-hint">Roles and profiles</span>
        </a>
    </div>
</div>

<style>
.settings-overview-intro { }
.settings-overview-title { font-size: 1.25rem; font-weight: 600; color: var(--geminia-text); margin: 0 0 0.35rem; }
.settings-overview-desc { font-size: 0.95rem; color: var(--geminia-text-muted); margin: 0; }
.settings-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}
.settings-overview-card {
    display: flex; flex-direction: column; align-items: flex-start;
    padding: 1.5rem; background: #fafbfc;
    border: 1px solid var(--geminia-border);
    border-radius: 12px; text-decoration: none; color: var(--geminia-text);
    transition: all 0.2s ease;
}
.settings-overview-card:hover {
    background: var(--geminia-primary-muted);
    border-color: var(--geminia-primary);
    color: var(--geminia-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 74, 138, 0.08);
}
.settings-overview-icon {
    width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
    background: rgba(26, 74, 138, 0.08); border-radius: 12px;
    color: var(--geminia-primary); font-size: 1.35rem; margin-bottom: 1rem;
}
.settings-overview-card:hover .settings-overview-icon {
    background: var(--geminia-primary);
    color: #fff;
}
.settings-overview-name { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
.settings-overview-hint { font-size: 0.85rem; color: var(--geminia-text-muted); line-height: 1.4; }
.settings-overview-card:hover .settings-overview-hint { color: inherit; opacity: 0.85; }
</style>
