<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseMaturityTicketsCommand extends Command
{
    protected $signature = 'tickets:close-maturity-reminders';

    protected $description = 'Close all maturity reminder tickets (source=Auto, title starts with "Maturity reminder:")';

    public function handle(): int
    {
        $source = config('tickets.auto_maturity.source', 'Auto');
        $now = now()->format('Y-m-d H:i:s');

        $ids = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.setype', 'HelpDesk')
            ->where('e.deleted', 0)
            ->where('e.source', $source)
            ->where('t.title', 'like', 'Maturity reminder:%')
            ->where('t.status', '!=', 'Closed')
            ->pluck('t.ticketid');

        if ($ids->isEmpty()) {
            $this->info('No open maturity reminder tickets to close.');
            return self::SUCCESS;
        }

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

        $this->info("Closed {$count} maturity reminder ticket(s).");

        return self::SUCCESS;
    }
}
