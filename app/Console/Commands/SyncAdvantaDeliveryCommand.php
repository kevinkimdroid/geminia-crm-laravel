<?php

namespace App\Console\Commands;

use App\Services\AdvantaDeliverySyncService;
use Illuminate\Console\Command;

class SyncAdvantaDeliveryCommand extends Command
{
    protected $signature = 'advanta:sync-delivery
                            {--limit=100 : Max sms_logs rows to check}
                            {--hours=72 : Only logs sent within this many hours}';

    protected $description = 'Poll Advanta getdlr API and update CRM sms_logs delivery status (Success, Blacklisted, etc.)';

    public function handle(AdvantaDeliverySyncService $sync): int
    {
        $summary = $sync->syncRecent(
            (int) $this->option('limit'),
            (int) $this->option('hours')
        );

        $this->info(sprintf(
            'Checked %d message(s) with Advanta DLR: %d updated, %d errors.',
            $summary['checked'],
            $summary['updated'],
            $summary['errors']
        ));

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
