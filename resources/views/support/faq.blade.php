@extends('layouts.app')

@section('title', 'FAQ')

@section('content')
<div class="page-header">
    <h1 class="page-title">FAQ</h1>
    <p class="page-subtitle">Frequently asked questions and knowledge base.</p>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card faq-coming-card overflow-hidden">
            <div class="card-body p-5 text-center">
                <div class="faq-coming-icon">
                    <i class="bi bi-question-circle-fill"></i>
                </div>
                <h4 class="mt-4 mb-2">Knowledge Base Coming Soon</h4>
                <p class="text-muted mb-4">We're building a comprehensive FAQ and help center. You'll be able to browse articles, search for answers, and get instant support.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="{{ route('tickets.index') }}" class="btn btn-primary-custom"><i class="bi bi-ticket-perforated me-1"></i>Open a Ticket</a>
                    <a href="{{ route('support') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Support</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.faq-coming-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.faq-coming-icon { width: 100px; height: 100px; margin: 0 auto; background: linear-gradient(135deg, var(--primary-light, rgba(14, 67, 133, 0.12)) 0%, rgba(14, 67, 133, 0.06) 100%); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--primary, #0E4385); }
</style>
@endsection
