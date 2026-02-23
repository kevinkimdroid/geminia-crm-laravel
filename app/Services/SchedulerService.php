<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reads Vtiger cron tasks for the Scheduler UI.
 */
class SchedulerService
{
    public function getCronTasks(): array
    {
        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_cron_task')
                ->orderBy('sequence')
                ->get();

            return $rows->map(function ($row) {
                $frequencySec = (int) ($row->frequency ?? 900);
                $hours = floor($frequencySec / 3600);
                $mins = floor(($frequencySec % 3600) / 60);
                $frequencyFormatted = sprintf('%02d:%02d', $hours, $mins);

                $lastStart = $row->laststart ?? 0;
                $lastEnd = $row->lastend ?? 0;

                return [
                    'id' => $row->id,
                    'sequence' => $row->sequence ?? 0,
                    'name' => $row->name ?? 'Unknown',
                    'frequency' => $frequencyFormatted,
                    'frequency_seconds' => $frequencySec,
                    'status' => (int) ($row->status ?? 0) === 1 ? 'Active' : 'In Active',
                    'status_active' => (int) ($row->status ?? 0) === 1,
                    'last_start' => $lastStart ? Carbon::createFromTimestamp($lastStart)->diffForHumans() : '—',
                    'last_end' => $lastEnd ? Carbon::createFromTimestamp($lastEnd)->diffForHumans() : '—',
                    'description' => $row->description ?? '',
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            Log::warning('SchedulerService::getCronTasks: ' . $e->getMessage());
            return [];
        }
    }
}
