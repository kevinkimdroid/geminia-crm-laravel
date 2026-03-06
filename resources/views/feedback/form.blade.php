@extends('layouts.feedback')

@section('title', 'Rate Your Experience')

@section('content')
<div class="feedback-card mx-auto">
    <h1 class="h4 fw-bold mb-2">How was your experience?</h1>
    <p class="text-muted small mb-4">Your support ticket <strong>{{ $ticketNo }}</strong> — {{ Str::limit($title, 50) }} — has been closed. We'd love to hear from you.</p>

    <form method="POST" action="{{ $formAction }}">
        @csrf

        <div class="mb-4">
            <label class="form-label fw-semibold">Were you happy with our service?</label>
            <div class="d-flex flex-column gap-2">
                <div class="form-check form-check-lg p-3 border rounded">
                    <input class="form-check-input" type="radio" name="rating" id="rating-happy" value="happy" required>
                    <label class="form-check-label fw-medium" for="rating-happy">
                        <i class="bi bi-emoji-smile text-success me-2"></i> Yes, I was happy with the service
                    </label>
                </div>
                <div class="form-check form-check-lg p-3 border rounded">
                    <input class="form-check-input" type="radio" name="rating" id="rating-not-happy" value="not_happy">
                    <label class="form-check-label fw-medium" for="rating-not-happy">
                        <i class="bi bi-emoji-frown text-warning me-2"></i> No, I was not satisfied
                    </label>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Additional comments <span class="text-muted">(optional)</span></label>
            <textarea name="comment" class="form-control" rows="3" placeholder="Any feedback you'd like to share...">{{ old('comment') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-send me-1"></i> Submit Feedback
        </button>
    </form>
</div>
@endsection
