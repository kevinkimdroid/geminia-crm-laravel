<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateComplaintsFromEmailsCommand extends Command
{
    protected $signature = 'complaints:create-from-emails
                            {--limit=50 : Max emails to process}
                            {--dry-run : Show what would be done without creating}';

    protected $description = 'Create complaints from emails in Mail Manager that do not yet have a complaint (backfill)';

    public function handle(): int
    {
        $config = config('complaints.auto_from_email', []);
        if (empty($config['enabled'])) {
            $this->warn('Complaints auto-from-email is disabled. Set COMPLAINTS_AUTO_FROM_EMAIL_ENABLED=true');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $query = DB::connection('vtiger')->table('mail_manager_emails')
            ->whereNotNull('from_address')
            ->where('from_address', '!=', '');

        if (\Illuminate\Support\Facades\Schema::connection('vtiger')->hasColumn('mail_manager_emails', 'complaint_id')) {
            $query->whereNull('complaint_id');
        }

        $emails = $query->orderByDesc('id')->limit($limit)->get();

        $service = app(\App\Services\AutoComplaintFromEmailService::class);
        $created = 0;
        $skipped = 0;

        $excludedDomains = config('email-service.excluded_sender_domains', ['geminialife.co.ke']);
        foreach ($emails as $email) {
            $from = strtolower(trim($email->from_address ?? ''));
            if ($from === '') {
                $skipped++;
                continue;
            }
            $isExcluded = false;
            foreach ($excludedDomains as $domain) {
                if ($domain && str_ends_with($from, '@' . $domain)) {
                    $isExcluded = true;
                    break;
                }
            }
            if ($isExcluded) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("Would create complaint for: {$email->from_address} - " . ($email->subject ?? '(no subject)'));
                $created++;
                continue;
            }

            $complaint = $service->processNewInboundEmail((int) $email->id);
            if ($complaint) {
                $created++;
                $this->info("Created complaint {$complaint->complaint_ref} from email #{$email->id}");
            } else {
                $skipped++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run: would create {$created} complaints, skip {$skipped}");
        } else {
            $this->info("Created {$created} complaints, skipped {$skipped}");
        }

        return self::SUCCESS;
    }
}
