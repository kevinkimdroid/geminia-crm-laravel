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
        $lifeLimit = $mailService->resolveFetchLimit(null, $this->option('limit') ? (int) $this->option('limit') : null);
        $pensionLimit = $mailService->resolveFetchLimit(config('pension.mailbox'));

        $this->info("Fetching up to {$lifeLimit} emails from INBOX...");

        try {
            $result = $mailService->fetchAndStoreEmails('INBOX', $lifeLimit);
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

        if ($pensionMailbox = config('pension.mailbox')) {
            $this->info("Fetching pension mailbox: {$pensionMailbox} (up to {$pensionLimit})");
            try {
                $pensionResult = $mailService->fetchAndStoreEmails('INBOX', $pensionLimit, $pensionMailbox);
                $this->info("Pension — fetched: {$pensionResult['fetched']}, stored: {$pensionResult['stored']} new.");
                foreach ($pensionResult['errors'] ?? [] as $err) {
                    $this->warn("  Pension: {$err}");
                }
            } catch (\Throwable $e) {
                $this->warn('Pension fetch failed: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
