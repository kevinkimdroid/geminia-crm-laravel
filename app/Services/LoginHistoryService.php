<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginHistoryService
{
    /**
     * @return Collection|LengthAwarePaginator
     */
    public function getRecords(int $limit, int $offset, string $filter = 'all')
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_loginhistory as l')
                ->leftJoin('vtiger_users as u', 'l.user_name', '=', 'u.user_name')
                ->select(
                    'l.login_id',
                    'l.user_name',
                    'l.user_ip',
                    'l.login_time',
                    'l.logout_time',
                    'l.status',
                    DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as full_name")
                )
                ->orderByDesc('l.login_time');

            if ($filter === 'signed_in') {
                $query->where('l.status', 'Signed in');
            } elseif ($filter === 'signed_off') {
                $query->where('l.status', 'Signed off');
            }

            return $query->offset($offset)->limit($limit)->get();
        } catch (\Throwable $e) {
            Log::warning('LoginHistoryService::getRecords: ' . $e->getMessage());
            return collect();
        }
    }

    public function getCount(string $filter = 'all'): int
    {
        try {
            $query = DB::connection('vtiger')->table('vtiger_loginhistory');

            if ($filter === 'signed_in') {
                $query->where('status', 'Signed in');
            } elseif ($filter === 'signed_off') {
                $query->where('status', 'Signed off');
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('LoginHistoryService::getCount: ' . $e->getMessage());
            return 0;
        }
    }
}
