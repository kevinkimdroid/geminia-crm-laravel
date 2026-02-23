@extends('layouts.app')

@section('title', ($template ? 'Edit' : 'Add') . ' Email Template')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('tools.email-templates') }}">Email Templates</a></li>
                <li class="breadcrumb-item active">{{ $template ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
        <h1 class="page-title">{{ $template ? 'Edit' : 'Add' }} Email Template</h1>
        <p class="page-subtitle mb-0">Create reusable email templates for your communications.</p>
    </div>
    <a href="{{ route('tools.email-templates') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <ul class="mb-0">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form action="{{ $template ? route('tools.email-templates.update', $template) : route('tools.email-templates.store') }}" method="POST">
    @csrf
    @if($template) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Template Name</label>
                    <input type="text" name="template_name" class="form-control" value="{{ old('template_name', optional($template)->template_name ?? '') }}" required placeholder="e.g. Birthdays">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subject</label>
                    <input type="text" name="subject" class="form-control" value="{{ old('subject', optional($template)->subject ?? '') }}" required placeholder="e.g. Happy Birthday">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', optional($template)->description ?? '') }}" placeholder="e.g. Sending messages to customers on their birthday">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Module Name</label>
                    <select name="module_name" class="form-select">
                        <option value="">— Select module —</option>
                        @foreach ($modules ?? [] as $mod)
                            <option value="{{ $mod }}" {{ old('module_name', optional($template)->module_name ?? '') == $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Body</label>
                    <textarea name="body" class="form-control font-monospace" rows="12" placeholder="Email content (HTML or plain text). Use placeholders like @{{firstname}}, @{{lastname}}...">{{ old('body', optional($template)->body ?? '') }}</textarea>
                    <p class="small text-muted mt-1">Use placeholders for personalization: &#123;&#123;firstname&#125;&#125;, &#123;&#123;lastname&#125;&#125;, &#123;&#123;email&#125;&#125;, etc.</p>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent border-top">
            <button type="submit" class="btn btn-primary-custom">
                <i class="bi bi-check-lg me-2"></i>{{ $template ? 'Update' : 'Create' }} Template
            </button>
            <a href="{{ route('tools.email-templates') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </div>
</form>
@endsection
