<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reassign all tickets created from email (source=Email) to a specified user.
 */
class ReassignEmailTicketsCommand extends Command
{
    protected $signature = 'tickets:reassign-email
                            {assignee=Caroline Wanjiku : User name to assign to}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Reassign all email-source tickets to a user (default: Caroline Wanjiku)';

    public function handle(): int
    {
        $assigneeName = trim($this->argument('assignee'));
        if ($assigneeName === '') {
            $this->error('Please provide an assignee name.');
            return self::FAILURE;
        }

        // Match by full name (first + last) or user_name
        $user = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where(function ($q) use ($assigneeName) {
                $q->whereRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) = ?", [$assigneeName])
                    ->orWhere('user_name', $assigneeName);
            })
            ->select('id', 'first_name', 'last_name', 'user_name')
            ->first();

        if (! $user) {
            $this->error("User '{$assigneeName}' not found.");
            return self::FAILURE;
        }

        $ticketIds = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->where('e.deleted', 0)
            ->where('e.source', 'Email')
            ->pluck('t.ticketid');

        if ($ticketIds->isEmpty()) {
            $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
            $this->info("No email tickets found. {$displayName} would receive them.");
            return self::SUCCESS;
        }

        $count = $ticketIds->count();
        $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN – no changes made.');
            $this->info("Would reassign {$count} email ticket(s) to {$displayName}.");
            return self::SUCCESS;
        }

        $now = now()->format('Y-m-d H:i:s');

        DB::connection('vtiger')->table('vtiger_crmentity')
            ->whereIn('crmid', $ticketIds->all())
            ->update(['smownerid' => $user->id, 'modifiedtime' => $now]);

        foreach (['geminia_ticket_counts_by_status', 'geminia_tickets_count'] as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
        \Illuminate\Support\Facades\Cache::forget('tickets_list_default');
        \App\Events\DashboardStatsUpdated::dispatch();

        $this->info("Reassigned {$count} email ticket(s) to {$displayName}.");
        $this->newLine();
        $this->comment("To assign NEW email tickets to {$displayName}, add to .env:");
        $this->line("TICKET_AUTO_FROM_EMAIL_ASSIGN_TO={$user->id}");

        return self::SUCCESS;
    }
}
