<?php

namespace App\Http\Controllers;

use App\Models\TicketFeedback;
use App\Services\CrmService;
use App\Services\TicketNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(
        protected CrmService $crm,
        protected TicketNotificationService $notifier
    ) {}

    /**
     * Show the feedback form or process submission (requires signed URL).
     * GET: show form. POST: submit feedback. Form posts to same signed URL.
     */
    public function form(Request $request): View|RedirectResponse
    {
        $request->validate(['ticket' => 'required|integer']);

        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->with('error', 'This feedback link has expired or is invalid.');
        }

        $ticketId = (int) $request->get('ticket');
        $ticket = $this->crm->getTicket($ticketId);
        if (! $ticket) {
            return redirect()->route('login')->with('error', 'Ticket not found.');
        }

        $status = $ticket->status ?? '';
        if ($status !== 'Closed') {
            return redirect()->route('login')->with('info', 'This ticket has not been closed yet.');
        }

        $contactId = (int) ($ticket->contact_id ?? 0);
        if (! $contactId) {
            return redirect()->route('login')->with('error', 'Invalid ticket.');
        }

        $existing = TicketFeedback::where('ticket_id', $ticketId)->where('contact_id', $contactId)->first();
        if ($existing) {
            return redirect()->route('feedback.thank-you')->with('submitted', true);
        }

        // POST: process submission
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'rating' => 'required|in:happy,not_happy',
                'comment' => 'nullable|string|max:2000',
            ]);
            try {
                TicketFeedback::create([
                    'ticket_id' => $ticketId,
                    'contact_id' => $contactId,
                    'rating' => $validated['rating'],
                    'comment' => trim((string) ($validated['comment'] ?? '')),
                ]);
                Log::info('Ticket feedback submitted', [
                    'ticket_id' => $ticketId,
                    'contact_id' => $contactId,
                    'rating' => $validated['rating'],
                ]);

                // Email feedback to life@geminialife.co.ke
                $ticketNo = $ticket->ticket_no ?? 'TT' . $ticketId;
                $title = $ticket->title ?? 'Support request';
                $contact = $this->crm->getContact($contactId);
                $contactName = $contact ? trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) : 'Customer';
                if ($contactName === '') {
                    $contactName = 'Customer';
                }
                try {
                    $this->notifier->sendFeedbackReceivedNotification(
                        $ticketId,
                        $ticketNo,
                        $title,
                        $contactName,
                        $validated['rating'],
                        $validated['comment'] ?? null
                    );
                } catch (\Throwable $e) {
                    Log::warning('Feedback notify email failed', ['error' => $e->getMessage(), 'ticket' => $ticketNo]);
                }
            } catch (\Throwable $e) {
                Log::warning('Ticket feedback create failed', ['error' => $e->getMessage(), 'ticket_id' => $ticketId]);
                return redirect()->back()->with('error', 'Failed to save feedback. Please try again.');
            }
            return redirect()->route('feedback.thank-you')->with('submitted', true);
        }

        // GET: show form
        $ticketNo = $ticket->ticket_no ?? 'TT' . $ticketId;
        $title = $ticket->title ?? 'Support request';

        return view('feedback.form', [
            'ticket' => $ticket,
            'ticketNo' => $ticketNo,
            'title' => $title,
            'formAction' => $request->fullUrl(),
        ]);
    }

    /**
     * Thank you page after submitting feedback.
     */
    public function thankYou(Request $request): View
    {
        $alreadySubmitted = $request->session()->get('submitted', false);

        return view('feedback.thank-you', ['already_submitted' => (bool) $alreadySubmitted]);
    }
}
