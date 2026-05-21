@php
    $staffBanner = config('dashboard.staff_banner', []);
    $staffBannerMessage = trim((string) ($staffBanner['message'] ?? ''));
    $staffBannerEnabled = ($staffBanner['enabled'] ?? false) && $staffBannerMessage !== '';
    $staffBannerVariant = in_array($staffBanner['variant'] ?? 'info', ['info', 'warning', 'success'], true)
        ? $staffBanner['variant']
        : 'info';
    $staffBannerLinkUrl = trim((string) ($staffBanner['link_url'] ?? ''));
    $staffBannerLinkLabel = trim((string) ($staffBanner['link_label'] ?? 'Learn more'));
    $staffBannerShowLink = $staffBannerLinkUrl !== '' && filter_var($staffBannerLinkUrl, FILTER_VALIDATE_URL);
@endphp
@if ($staffBannerEnabled)
<div
    id="dashboardStaffBanner"
    class="dashboard-staff-banner dashboard-staff-banner--{{ $staffBannerVariant }}"
    data-banner-id="{{ $staffBanner['id'] ?? 'default' }}"
    role="status"
    aria-live="polite"
    hidden
>
    <div class="dashboard-staff-banner-icon" aria-hidden="true">
        @if ($staffBannerVariant === 'warning')
            <i class="bi bi-exclamation-triangle-fill"></i>
        @elseif ($staffBannerVariant === 'success')
            <i class="bi bi-check-circle-fill"></i>
        @else
            <i class="bi bi-megaphone-fill"></i>
        @endif
    </div>
    <div class="dashboard-staff-banner-body">
        <p class="dashboard-staff-banner-title">{{ $staffBanner['title'] ?? 'Notice for staff' }}</p>
        <p class="dashboard-staff-banner-message">{!! nl2br(e($staffBannerMessage)) !!}</p>
        @if ($staffBannerShowLink)
            <a href="{{ $staffBannerLinkUrl }}" class="dashboard-staff-banner-link" target="_blank" rel="noopener noreferrer">
                {{ $staffBannerLinkLabel !== '' ? $staffBannerLinkLabel : 'Learn more' }}
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            </a>
        @endif
    </div>
    <button type="button" class="dashboard-staff-banner-close" data-dashboard-staff-banner-dismiss aria-label="Dismiss notice">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
</div>

<style>
.dashboard-staff-banner {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: 1080;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    width: min(24rem, calc(100vw - 2.5rem));
    padding: 1rem 1rem 1rem 1.1rem;
    border-radius: 14px;
    border: 1px solid rgba(26, 74, 138, 0.14);
    background: #fff;
    color: #1e293b;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
    animation: dashboardStaffBannerIn 0.35s ease;
}
.dashboard-staff-banner[hidden] { display: none !important; }
.dashboard-staff-banner-icon {
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.dashboard-staff-banner--info .dashboard-staff-banner-icon {
    background: rgba(51, 180, 227, 0.16);
    color: #1a468a;
}
.dashboard-staff-banner--warning .dashboard-staff-banner-icon {
    background: rgba(245, 158, 11, 0.16);
    color: #b45309;
}
.dashboard-staff-banner--success .dashboard-staff-banner-icon {
    background: rgba(13, 148, 136, 0.16);
    color: #0f766e;
}
.dashboard-staff-banner-body { min-width: 0; flex: 1; }
.dashboard-staff-banner-title {
    margin: 0 0 0.35rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}
.dashboard-staff-banner-message {
    margin: 0;
    font-size: 0.86rem;
    line-height: 1.45;
    color: #475569;
}
.dashboard-staff-banner-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.65rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #1a468a;
    text-decoration: none;
}
.dashboard-staff-banner-link:hover { color: #133a6f; text-decoration: underline; }
.dashboard-staff-banner-close {
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.dashboard-staff-banner-close:hover {
    background: rgba(15, 23, 42, 0.06);
    color: #0f172a;
}
@keyframes dashboardStaffBannerIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 768px) {
    .dashboard-staff-banner {
        right: 1rem;
        left: 1rem;
        bottom: 1rem;
        width: auto;
    }
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var banner = document.getElementById('dashboardStaffBanner');
    if (!banner) return;

    var storageKey = 'geminia-dashboard-staff-banner-dismissed-' + (banner.dataset.bannerId || 'default');
    if (window.localStorage.getItem(storageKey) === '1') return;

    banner.hidden = false;

    var dismissButton = banner.querySelector('[data-dashboard-staff-banner-dismiss]');
    if (!dismissButton) return;

    dismissButton.addEventListener('click', function () {
        banner.hidden = true;
        try {
            window.localStorage.setItem(storageKey, '1');
        } catch (error) {}
    });
});
</script>
@endpush
@endif
