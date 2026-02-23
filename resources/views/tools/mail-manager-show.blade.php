@extends('layouts.app')

@section('title', $email->subject ?? 'Email')

@section('content')
<div class="page-header mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('tools.mail-manager') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="page-title mb-0">{{ Str::limit($email->subject ?? '(No subject)', 80) }}</h1>
            <p class="page-subtitle mb-0 text-muted small">
                {{ $email->from_name ? $email->from_name . ' &lt;' . $email->from_address . '&gt;' : $email->from_address }}
                · {{ $email->date ? \Carbon\Carbon::parse($email->date)->format('M d, Y H:i') : '' }}
            </p>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        @if($email->to_addresses)
            <p class="mb-2"><strong>To:</strong> {{ $email->to_addresses }}</p>
        @endif
        @if($email->cc_addresses)
            <p class="mb-2"><strong>Cc:</strong> {{ $email->cc_addresses }}</p>
        @endif
        @if($email->has_attachments)
            <p class="mb-2 text-muted small"><i class="bi bi-paperclip"></i> This email has attachments (stored on mail server)</p>
        @endif
        <hr>
        <div class="email-body">
            @if($email->body_html)
                {!! $email->body_html !!}
            @else
                <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;">{{ $email->body_text ?? 'No content' }}</pre>
            @endif
        </div>
    </div>
</div>
@endsection
