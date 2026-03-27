@extends('layouts.app')

@section('title', 'Protect PDF')

@section('content')
<nav class="breadcrumb-nav mb-3">
    <a href="{{ route('tools') }}" class="text-muted small text-decoration-none">Tools</a>
    <span class="text-muted mx-2">/</span>
    <span class="text-dark small fw-semibold">Protect PDF</span>
</nav>

<div class="page-header mb-4">
    <h1 class="app-page-title mb-1">Protect PDF</h1>
    <p class="app-page-sub mb-0">Upload a PDF and set the password people will use to open it. Encryption (AES-256 when your server allows it, otherwise AES-128) and owner security are applied automatically—no extra choices needed.</p>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="app-card p-4 col-lg-8 col-xl-6 mx-lg-auto">
    <form method="POST" action="{{ route('tools.pdf-protect.process') }}" enctype="multipart/form-data" id="pdfProtectForm">
        @csrf
        <div class="mb-4">
            <label class="form-label fw-semibold">PDF file <span class="text-danger">*</span></label>
            <input type="file" name="pdf" class="form-control @error('pdf') is-invalid @enderror" accept=".pdf,application/pdf" required>
            @error('pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="small text-muted mb-0 mt-1">Max {{ number_format((int) config('pdf-protect.max_upload_kb', 51200)) }} KB. Your original file is not stored.</p>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">Password to open the PDF <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="user_password" id="user_password" class="form-control @error('user_password') is-invalid @enderror" required autocomplete="new-password" minlength="8" maxlength="128" value="{{ old('user_password') }}" placeholder="At least 8 characters">
                <button type="button" class="btn btn-outline-secondary" id="togglePw" title="Show or hide password"><i class="bi bi-eye" id="togglePwIcon"></i></button>
                <button type="button" class="btn btn-outline-primary" id="suggestPw" title="Fill a strong random password">Suggest</button>
            </div>
            @error('user_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <p class="small text-muted mb-0 mt-1">Share this password with recipients separately (e.g. phone)—not in the same email as the file.</p>
        </div>
        <button type="submit" class="btn btn-primary-custom btn-lg">
            <i class="bi bi-download me-2"></i>Download protected PDF
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var pw = document.getElementById('user_password');
    var suggest = document.getElementById('suggestPw');
    var toggle = document.getElementById('togglePw');
    var icon = document.getElementById('togglePwIcon');
    var alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    suggest && suggest.addEventListener('click', function() {
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
        var s = '';
        for (var i = 0; i < 16; i++) s += alphabet.charAt((bytes[i] || 0) % alphabet.length);
        pw.value = s;
        pw.setAttribute('type', 'text');
        if (icon) { icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); }
    });

    toggle && toggle.addEventListener('click', function() {
        if (!pw) return;
        var t = pw.getAttribute('type') === 'password' ? 'text' : 'password';
        pw.setAttribute('type', t);
        if (icon) {
            icon.classList.toggle('bi-eye', t === 'password');
            icon.classList.toggle('bi-eye-slash', t !== 'password');
        }
    });
});
</script>
@endsection
