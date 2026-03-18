<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Handles user offboarding: record counts and bulk reassignment when a user leaves.
 */
class UserOffboardingService
{
    protected const MODULES = [
        'tickets' => ['HelpDesk', 'Ticket'],
        'contacts' => ['Contacts', 'Contact'],
        'leads' => ['Leads', 'Lead'],
        'deals' => ['Potentials', 'Opportunity'],
    ];

    /**
     * Get counts of records owned by a user, by module.
     */
    public function getRecordCounts(int $userId): array
    {
        $counts = [];
        foreach (self::MODULES as $key => $setypes) {
            try {
                $counts[$key] = (int) DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('smownerid', $userId)
                    ->whereIn('setype', $setypes)
                    ->where('deleted', 0)
                    ->count();
            } catch (\Throwable $e) {
                $counts[$key] = 0;
            }
        }
        return $counts;
    }

    /**
     * Reassign all records from one user to another (or unassigned if toUserId is 0).
     */
    public function reassignRecords(int $fromUserId, int $toUserId): array
    {
        $reassigned = [];
        foreach (self::MODULES as $key => $setypes) {
            try {
                $n = DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('smownerid', $fromUserId)
                    ->whereIn('setype', $setypes)
                    ->where('deleted', 0)
                    ->update([
                        'smownerid' => $toUserId,
                        'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    ]);
                $reassigned[$key] = (int) $n;
            } catch (\Throwable $e) {
                $reassigned[$key] = 0;
            }
        }
        return $reassigned;
    }
}
