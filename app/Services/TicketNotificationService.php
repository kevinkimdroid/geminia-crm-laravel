<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends email notifications for ticket creation and SLA violations.
 */
class TicketNotificationService
{
    public function __construct(
        protected CrmService $crm
    ) {}

    /**
     * Send notification when a new ticket is created.
     * Notifies assigned user and optionally the contact.
     */
    public function sendTicketCreatedNotification(
        int $ticketId,
        string $ticketNo,
        string $title,
        int $assignedToUserId,
        ?int $contactId = null,
        ?string $policyNumber = null,
        bool $notifyContact = false,
        ?string $customMessageToClient = null
    ): array {
        $config = config('tickets.notify_on_creation', []);
        if (empty($config['enabled'])) {
            Log::info('TicketNotificationService: notify_on_creation disabled', ['ticket' => $ticketNo]);
            return ['assigned_sent' => false, 'contact_sent' => false];
        }

        $results = ['assigned_sent' => false, 'contact_sent' => false];

        // Notify assigned user
        if (! empty($config['notify_assigned_user'])) {
            $user = DB::connection('vtiger')->table('vtiger_users')
                ->where('id', $assignedToUserId)
                ->select('id', 'email1', 'first_name', 'last_name', 'user_name')
                ->first();
            if (! $user) {
                Log::warning('TicketNotificationService: assigned user not found', ['ticket' => $ticketNo, 'user_id' => $assignedToUserId]);
            } elseif (empty(trim($user->email1 ?? ''))) {
                Log::warning('TicketNotificationService: assigned user has no email', ['ticket' => $ticketNo, 'user_id' => $assignedToUserId]);
            } else {
                $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
                $subject = "New ticket assigned: {$ticketNo} — {$title}";
                $body = $this->buildTicketCreatedBody($ticketId, $ticketNo, $title, $userName, true, $policyNumber);
                if ($this->send($user->email1, $userName, $subject, $body)) {
                    $results['assigned_sent'] = true;
                }
            }
        }

        // Notify contact when $notifyContact is true (form checkbox on create ticket page).
        // Requires contact to have an email address on file.
        if ($notifyContact && $contactId) {
            $contact = DB::connection('vtiger')->table('vtiger_contactdetails')
                ->where('contactid', $contactId)
                ->select('contactid', 'email', 'firstname', 'lastname')
                ->first();
            if (! $contact) {
                Log::debug('TicketNotificationService: contact not found', ['ticket' => $ticketNo, 'contact_id' => $contactId]);
            } elseif (empty(trim($contact->email ?? ''))) {
                Log::debug('TicketNotificationService: contact has no email', ['ticket' => $ticketNo, 'contact_id' => $contactId]);
            } else {
                $contactName = trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) ?: 'Customer';
                $subject = 'Your support request — Ticket ' . $ticketNo;
                $body = $this->buildTicketCreatedBody($ticketId, $ticketNo, $title, $contactName, false, $policyNumber, $customMessageToClient);
                if ($this->send($contact->email, $contactName, $subject, $body)) {
                    $results['contact_sent'] = true;
                }
            }
        }

        if (! $results['assigned_sent'] && ! $results['contact_sent']) {
            Log::info('TicketNotificationService: no email sent', ['ticket' => $ticketNo, 'assigned_to' => $assignedToUserId, 'contact_id' => $contactId]);
        }

