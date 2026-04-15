<?php

namespace App\Console\Commands;

use App\Services\MailService;
use App\Support\MailFetchHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchEmailsCommand extends Command
{
    protected $signature = 'mail:fetch
                            {--limit= : Max emails to fetch (default from config)}';

    protected $description = 'Fetch emails from inbox and store in Mail Manager (auto-tickets when enabled)';

    public function handle(MailService $mailService): int
    {
        $limit = $this->option('limit')
            ? (int) $this->option('limit')
            : max(50, (int) config('email-service.fetch_limit', 25), (int) config('microsoft-graph.fetch_limit', 25));

        $this->info("Fetching up to {$limit} emails from INBOX...");

        try {
            $result = $mailService->fetchAndStoreEmails('INBOX', $limit);
        } catch (\Throwable $e) {
            MailFetchHealth::markFailure($e->getMessage(), 'scheduler');
            $this->error('Fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Fetched: {$result['fetched']}, Stored: {$result['stored']} new.");
        if (! empty($result['errors'])) {
            MailFetchHealth::markFailure(implode(' ', $result['errors']), 'scheduler');
            foreach ($result['errors'] as $err) {
                $this->warn("  - {$err}");
            }
        } else {
            MailFetchHealth::markSuccess($result, 'scheduler');
        }

        Cache::forget('geminia_emails_count');
        return self::SUCCESS;
    }
}
