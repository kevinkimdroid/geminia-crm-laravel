@push('head')
<style>
.reports-audit-page { font-family: 'Poppins', system-ui, sans-serif; }
.reports-breadcrumb { font-size: 0.8rem; }
.reports-breadcrumb a { color: var(--geminia-text-muted, #64748b); text-decoration: none; }
.reports-breadcrumb a:hover { color: var(--geminia-primary, #1A468A); }
.reports-breadcrumb-sep { color: #cbd5e1; margin: 0 0.35rem; }
.reports-breadcrumb-current { color: var(--geminia-text, #1e293b); font-weight: 500; }
.reports-audit-title { font-size: 1.5rem; font-weight: 700; color: var(--geminia-text, #1e293b); }
.reports-audit-subtitle { font-size: 0.9rem; color: var(--geminia-text-muted, #64748b); }
.reports-table-card {
    background: #fff; border: 1px solid rgba(26, 74, 138, 0.1); border-radius: 14px;
    box-shadow: 0 1px 3px rgba(26, 74, 138, 0.06); overflow: hidden;
}
.reports-table-card .table { margin-bottom: 0; }
.reports-table-card thead th {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
    color: #64748b; background: #f8fafc; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
}
.reports-table-card tbody td { padding: 1rem 1.25rem; font-size: 0.9rem; vertical-align: middle; }
.reports-table-card tbody tr:hover { background: rgba(26, 74, 138, 0.02); }
.reports-table-card tbody tr:not(:last-child) td { border-bottom: 1px solid #f1f5f9; }
.reports-meta { font-size: 0.8rem; color: #64748b; }
@media print {
    .no-print { display: none !important; }
    .reports-audit-page { padding: 0; }
    .reports-table-card { box-shadow: none; border: 1px solid #ddd; }
    .reports-header { margin-bottom: 1rem !important; padding-bottom: 0.5rem; border-bottom: 1px solid #ddd; }
    .reports-breadcrumb { display: block; }
    .reports-audit-title { font-size: 1.25rem; }
    .reports-meta { margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid #eee; font-size: 0.75rem; }
}
</style>
@endpush