        return $results;
    }

    /**
     * Send notification when a ticket is reassigned to a user.
     */
    public function sendTicketAssignedNotification(
        int $ticketId,
        string $ticketNo,
        string $title,
        int $assignedToUserId
    ): bool {
        $config = config('tickets.notify_on_reassignment', []);
        if (empty($config['enabled'])) {
            Log::info('TicketNotificationService: notify_on_reassignment disabled', ['ticket' => $ticketNo]);
            return false;
        }

        $user = DB::connection('vtiger')->table('vtiger_users')
            ->where('id', $assignedToUserId)
            ->select('id', 'email1', 'first_name', 'last_name', 'user_name')
            ->first();
        if (! $user) {
            Log::warning('TicketNotificationService: assigned user not found', ['ticket' => $ticketNo, 'user_id' => $assignedToUserId]);
            return false;
        }
        if (empty(trim($user->email1 ?? ''))) {
            Log::warning('TicketNotificationService: assigned user has no email', ['ticket' => $ticketNo, 'user_id' => $assignedToUserId]);
            return false;
        }

        $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
        $subject = "Ticket assigned to you: {$ticketNo} — {$title}";
        $body = $this->buildTicketAssignedBody($ticketId, $ticketNo, $title, $userName);

        return $this->send($user->email1, $userName, $subject, $body);
    }

    protected function buildTicketAssignedBody(int $ticketId, string $ticketNo, string $title, string $recipientName): string
    {
        $appName = config('app.name', 'Geminia Life');
        $ticketUrl = rtrim(config('app.url', ''), '/') . '/tickets/' . $ticketId;

        return "Hello {$recipientName},\n\n"
            . "A support ticket has been assigned to you.\n\n"
            . "Ticket: {$ticketNo}\n"
            . "Title: {$title}\n\n"
            . "View ticket: {$ticketUrl}\n\n"
            . "Kind regards,\n{$appName}";
    }

    /**
     * Send SLA violation reminder emails to assigned users (and optional CC).
     * Uses cache to avoid spamming: at most once per ticket per 24 hours.
     */
    public function sendSlaViolationReminders(): array
    {
        $config = config('tickets.sla_violation_reminders', []);
        if (empty($config['enabled'])) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $sla = app(TicketSlaService::class);
        $tickets = $sla->getBrokenSlaTickets(50);
        $sent = 0;
        $skipped = 0;

        foreach ($tickets as $t) {
            $cacheKey = 'sla_reminder_sent_' . $t->ticketid;
            if (\Illuminate\Support\Facades\Cache::get($cacheKey)) {
                $skipped++;
                continue;
            }

            $ownerId = $t->smownerid ?? null;
            if (! $ownerId) {
                continue;
            }

            $user = DB::connection('vtiger')->table('vtiger_users')
                ->where('id', $ownerId)
                ->select('id', 'email1', 'first_name', 'last_name', 'user_name')
                ->first();
            if (! $user || empty(trim($user->email1 ?? ''))) {
                $skipped++;
                continue;
            }

            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
            $hoursOver = $t->hours_overdue ?? 0;
            $subject = "SLA Violation: Ticket {$t->ticket_no} is {$hoursOver}h overdue";
            $body = $this->buildSlaViolationBody($t, $userName);

            if ($this->send($user->email1, $userName, $subject, $body)) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHours(24));
                $sent++;
            }

            // Optional: CC supervisors
            $cc = $config['cc_emails'] ?? [];
            if (! empty($cc)) {
                foreach ($cc as $ccEmail) {
                    if (trim($ccEmail) !== '' && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                        $this->send($ccEmail, null, $subject, $body);
                    }
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    protected function buildTicketCreatedBody(int $ticketId, string $ticketNo, string $title, string $recipientName, bool $isAssignee, ?string $policyNumber, ?string $customMessageToClient = null): string
    {
        $appName = config('app.name', 'Geminia Life');
        $ticketUrl = rtrim(config('app.url', ''), '/') . '/tickets/' . $ticketId;

        if ($isAssignee) {
            return "Hello {$recipientName},\n\n"
                . "A new support ticket has been assigned to you.\n\n"
                . "Ticket: {$ticketNo}\n"
                . "Title: {$title}\n"
                . ($policyNumber ? "Policy: {$policyNumber}\n" : '')
                . "\nView ticket: {$ticketUrl}\n\n"
                . "Kind regards,\n{$appName}";
        }

        $defaultMessage = 'Our team will respond as soon as possible.';
        $bodyMessage = (trim($customMessageToClient ?? '') !== '') ? trim($customMessageToClient) : $defaultMessage;

        return "Hello {$recipientName},\n\n"
            . "Thank you for contacting us. We have created a support ticket for your request.\n\n"
            . "Ticket number: {$ticketNo}\n"
            . "Summary: {$title}\n\n"
            . $bodyMessage . "\n\n"
            . "Kind regards,\n{$appName}";
    }

    protected function buildSlaViolationBody(object $ticket, string $recipientName): string
    {
        $appName = config('app.name', 'Geminia Life');
        $hoursOver = $ticket->hours_overdue ?? 0;
        $contactName = trim(($ticket->contact_first ?? '') . ' ' . ($ticket->contact_last ?? '')) ?: 'N/A';

        return "Hello {$recipientName},\n\n"
            . "This is a reminder that the following ticket has exceeded its SLA (Turn-around Time):\n\n"
            . "Ticket: {$ticket->ticket_no}\n"
            . "Title: {$ticket->title}\n"
            . "Status: {$ticket->status}\n"
            . "Category: {$ticket->category}\n"
            . "Contact: {$contactName}\n"
            . "Hours overdue: {$hoursOver}\n\n"
            . "Please take action to resolve this ticket.\n\n"
            . "Kind regards,\n{$appName}";
    }

    /**
     * Send feedback request email to contact when a ticket is closed.
     * Includes a signed link valid for 7 days.
     */
    public function sendFeedbackRequestEmail(int $ticketId, string $ticketNo, int $contactId): bool
    {
        $config = config('tickets.feedback_request', []);
        if (empty($config['enabled'])) {
            return false;
        }

        $contact = DB::connection('vtiger')->table('vtiger_contactdetails')
            ->where('contactid', $contactId)
            ->select('contactid', 'email', 'firstname', 'lastname')
            ->first();
        if (! $contact || empty(trim($contact->email ?? ''))) {
            Log::debug('TicketNotificationService: feedback request skipped, contact has no email', [
                'ticket' => $ticketNo,
                'contact_id' => $contactId,
            ]);
            return false;
        }

        $publicUrl = trim((string) ($config['public_url'] ?? ''));
        if ($publicUrl !== '') {
            $path = trim((string) ($config['public_path'] ?? 'crm-client-feedback'), '/');
            $expires = now()->addDays(7)->getTimestamp();
            $params = ['ticket' => $ticketId, 'expires' => $expires];
            $signed = rtrim($publicUrl, '/') . '/' . $path . '?' . http_build_query($params);
            $params['signature'] = hash_hmac('sha256', $signed, config('app.key'));
            $feedbackUrl = rtrim($publicUrl, '/') . '/' . $path . '?' . http_build_query($params);
        } else {
            $feedbackUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'feedback.form',
                now()->addDays(7),
                ['ticket' => $ticketId]
            );
        }

        $contactName = trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) ?: 'Customer';
        $appName = config('app.name', 'Geminia Life');
        $subject = 'How was your experience? — Ticket ' . $ticketNo . ' closed';
        $body = "Hello {$contactName},\n\n"
            . "Your support request (Ticket {$ticketNo}) has been resolved and closed.\n\n"
            . "We would appreciate it if you could take a moment to rate your experience:\n\n"
            . "Were you happy with our service?\n"
            . $feedbackUrl . "\n\n"
            . "Thank you for choosing {$appName}.\n\n"
            . "Kind regards,\n{$appName}";

        $sent = $this->send($contact->email, $contactName, $subject, $body);
        if ($sent) {
            Log::info('TicketNotificationService: feedback request sent', [
                'ticket' => $ticketNo,
                'contact_id' => $contactId,
            ]);
        }
        return $sent;
    }

    /**
     * When a contact submits feedback, email it to life@geminialife.co.ke (or configured address).
     */
    public function sendFeedbackReceivedNotification(int $ticketId, string $ticketNo, string $title, string $contactName, string $rating, ?string $comment): bool
    {
        $config = config('tickets.feedback_request', []);
        $to = trim((string) ($config['notify_email'] ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('TicketNotificationService: feedback notify email not configured or invalid', [
                'notify_email' => $to ?: '(empty)',
                'ticket' => $ticketNo,
            ]);
            return false;
        }

        $ratingLabel = $rating === 'happy' ? 'Happy' : 'Not satisfied';
        $appName = config('app.name', 'Geminia Life');
        $ticketUrl = rtrim(config('app.url', ''), '/') . '/tickets/' . $ticketId;

        $subject = "Client feedback: {$ticketNo} — {$ratingLabel}";
        $body = "Feedback received for ticket {$ticketNo}\n\n"
            . "Title: {$title}\n"
            . "Contact: {$contactName}\n"
            . "Rating: {$ratingLabel}\n";
        if (! empty(trim($comment ?? ''))) {
            $body .= "\nComment:\n" . trim($comment) . "\n";
        }
        $body .= "\nView ticket: {$ticketUrl}\n\nKind regards,\n{$appName}";

        Log::info('TicketNotificationService: sending feedback notification', ['to' => $to, 'ticket' => $ticketNo]);

        $sent = $this->send($to, null, $subject, $body);
        if ($sent) {
            Log::info('TicketNotificationService: feedback notification sent to ' . $to, ['ticket' => $ticketNo]);
        } else {
            Log::warning('TicketNotificationService: feedback notification failed to send', ['to' => $to, 'ticket' => $ticketNo]);
        }
        return $sent;
    }

    protected function send(string $to, ?string $toName, string $subject, string $body): bool
    {
        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            if ($graph->sendMail($to, $toName, $subject, $body, false)) {
                return true;
            }
            Log::warning('TicketNotificationService: Graph send failed, falling back to Laravel Mail', ['to' => $to]);
        }

        $mailer = config('mail.default');
        if ($mailer === 'log') {
            Log::warning('TicketNotificationService: MAIL_MAILER=log – email will not be delivered. Set MAIL_MAILER=smtp for actual delivery.');
        }

        try {
            $from = config('mail.from.address', config('email-service.sender', 'life@geminialife.co.ke'));
            $fromName = config('mail.from.name', config('app.name'));
            Mail::raw($body, function ($message) use ($to, $toName, $subject, $from, $fromName) {
                $message->to($to, $toName)->from($from, $fromName)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('TicketNotificationService: send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
