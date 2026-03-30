<div class="ticket-dropdown-settings">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h5 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width:2.5rem;height:2.5rem">
                    <i class="bi bi-ui-checks"></i>
                </span>
                Create ticket form — dropdowns
            </h5>
            <p class="text-muted small mb-0" style="max-width: 42rem">
                Edit the lists below so staff see the right <strong>Category</strong> and <strong>Ticket Source</strong> choices.
                Each line is one option. Values are combined with vtiger (existing tickets / picklists) and with
                <code>TICKET_CATEGORIES</code> / <code>TICKET_SOURCES</code> in <code>.env</code> — nothing you type here replaces the CRM; it adds to the menu.
            </p>
        </div>
        <a href="{{ route('tickets.create') }}" class="btn btn-outline-primary btn-sm shrink-0">
            <i class="bi bi-plus-circle me-1"></i> Open create ticket
        </a>
    </div>

    @if (! \App\Models\CrmSetting::tableExists())
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Database not ready.</strong> Run <code>php artisan migrate</code> to create the <code>crm_settings</code> table, then reload this page.
            </div>
        </div>
    @else
        <div class="app-card overflow-hidden mb-4 border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
                <i class="bi bi-eye text-primary"></i>
                <h6 class="mb-0 fw-bold">What appears in the dropdowns now</h6>
                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-normal ms-1">after save, allow up to 5 minutes or save again to refresh cache</span>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <p class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-tags me-1"></i> Category
                            <span class="badge rounded-pill bg-light text-dark ms-1">{{ count($previewCategories ?? []) }}</span>
                        </p>
                        <div class="ticket-preview-chips border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 11rem; overflow-y: auto;">
                            @forelse($previewCategories ?? [] as $c)
                                <span class="badge rounded-pill bg-white border text-dark fw-normal me-1 mb-1">{{ $c }}</span>
                            @empty
                                <span class="text-muted small">No categories resolved.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <p class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-broadcast me-1"></i> Ticket source
                            <span class="badge rounded-pill bg-light text-dark ms-1">{{ count($previewSources ?? []) }}</span>
                        </p>
                        <div class="ticket-preview-chips border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 11rem; overflow-y: auto;">
                            @forelse($previewSources ?? [] as $s)
                                <span class="badge rounded-pill bg-white border text-dark fw-normal me-1 mb-1">{{ $s }}</span>
                            @empty
                                <span class="text-muted small">No sources resolved.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('settings.ticket-dropdowns.update') }}" method="POST" id="ticketDropdownsForm" class="mb-4">
            @csrf
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="app-card h-100 border-0 shadow-sm">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i class="bi bi-tags-fill text-primary"></i> Extra categories
                            </h6>
                            <p class="text-muted small mb-0 mt-2">One category per line. Use the toolbar to tidy the list before saving.</p>
                        </div>
                        <div class="p-4">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="small text-muted"><span id="catLineCount">0</span> non-empty lines</span>
                                <div class="ms-auto btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_categories_custom" data-action="sort" title="Sort A–Z">A–Z</button>
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_categories_custom" data-action="dedupe" title="Remove duplicate lines">Dedupe</button>
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_categories_custom" data-action="trim" title="Trim spaces on each line">Trim</button>
                                </div>
                            </div>
                            <textarea name="ticket_categories_custom" id="ticket_categories_custom" class="form-control font-monospace ticket-dropdown-textarea" rows="12" placeholder="Group Life - Claim&#10;Premium Adjustment&#10;Other request">{{ old('ticket_categories_custom', $ticketCategoriesCustom ?? '') }}</textarea>
                            @error('ticket_categories_custom')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="app-card h-100 border-0 shadow-sm">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i class="bi bi-broadcast-pin text-primary"></i> Extra ticket sources
                            </h6>
                            <p class="text-muted small mb-0 mt-2">One source per line (e.g. WhatsApp, Branch walk-in). These merge with CRM history and env defaults.</p>
                        </div>
                        <div class="p-4">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="small text-muted"><span id="srcLineCount">0</span> non-empty lines</span>
                                <div class="ms-auto btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_sources_custom" data-action="sort" title="Sort A–Z">A–Z</button>
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_sources_custom" data-action="dedupe" title="Remove duplicate lines">Dedupe</button>
                                    <button type="button" class="btn btn-outline-secondary js-textarea-tool" data-target="ticket_sources_custom" data-action="trim" title="Trim spaces on each line">Trim</button>
                                </div>
                            </div>
                            <textarea name="ticket_sources_custom" id="ticket_sources_custom" class="form-control font-monospace ticket-dropdown-textarea" rows="12" placeholder="CRM&#10;Phone&#10;WhatsApp&#10;Partner portal">{{ old('ticket_sources_custom', $ticketSourcesCustom ?? '') }}</textarea>
                            @error('ticket_sources_custom')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2-circle me-1"></i> Save dropdown lists
                </button>
                <p class="text-muted small mb-0">Edits apply for all users who can create tickets. Cache clears automatically when you save.</p>
            </div>
        </form>
    @endif
</div>

@push('scripts')
<script>
(function () {
    function linesFromText(val) {
        return (val || '').split(/\r\n|\r|\n/).map(function (l) { return l.trim(); }).filter(Boolean);
}
    function updateLineCounts() {
        var cat = document.getElementById('ticket_categories_custom');
        var src = document.getElementById('ticket_sources_custom');
        var cEl = document.getElementById('catLineCount');
        var sEl = document.getElementById('srcLineCount');
        if (cEl && cat) cEl.textContent = String(linesFromText(cat.value).length);
        if (sEl && src) sEl.textContent = String(linesFromText(src.value).length);
    }
    function applyTool(textareaId, action) {
        var ta = document.getElementById(textareaId);
        if (!ta) return;
        var lines = linesFromText(ta.value);
        if (action === 'sort') {
            lines.sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); });
        } else if (action === 'dedupe') {
            var seen = {};
            lines = lines.filter(function (l) {
                var k = l.toLowerCase();
                if (seen[k]) return false;
                seen[k] = true;
                return true;
            });
        } else if (action === 'trim') {
            lines = (ta.value || '').split(/\r\n|\r|\n/).map(function (l) { return l.trim(); }).filter(Boolean);
        }
        ta.value = lines.join('\n');
        updateLineCounts();
    }
    document.querySelectorAll('.js-textarea-tool').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyTool(btn.getAttribute('data-target'), btn.getAttribute('data-action'));
        });
    });
    ['ticket_categories_custom', 'ticket_sources_custom'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateLineCounts);
    });
    updateLineCounts();
})();
</script>
@endpush
