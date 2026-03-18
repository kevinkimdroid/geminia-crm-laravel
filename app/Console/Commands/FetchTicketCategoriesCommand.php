<?php

namespace App\Console\Commands;

use App\Services\CrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTicketCategoriesCommand extends Command
{
    protected $signature = 'tickets:fetch-categories
                            {--refresh : Clear cache and fetch fresh from CRM}';

    protected $description = 'Fetch all ticket categories from the CRM (vtiger) and display them';

    public function handle(CrmService $crm): int
    {
        if ($this->option('refresh')) {
            Cache::forget('ticket_categories_from_crm');
        }

        try {
            $categories = $crm->getTicketCategoriesFromCrm();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch categories: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Ticket categories from CRM (' . count($categories) . ' total):');
        $this->newLine();
        foreach ($categories as $i => $cat) {
            $this->line('  ' . ($i + 1) . '. ' . $cat);
        }
        $this->newLine();
        $this->info('Use these in TICKET_CATEGORIES (comma-separated) in .env if needed.');
        $this->line(implode(',', $categories));

        return self::SUCCESS;
    }
}
