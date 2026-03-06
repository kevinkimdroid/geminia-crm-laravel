<?php

namespace App\Console\Commands;

use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class SlaViolationReminderCommand extends Command
{
    protected $signature = 'tickets:sla-violation-reminders';

    protected $description = 'Send email reminders for tickets that have exceeded SLA (TAT)';

    public function handle(TicketNotificationService $notifier): int
    {
        $result = $notifier->sendSlaViolationReminders();
        $sent = $result['sent'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        if ($sent > 0) {
            $this->info("Sent {$sent} SLA violation reminder(s).");
        }
        if ($skipped > 0) {
            $this->line("Skipped {$skipped} (already reminded or no email).");
        }
        if ($sent === 0 && $skipped === 0) {
            $this->info('No SLA violations to remind.');
        }

        return self::SUCCESS;
    }
}
