<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clean up tickets and complaints that were erroneously created from emails
 * sent by excluded organization domains (e.g. centralbank.go.ke, gab.co.ke).
 */
class CleanupOrganizationTicketsCommand extends Command
{
    protected $signature = 'tickets:cleanup-organization-emails
                            {--dry-run : List affected records without making changes}';

    protected $description = 'Remove tickets/complaints created from excluded org emails (centralbank.go.ke, etc.)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $domains = config('email-service.excluded_sender_domains', []);
        if (empty($domains)) {
            $this->warn('No excluded sender domains configured.');
            return self::SUCCESS;
        }

        $this->info('Excluded domains: ' . implode(', ', $domains));
        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be made.');
        }

        $totalTickets = 0;
        $totalComplaints = 0;

        // Find emails from excluded domains that have ticket_id
        $emailsWithTickets = $this->getEmailsFromExcludedDomains($domains)
            ->whereNotNull('ticket_id')
            ->where('ticket_id', '!=', 0)
            ->get(['id', 'from_address', 'subject', 'ticket_id']);

        foreach ($emailsWithTickets as $email) {
            if ($dryRun) {
                $this->line("  [ticket] TT{$email->ticket_id} ← {$email->from_address} - " . ($email->subject ?? '(no subject)'));
            } else {
                $this->softDeleteTicket((int) $email->ticket_id);
                DB::connection('vtiger')
                    ->table('mail_manager_emails')
                    ->where('id', $email->id)
                    ->update(['ticket_id' => null]);
                $this->line("  Removed ticket TT{$email->ticket_id} from {$email->from_address}");
            }
            $totalTickets++;
        }

        // Find emails from excluded domains that have complaint_id
        if (Schema::connection('vtiger')->hasColumn('mail_manager_emails', 'complaint_id')) {
            $emailsWithComplaints = $this->getEmailsFromExcludedDomains($domains)
                ->whereNotNull('complaint_id')
                ->where('complaint_id', '!=', 0)
                ->get(['id', 'from_address', 'subject', 'complaint_id']);

            foreach ($emailsWithComplaints as $email) {
                if ($dryRun) {
                    $this->line("  [complaint] {$email->complaint_id} ← {$email->from_address} - " . ($email->subject ?? '(no subject)'));
                } else {
                    DB::connection('vtiger')->table('complaints')->where('id', $email->complaint_id)->delete();
                    DB::connection('vtiger')
                        ->table('mail_manager_emails')
                        ->where('id', $email->id)
                        ->update(['complaint_id' => null]);
                    $this->line("  Removed complaint {$email->complaint_id} from {$email->from_address}");
                }
                $totalComplaints++;
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("Would remove: {$totalTickets} ticket(s), {$totalComplaints} complaint(s).");
        } else {
            if ($totalTickets > 0 || $totalComplaints > 0) {
                foreach (['geminia_ticket_counts_by_status', 'geminia_tickets_count'] as $key) {
                    \Illuminate\Support\Facades\Cache::forget($key);
                }
                \App\Events\DashboardStatsUpdated::dispatch();
            }
            $this->newLine();
            $this->info("Cleanup complete: {$totalTickets} ticket(s), {$totalComplaints} complaint(s) removed.");
        }

        return self::SUCCESS;
    }

    protected function getEmailsFromExcludedDomains(array $domains)
    {
        $query = DB::connection('vtiger')->table('mail_manager_emails');
        $query->where(function ($q) use ($domains) {
            foreach ($domains as $domain) {
                if ($domain) {
                    $q->orWhereRaw('LOWER(TRIM(from_address)) LIKE ?', ['%@' . $domain]);
                }
            }
        });
        return $query;
    }

    protected function softDeleteTicket(int $ticketId): void
    {
        DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $ticketId)->update(['deleted' => 1]);
    }
}
