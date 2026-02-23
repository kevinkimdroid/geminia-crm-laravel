@extends('layouts.app')

@section('title', 'Create PDF — ' . ($module['name'] ?? 'PDF Maker'))

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('tools.pdf-maker') }}">PDF Maker</a></li>
                <li class="breadcrumb-item active">{{ $module['name'] ?? 'Create PDF' }}</li>
            </ol>
        </nav>
        <h1 class="page-title">Create PDF</h1>
        <p class="page-subtitle">Generate a PDF document from {{ $module['name'] ?? 'this module' }}.</p>
    </div>
    <a href="{{ route('tools.pdf-maker') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to PDF Maker
    </a>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card overflow-hidden">
            <div class="card-body p-5">
                <div class="text-center py-4">
                    <div class="pdf-maker-create-icon mb-3">
                        <i class="bi {{ $module['icon'] ?? 'bi-file-pdf' }}"></i>
                    </div>
                    <h4 class="mb-2">{{ $module['name'] }} PDF</h4>
                    <p class="text-muted mb-4">Select a record to generate a PDF, or create a new one.</p>
                    <p class="text-muted small">
                        When Invoice, Sales Order, Purchase Order, and Quote modules are available in your CRM,
                        you will be able to select records here and generate PDF documents.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pdf-maker-create-icon { width: 80px; height: 80px; margin: 0 auto; background: linear-gradient(135deg, var(--primary-light, rgba(14, 67, 133, 0.12)) 0%, rgba(14, 67, 133, 0.06) 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; color: var(--primary, #0E4385); }
</style>
@endsection
