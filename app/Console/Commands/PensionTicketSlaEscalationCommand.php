<?php

namespace App\Console\Commands;

use App\Services\PensionTicketSlaEscalationService;
use Illuminate\Console\Command;

class PensionTicketSlaEscalationCommand extends Command
{
    protected $signature = 'pension:escalate-sla-tickets';

    protected $description = 'Reassign breached pension email tickets to the escalation owner';

    public function handle(PensionTicketSlaEscalationService $service): int
    {
        $result = $service->escalateBreachedTickets();

        foreach ($result['errors'] ?? [] as $error) {
            $this->error($error);
        }

        if (($result['escalated'] ?? 0) > 0) {
            $this->info("Escalated {$result['escalated']} pension ticket(s).");
        }
        if (($result['skipped'] ?? 0) > 0) {
            $this->line("Skipped {$result['skipped']} already escalated.");
        }
        if (($result['escalated'] ?? 0) === 0 && ($result['skipped'] ?? 0) === 0 && empty($result['errors'])) {
            $this->info('No pension SLA breaches to escalate.');
        }

        return self::SUCCESS;
    }
}
