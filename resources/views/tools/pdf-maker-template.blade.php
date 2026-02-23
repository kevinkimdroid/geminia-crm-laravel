@extends('layouts.app')

@section('title', 'Edit Template — ' . ($module['name'] ?? 'PDF Maker'))

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">PDF Maker</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('tools.pdf-maker') }}">PDF Maker</a></li>
                <li class="breadcrumb-item active">{{ $module['name'] ?? 'Template' }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('tools.pdf-maker.preview', $module['slug']) }}" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i> Preview PDF
        </a>
        <a href="{{ route('tools.pdf-maker') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('tools.pdf-maker.template.store', $module['slug']) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="name" value="Default">
    <div class="row g-4">
        {{-- Left: Template informations --}}
        <div class="col-lg-3">
            <div class="card pdf-tpl-card">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Template informations</h6>
                    <dl class="mb-0 small">
                        <dt class="text-muted mb-1">Description</dt>
                        <dd class="mb-2">{{ $module['description'] ?? '—' }}</dd>
                        <dt class="text-muted mb-1">Module</dt>
                        <dd class="mb-0">{{ $module['name'] ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Company & Logo --}}
            <div class="card pdf-tpl-card mt-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Company details</h6>
                    @if($template?->logo_path)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $template->logo_path) }}" alt="Logo" class="img-fluid rounded border mb-2" style="max-height: 50px;">
                            <form action="{{ route('tools.pdf-maker.logo.remove', $module['slug']) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-danger" onclick="return confirm('Remove logo?');">Remove</button>
                            </form>
                        </div>
                    @endif
                    <input type="file" name="logo" class="form-control form-control-sm mb-3" accept="image/*">
                    <div class="mb-2">
                        <input type="text" name="company_name" class="form-control form-control-sm" value="{{ old('company_name', $template?->company_name ?? '') }}" placeholder="Company name">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="tagline" class="form-control form-control-sm" value="{{ old('tagline', $template?->tagline ?? '') }}" placeholder="Tagline (optional)">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="company_address" class="form-control form-control-sm" value="{{ old('company_address', $template?->company_address ?? '') }}" placeholder="Address">
                    </div>
                    <div class="row g-1 mb-2">
                        <div class="col-6">
                            <input type="text" name="company_zip" class="form-control form-control-sm" value="{{ old('company_zip', $template?->company_zip ?? '') }}" placeholder="Zip">
                        </div>
                        <div class="col-6">
                            <input type="text" name="company_city" class="form-control form-control-sm" value="{{ old('company_city', $template?->company_city ?? '') }}" placeholder="City">
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="company_country" class="form-control form-control-sm" value="{{ old('company_country', $template?->company_country ?? '') }}" placeholder="Country">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="company_phone" class="form-control form-control-sm" value="{{ old('company_phone', $template?->company_phone ?? '') }}" placeholder="Phone">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="company_fax" class="form-control form-control-sm" value="{{ old('company_fax', $template?->company_fax ?? '') }}" placeholder="Fax">
                    </div>
                    <div class="mb-0">
                        <input type="text" name="company_website" class="form-control form-control-sm" value="{{ old('company_website', $template?->company_website ?? '') }}" placeholder="Website">
                    </div>
                </div>
            </div>

            <div class="card pdf-tpl-card mt-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Footer</h6>
                    <input type="text" name="footer_text" class="form-control form-control-sm mb-2" value="{{ old('footer_text', $template?->footer_text ?? '') }}" placeholder="Footer text">
                    <div class="form-check form-check-sm">
                        <input type="hidden" name="show_page_numbers" value="0">
                        <input type="checkbox" name="show_page_numbers" class="form-check-input" value="1" id="showPageNumbers" {{ old('show_page_numbers', $template?->show_page_numbers ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="showPageNumbers">Page numbers</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 mt-4">
                <i class="bi bi-check-lg me-2"></i>Save Template
            </button>
        </div>

        {{-- Right: Body / Header / Footer tabs with visual preview --}}
        <div class="col-lg-9">
            <ul class="nav nav-tabs pdf-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-body" type="button">Body</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-header" type="button">Header</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-footer" type="button">Footer</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-body">
                    <div class="card pdf-tpl-card pdf-preview-card">
                        <div class="card-body p-4 bg-white">
                            <p class="small text-muted mb-3">Your PDF will show this layout. Data placeholders are filled when you generate a PDF from a record.</p>
                            {{-- Visual template preview --}}
                            <div class="pdf-visual-preview">
                                <div class="row mb-4">
                                    <div class="col-6">
                                        <div class="text-muted small mb-2">Bill To</div>
                                        <div class="pdf-placeholder">$BILL_ACCOUNT$</div>
                                        <div class="pdf-placeholder">$BILL_STREET$</div>
                                        <div class="pdf-placeholder">$BILL_ZIP$ $BILL_CITY$</div>
                                        <div class="pdf-placeholder">$BILL_STATE$ $BILL_COUNTRY$</div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <div class="text-muted small mb-2">Company</div>
                                        <div class="pdf-placeholder">$COMPANY_NAME$</div>
                                        <div class="pdf-placeholder">$COMPANY_ADDRESS$</div>
                                        <div class="pdf-placeholder">$COMPANY_ZIP$ $COMPANY_CITY$</div>
                                        <div class="pdf-placeholder">$COMPANY_COUNTRY$</div>
                                        <div class="pdf-placeholder">$COMPANY_PHONE$ · $COMPANY_FAX$</div>
                                        <div class="pdf-placeholder">$COMPANY_WEBSITE$</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted small">Invoice Date:</span>
                                    <span class="pdf-placeholder">$INVOICE_DATE$</span>
                                </div>
                                <table class="table table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Description</th>
                                            <th class="text-end">List Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="pdf-placeholder">#PRODUCTBLOC_START#</span> $PRODUCT_TITLE$ <span class="pdf-placeholder">#PRODUCTBLOC_END#</span></td>
                                            <td class="text-end"><span class="pdf-placeholder">$PRODUCT_PRICE$</span> <span class="pdf-placeholder">$CURRENCY$</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="row mt-3">
                                    <div class="col-6"></div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal</span><span class="pdf-placeholder">$SUBTOTAL$</span></div>
                                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Tax</span><span class="pdf-placeholder">$TAX$</span></div>
                                        <div class="d-flex justify-content-between fw-bold"><span>Total</span><span class="pdf-placeholder">$TOTAL$</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-header">
                    <div class="card pdf-tpl-card">
                        <div class="card-body">
                            <p class="text-muted small mb-3">The header appears at the top of each page. It includes your logo and company name from the left panel.</p>
                            <div class="p-3 bg-light rounded">
                                <div class="d-flex align-items-center gap-3">
                                    @if($template?->logo_path)
                                        <img src="{{ asset('storage/' . $template->logo_path) }}" alt="Logo" style="max-height: 40px;">
                                    @else
                                        <div class="bg-white border rounded p-2 text-muted small">[Logo]</div>
                                    @endif
                                    <div>
                                        <strong>{{ $template?->company_name ?: 'Company Name' }}</strong>
                                        @if($template?->tagline)<div class="small text-muted">{{ $template->tagline }}</div>@endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-footer">
                    <div class="card pdf-tpl-card">
                        <div class="card-body">
                            <p class="text-muted small mb-3">The footer appears at the bottom of each page. Configure it in the left panel.</p>
                            <div class="p-3 bg-light rounded text-center small text-muted">
                                {{ $template?->footer_text ?: 'Footer text' }}
                                @if($template?->show_page_numbers) · Page 1 of 1 @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.pdf-tpl-card { border-radius: 12px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.pdf-tabs .nav-link { font-weight: 600; color: var(--text-muted); border: none; border-bottom: 2px solid transparent; padding: 0.75rem 1.25rem; }
.pdf-tabs .nav-link:hover { color: var(--primary); }
.pdf-tabs .nav-link.active { color: var(--primary); border-bottom-color: var(--primary); background: transparent; }
.pdf-preview-card { min-height: 400px; }
.pdf-visual-preview { font-size: 13px; }
.pdf-placeholder { color: var(--primary); font-family: monospace; font-size: 0.85em; background: rgba(14, 67, 133, 0.08); padding: 1px 4px; border-radius: 3px; }
</style>
@endsection
