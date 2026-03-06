@extends('layouts.feedback')

@section('title', 'Thank You')

@section('content')
<div class="feedback-card mx-auto text-center">
    <div class="mb-3">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
    </div>
    <h1 class="h4 fw-bold mb-2">Thank You!</h1>
    <p class="text-muted mb-0">
        @if($already_submitted ?? false)
            Your feedback has been received. We appreciate you taking the time to help us improve.
        @else
            Thank you for your feedback. We value your opinion and will use it to serve you better.
        @endif
    </p>
</div>
@endsection
