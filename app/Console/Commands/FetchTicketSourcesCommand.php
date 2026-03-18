<?php

namespace App\Console\Commands;

use App\Services\CrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTicketSourcesCommand extends Command
{
    protected $signature = 'tickets:fetch-sources
                            {--refresh : Clear cache and fetch fresh from CRM}';

    protected $description = 'Fetch all ticket sources from the CRM (vtiger) and display them';

    public function handle(CrmService $crm): int
    {
        if ($this->option('refresh')) {
            Cache::forget('ticket_sources_from_crm');
        }

        try {
            $sources = $crm->getTicketSourcesFromCrm();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch sources: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Ticket sources from CRM (' . count($sources) . ' total):');
        $this->newLine();
        foreach ($sources as $i => $src) {
            $this->line('  ' . ($i + 1) . '. ' . $src);
        }

        return self::SUCCESS;
    }
}
