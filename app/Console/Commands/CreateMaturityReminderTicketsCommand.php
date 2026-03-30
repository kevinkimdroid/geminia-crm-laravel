<?php

namespace App\Console\Commands;

use App\Services\TicketAutoCreateService;
use Illuminate\Console\Command;

class CreateMaturityReminderTicketsCommand extends Command
{
    protected $signature = 'tickets:create-maturity-reminders
                            {--dry-run : Show what would be created without creating}';

    protected $description = 'Auto-create tickets for policies maturing within configured horizons (optional product-based assignees)';

    public function handle(TicketAutoCreateService $autoCreate): int
    {
        $config = config('tickets.auto_maturity', []);
        if (empty($config['enabled'])) {
            $this->warn('Auto maturity reminders are disabled. Set TICKET_AUTO_MATURITY_ENABLED=true in .env');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run – no tickets will be created.');
            if (! empty($config['use_horizon_bands'])) {
                $bands = $autoCreate->buildMaturityHorizonBands($config);
                $this->info('Multi-horizon bands (non-overlapping maturity windows):');
                foreach ($bands as $b) {
                    $lo = $b['lower_exclusive_ymd'] ?? null;
                    $loDisp = $lo !== null ? "> {$lo}" : '≥ '.now()->format('Y-m-d');
                    $this->line("  • {$b['days_before']}d — {$b['label']}: maturity {$loDisp} and ≤ {$b['upper_inclusive_ymd']} (priority {$b['priority']})");
                }
            } else {
                $this->info('Single window: policies maturing in next '.($config['days_before'] ?? 30).' days.');
            }
            $this->info('Default assignee user ID: '.($config['assign_to_user_id'] ?? 1));
            $map = $config['product_assignees'] ?? [];
            if (is_array($map) && $map !== []) {
                $this->info('Product assignee map: '.json_encode($map));
            }
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
