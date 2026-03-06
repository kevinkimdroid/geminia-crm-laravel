<?php

namespace App\Console\Commands;

use App\Services\TicketNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyTicketCreatedCommand extends Command
{
    protected $signature = 'tickets:notify-created {ticket : Ticket number (e.g. TT2025806065) or ticket ID}';

    protected $description = 'Send ticket creation notification email for an existing ticket (for resend/debug)';

    public function handle(TicketNotificationService $notifier): int
    {
        $arg = trim($this->argument('ticket'));
        $ticketId = null;

        if (is_numeric($arg)) {
            $ticketId = (int) $arg;
        } elseif (str_starts_with(strtoupper($arg), 'TT')) {
            $ticketId = (int) substr($arg, 2);
        }

        if (! $ticketId || $ticketId < 1) {
            $this->error('Invalid ticket. Use ticket ID (e.g. 2025806065) or number (e.g. TT2025806065)');
            return self::FAILURE;
        }

        $ticket = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('t.ticketid', $ticketId)
            ->where('e.setype', 'HelpDesk')
            ->where('e.deleted', 0)
            ->select('t.ticketid', 't.ticket_no', 't.title', 't.contact_id', 'e.smownerid')
            ->first();

        if (! $ticket) {
            $this->error("Ticket {$arg} not found.");
            return self::FAILURE;
        }

        $result = $notifier->sendTicketCreatedNotification(
            (int) $ticket->ticketid,
            $ticket->ticket_no,
            $ticket->title,
            (int) $ticket->smownerid,
            $ticket->contact_id ? (int) $ticket->contact_id : null,
            null,
            true
        );

        $assigned = $result['assigned_sent'] ? 'Yes' : 'No';
        $contact = $result['contact_sent'] ? 'Yes' : 'No';

        $this->info("Notification for {$ticket->ticket_no}: Assigned user={$assigned}, Contact={$contact}");
        if (! $result['assigned_sent'] && ! $result['contact_sent']) {
            $this->warn('No email sent. Check storage/logs/laravel.log for details.');
        }

        return self::SUCCESS;
    }
}
