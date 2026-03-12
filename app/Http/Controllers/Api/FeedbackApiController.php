<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketFeedback;
use App\Services\CrmService;
use App\Services\TicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API for the standalone feedback app at geminialife.co.ke/feedback.
 * Validates signed feedback links and processes submissions.
 */
class FeedbackApiController extends Controller
{
    public function __construct(
        protected CrmService $crm,
        protected TicketNotificationService $notifier
    ) {}

    /**
     * Validate a feedback link. Used by the standalone app before showing the form.
     */
    public function validate(Request $request): JsonResponse
    {
        $ticket = (int) ($request->get('ticket') ?? 0);
        $expires = $request->get('expires');
        $signature = $request->get('signature');

        if (! $ticket || ! $expires || ! $signature) {
            return response()->json(['valid' => false, 'error' => 'Missing required parameters']);
        }

        $publicUrl = config('tickets.feedback_request.public_url', '');
        if ($publicUrl === '') {
            return response()->json(['valid' => false, 'error' => 'Feedback public URL not configured']);
        }

        $signedUrl = rtrim($publicUrl, '/') . '/feedback?' . http_build_query([
            'ticket' => $ticket,
            'expires' => $expires,
        ]);

        if (! hash_equals($signature, hash_hmac('sha256', $signedUrl, config('app.key')))) {
            return response()->json(['valid' => false, 'error' => 'Invalid signature']);
        }

        if (time() > (int) $expires) {
            return response()->json(['valid' => false, 'error' => 'Link has expired']);
        }

        $ticketData = $this->crm->getTicket($ticket);
        if (! $ticketData) {
            return response()->json(['valid' => false, 'error' => 'Ticket not found']);
        }

        $status = $ticketData->status ?? '';
        if ($status !== 'Closed') {
            return response()->json(['valid' => false, 'error' => 'Ticket has not been closed yet']);
        }

        $contactId = (int) ($ticketData->contact_id ?? 0);
        if (! $contactId) {
            return response()->json(['valid' => false, 'error' => 'Invalid ticket']);
        }

        $existing = TicketFeedback::where('ticket_id', $ticket)->where('contact_id', $contactId)->first();
        if ($existing) {
            return response()->json(['valid' => true, 'already_submitted' => true]);
        }

        return response()->json([
            'valid' => true,
            'ticket_no' => $ticketData->ticket_no ?? 'TT' . $ticket,
            'title' => $ticketData->title ?? 'Support request',
        ]);
    }

    /**
     * Submit feedback. Used by the standalone app after form submission.
     */
    public function submit(Request $request): JsonResponse
    {
        $ticket = (int) ($request->get('ticket') ?? 0);
        $expires = $request->get('expires');
        $signature = $request->get('signature');
        $rating = $request->get('rating');
        $comment = trim((string) ($request->get('comment') ?? ''));

        if (! $ticket || ! $expires || ! $signature || ! $rating) {
            return response()->json(['success' => false, 'error' => 'Missing required parameters']);
        }

        if (! in_array($rating, ['happy', 'not_happy'], true)) {
            return response()->json(['success' => false, 'error' => 'Invalid rating']);
        }

        $publicUrl = config('tickets.feedback_request.public_url', '');
        if ($publicUrl === '') {
            return response()->json(['success' => false, 'error' => 'Feedback public URL not configured']);
        }

        $signedUrl = rtrim($publicUrl, '/') . '/feedback?' . http_build_query([
            'ticket' => $ticket,
            'expires' => $expires,
        ]);

        if (! hash_equals($signature, hash_hmac('sha256', $signedUrl, config('app.key')))) {
            return response()->json(['success' => false, 'error' => 'Invalid signature']);
        }

        if (time() > (int) $expires) {
            return response()->json(['success' => false, 'error' => 'Link has expired']);
        }

        $ticketData = $this->crm->getTicket($ticket);
        if (! $ticketData) {
            return response()->json(['success' => false, 'error' => 'Ticket not found']);
        }

        $contactId = (int) ($ticketData->contact_id ?? 0);
        if (! $contactId) {
            return response()->json(['success' => false, 'error' => 'Invalid ticket']);
        }

        $existing = TicketFeedback::where('ticket_id', $ticket)->where('contact_id', $contactId)->first();
        if ($existing) {
            return response()->json(['success' => true, 'already_submitted' => true]);
        }

        try {
            TicketFeedback::create([
                'ticket_id' => $ticket,
                'contact_id' => $contactId,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            $ticketNo = $ticketData->ticket_no ?? 'TT' . $ticket;
            $title = $ticketData->title ?? 'Support request';
            $contact = $this->crm->getContact($contactId);
            $contactName = $contact ? trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) : 'Customer';
            if ($contactName === '') {
                $contactName = 'Customer';
            }

            try {
                $this->notifier->sendFeedbackReceivedNotification(
                    $ticket,
                    $ticketNo,
                    $title,
                    $contactName,
                    $rating,
                    $comment !== '' ? $comment : null
                );
            } catch (\Throwable $e) {
                Log::warning('Feedback notify email failed', ['error' => $e->getMessage(), 'ticket' => $ticketNo]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::warning('Ticket feedback create failed', ['error' => $e->getMessage(), 'ticket_id' => $ticket]);
            return response()->json(['success' => false, 'error' => 'Failed to save feedback']);
        }
    }
}
