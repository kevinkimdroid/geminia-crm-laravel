<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseTicketsByAssigneeCommand extends Command
{
    protected $signature = 'tickets:close-by-assignee {name : User name (e.g. "Romano Omuse")}';

    protected $description = 'Close all open tickets assigned to a user by name';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        if ($name === '') {
            $this->error('Please provide a user name.');
            return self::FAILURE;
        }

        // Match by full name (first + last) or user_name
        $user = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where(function ($q) use ($name) {
                $q->whereRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) = ?", [$name])
                    ->orWhere('user_name', $name);
            })
            ->select('id', 'first_name', 'last_name', 'user_name')
            ->first();

        if (! $user) {
            $this->error("User '{$name}' not found.");
            return self::FAILURE;
        }

        $ids = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.setype', 'HelpDesk')
            ->where('e.deleted', 0)
            ->where('e.smownerid', $user->id)
            ->where('t.status', '!=', 'Closed')
            ->pluck('t.ticketid');

        if ($ids->isEmpty()) {
            $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
            $this->info("No open tickets assigned to {$displayName}.");
            return self::SUCCESS;
        }

        $now = now()->format('Y-m-d H:i:s');
        $count = $ids->count();

        foreach ($ids as $id) {
            DB::connection('vtiger')->table('vtiger_troubletickets')->where('ticketid', $id)->update([
                'status' => 'Closed',
            ]);
            DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update([
                'modifiedtime' => $now,
            ]);
        }

        foreach (['geminia_ticket_counts_by_status', 'geminia_tickets_count'] as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
        foreach (['Open', 'In_Progress', 'Wait_For_Response', 'Closed', 'Unassigned'] as $slug) {
            \Illuminate\Support\Facades\Cache::forget('tickets_list_' . $slug);
        }
        \Illuminate\Support\Facades\Cache::forget('tickets_list_default');

        $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
        $this->info("Closed {$count} ticket(s) assigned to {$displayName}.");

        return self::SUCCESS;
    }
}
