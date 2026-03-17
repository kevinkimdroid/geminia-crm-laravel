<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DebugTicketPermissionCommand extends Command
{
    protected $signature = 'debug:ticket-permission {ticketId : The ticket ID to check} {--user= : Optional vtiger user ID to check (if not logged in)}';

    protected $description = 'Debug ticket permission: shows userId, ownerId, and whether access would be granted';

    public function handle(): int
    {
        $ticketId = (int) $this->argument('ticketId');
        $userIdOverride = $this->option('user') ? (int) $this->option('user') : null;

        $user = Auth::guard('vtiger')->user();
        $userId = $userIdOverride ?? ($user ? (int) ($user->id ?? $user->getAuthIdentifier()) : null);
        if ($userId === null) {
            $this->error('Provide --user=ID (vtiger user id) or log in via the web app first.');
            $this->line('Example: php artisan debug:ticket-permission ' . $ticketId . ' --user=5');
            return 1;
        }

        $userName = $user ? ($user->user_name ?? 'user#' . $userId) : 'user#' . $userId;
        $this->info("Checking as user ID: {$userId} ({$userName})");
        $this->line('');

        $row = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $ticketId)
            ->select('crmid', 'smownerid', 'setype', 'deleted')
            ->first();

        if (!$row) {
            $this->error("No vtiger_crmentity row for crmid={$ticketId}");
            return 1;
        }

        $this->info("vtiger_crmentity: crmid={$row->crmid}, smownerid={$row->smownerid}, setype={$row->setype}, deleted={$row->deleted}");
        $this->line('');

        $ticketExists = DB::connection('vtiger')
            ->table('vtiger_troubletickets')
            ->where('ticketid', $ticketId)
            ->exists();

        $this->info('Ticket in vtiger_troubletickets: ' . ($ticketExists ? 'YES' : 'NO'));
        $this->line('');

        $ownerId = (int) ($row->smownerid ?? 0);
        $match = ($ownerId === $userId);
        $this->info('smownerid matches user: ' . ($match ? 'YES' : 'NO') . " (smownerid={$ownerId}, userId={$userId})");

        // Check if owner is a group
        $isGroup = DB::connection('vtiger')->table('vtiger_groups')->where('groupid', $ownerId)->exists();
        $this->info('smownerid is a group: ' . ($isGroup ? 'YES' : 'NO'));

        if ($isGroup) {
            $inGroup = DB::connection('vtiger')
                ->table('vtiger_user2group')
                ->where('userid', $userId)
                ->where('groupid', $ownerId)
                ->exists();
            $this->info('User in group: ' . ($inGroup ? 'YES' : 'NO'));
        }

        $this->line('');
        $wouldAllow = $match || ($isGroup && ($inGroup ?? false));
        $this->info('Expected access: ' . ($wouldAllow ? 'ALLOW' : 'DENY'));

        return 0;
    }
}
