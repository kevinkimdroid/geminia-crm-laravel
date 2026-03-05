<?php

namespace App\Console\Commands;

use App\Services\TicketAutoCreateService;
use Illuminate\Console\Command;

class CreateMaturityReminderTicketsCommand extends Command
{
    protected $signature = 'tickets:create-maturity-reminders
                            {--dry-run : Show what would be created without creating}';

    protected $description = 'Auto-create tickets for policies maturing within configured days (assigned to Customer Service)';

    public function handle(TicketAutoCreateService $autoCreate): int
    {
        $config = config('tickets.auto_maturity', []);
        if (empty($config['enabled'])) {
            $this->warn('Auto maturity reminders are disabled. Set TICKET_AUTO_MATURITY_ENABLED=true in .env');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run – no tickets will be created.');
            $this->info('Policies maturing in next ' . ($config['days_before'] ?? 30) . ' days would trigger tickets.');
            $this->info('Assignee user ID: ' . ($config['assign_to_user_id'] ?? 1));
            return self::SUCCESS;
        }

        $this->info('Creating maturity reminder tickets...');
        $result = $autoCreate->createMaturityReminderTickets();

        $this->info("Created: {$result['created']}, Skipped: {$result['skipped']}");
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $this->warn("  - {$err}");
            }
        }

        return self::SUCCESS;
    }
}
