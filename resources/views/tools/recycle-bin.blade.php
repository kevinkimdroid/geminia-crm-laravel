@extends('layouts.app')

@section('title', 'Recycle Bin')

@section('content')
<div class="page-header">
    <h1 class="page-title">Recycle Bin</h1>
    <p class="page-subtitle">View and restore deleted records.</p>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card overflow-hidden">
            <div class="card-body p-5 text-center">
                <div class="tools-coming-icon"><i class="bi bi-trash"></i></div>
                <h4 class="mt-4 mb-2">Recycle Bin Coming Soon</h4>
                <p class="text-muted mb-0">Restore or permanently delete deleted records.</p>
            </div>
        </div>
    </div>
</div>

<style>
.tools-coming-icon { width: 100px; height: 100px; margin: 0 auto; background: linear-gradient(135deg, var(--primary-light, rgba(14, 67, 133, 0.12)) 0%, rgba(14, 67, 133, 0.06) 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--primary, #0E4385); }
</style>
@endsection
