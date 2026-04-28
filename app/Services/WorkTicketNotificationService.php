<?php

namespace App\Services;

use App\Models\WorkTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkTicketNotificationService
{
    public function __construct(
        protected MicrosoftGraphMailService $graph
    ) {}

    public function notifyCreated(WorkTicket $ticket): void
    {
        $subject = "New work ticket: {$ticket->ticket_no} — {$ticket->title}";
        $link = rtrim((string) config('app.url', ''), '/') . '/work-tickets/' . $ticket->id;

        $assignee = $this->getUser((int) $ticket->assignee_id);
        if ($assignee && ! empty($assignee->email1)) {
            $name = $this->fullName($assignee);
            $body = "Hello {$name},\n\n"
                . "A new work ticket has been assigned to you.\n\n"
                . "Ticket: {$ticket->ticket_no}\n"
                . "Title: {$ticket->title}\n"
                . "Priority: {$ticket->priority}\n"
                . "Status: {$ticket->status}\n"
                . "Due date: " . ($ticket->due_date?->toDateString() ?: 'N/A') . "\n\n"
                . "View ticket: {$link}\n\n"
                . "Kind regards,\n" . config('app.name', 'Geminia CRM');
            $this->send((string) $assignee->email1, $name, $subject, $body);
        }

        // Reporting manager immediate email disabled by request.
        // Manager visibility will be handled via scheduled reporting instead.
    }

    public function notifyClosed(WorkTicket $ticket, int $actorUserId): void
    {
        $subject = "Work ticket closed: {$ticket->ticket_no}";
        $link = rtrim((string) config('app.url', ''), '/') . '/work-tickets/' . $ticket->id;

        $creator = $this->getUser((int) $ticket->created_by);
        $actor = $this->getUser($actorUserId);
        $closedBy = $actor ? $this->fullName($actor) : ('User #' . $actorUserId);

        if ($creator && ! empty($creator->email1)) {
            $name = $this->fullName($creator);
            $body = "Hello {$name},\n\n"
                . "Work ticket {$ticket->ticket_no} has been closed.\n\n"
                . "Title: {$ticket->title}\n"
                . "Closed by: {$closedBy}\n"
                . "Completed at: " . ($ticket->completed_at?->toDateTimeString() ?: now()->toDateTimeString()) . "\n\n"
                . "View ticket: {$link}\n\n"
                . "Kind regards,\n" . config('app.name', 'Geminia CRM');
            $this->send((string) $creator->email1, $name, $subject, $body);
        }
    }

    protected function getUser(int $userId): ?object
    {
        if ($userId < 1) {
            return null;
        }

        return DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $userId)
            ->select('id', 'email1', 'first_name', 'last_name', 'user_name')
            ->first();
    }

    protected function fullName(object $user): string
    {
        return trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))
            ?: (string) ($user->user_name ?? ('User #' . ($user->id ?? 0)));
    }

    protected function send(string $to, ?string $toName, string $subject, string $body): bool
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($this->graph->isConfigured()) {
            if ($this->graph->sendMail($to, $toName, $subject, $body, false)) {
                return true;
            }
            Log::warning('WorkTicketNotificationService: Graph send failed, falling back to Mail', ['to' => $to]);
        }

        try {
            Mail::raw($body, function ($message) use ($to, $toName, $subject) {
                $message->to($to, $toName)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('WorkTicketNotificationService: send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

