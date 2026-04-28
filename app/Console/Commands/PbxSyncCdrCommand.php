<?php

namespace App\Console\Commands;

use App\Models\PbxCall;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PbxSyncCdrCommand extends Command
{
    protected $signature = 'pbx:sync-cdr
                            {--minutes=20 : Look back window in minutes}
                            {--limit=300 : Max CDR rows to process}
                            {--dry-run : Do not write, only show count}';

    protected $description = 'Sync PBX calls and recordings from asteriskcdrdb.cdr into local pbx_calls table';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        $monitorBase = rtrim((string) config('services.pbx.monitor_public_base_url', ''), '/');

        $processed = 0;
        $upserts = 0;
        $usedFallback = false;

        try {
            $rows = DB::connection('vtiger')
                ->table('asteriskcdrdb.cdr')
                ->select([
                    'uniqueid',
                    'calldate',
                    'src',
                    'dst',
                    'dcontext',
                    'disposition',
                    'billsec',
                    'duration',
                    'recordingfile',
                ])
                ->whereNotNull('uniqueid')
                ->where('uniqueid', '<>', '')
                ->where('calldate', '>=', now()->subMinutes($minutes)->format('Y-m-d H:i:s'))
                ->orderByDesc('calldate')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $externalId = trim((string) ($row->uniqueid ?? ''));
                if ($externalId === '') {
                    continue;
                }

                $startTime = ! empty($row->calldate) ? \Carbon\Carbon::parse((string) $row->calldate) : null;
                $src = trim((string) ($row->src ?? ''));
                $dst = trim((string) ($row->dst ?? ''));
                $dcontext = strtolower(trim((string) ($row->dcontext ?? '')));
                $direction = (str_contains($dcontext, 'from-trunk') || str_contains($dcontext, 'from-pstn'))
                    ? 'inbound'
                    : 'outbound';
                $customerNumber = $direction === 'inbound' ? $src : $dst;

                $disposition = strtolower(trim((string) ($row->disposition ?? '')));
                $status = match ($disposition) {
                    'answered' => 'completed',
                    'busy' => 'busy',
                    'no answer' => 'no-answer',
                    'failed' => 'failed',
                    'congestion' => 'no-response',
                    default => $disposition !== '' ? $disposition : 'unknown',
                };

                $duration = max(0, (int) (($row->billsec ?? 0) ?: ($row->duration ?? 0)));

                $recordingUrl = null;
                $recordingFile = trim((string) ($row->recordingfile ?? ''));
                if ($monitorBase !== '' && $recordingFile !== '') {
                    $recordingFile = ltrim($recordingFile, '/');
                    if ($startTime) {
                        $datedPath = $startTime->copy()->timezone(config('app.timezone', 'Africa/Nairobi'))->format('Y/m/d') . '/' . $recordingFile;
                        $recordingUrl = $monitorBase . '/' . $datedPath;
                    } else {
                        $recordingUrl = $monitorBase . '/' . $recordingFile;
                    }
                }

                $processed++;
                if ($dryRun) {
                    continue;
                }

                PbxCall::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'call_status' => $status,
                        'direction' => $direction,
                        'customer_number' => $customerNumber,
                        'customer_name' => null,
                        'user_name' => null,
                        'recording_url' => $recordingUrl,
                        'duration_sec' => $duration,
                        'start_time' => $startTime,
                    ]
                );
                $upserts++;
            }
        } catch (\Throwable $e) {
            $usedFallback = true;
            $this->warn('CDR access failed, using vtiger_pbxmanager fallback: ' . $e->getMessage());

            $rows = DB::connection('vtiger')
                ->table('vtiger_pbxmanager')
                ->select([
                    'sourceuuid',
                    'starttime',
                    'customernumber',
                    'direction',
                    'callstatus',
                    'recordingurl',
                    DB::raw('COALESCE(NULLIF(billduration,0), NULLIF(totalduration,0), 0) as duration_sec'),
                ])
                ->whereNotNull('sourceuuid')
                ->where('sourceuuid', '<>', '')
                ->where('starttime', '>=', now()->subMinutes($minutes)->format('Y-m-d H:i:s'))
                ->orderByDesc('starttime')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $externalId = trim((string) ($row->sourceuuid ?? ''));
                if ($externalId === '') {
                    continue;
                }

                $startTime = ! empty($row->starttime) ? \Carbon\Carbon::parse((string) $row->starttime) : null;
                $recordingUrl = trim((string) ($row->recordingurl ?? '')) ?: null;

                $processed++;
                if ($dryRun) {
                    continue;
                }

                PbxCall::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'call_status' => strtolower(trim((string) ($row->callstatus ?? 'unknown'))),
                        'direction' => strtolower(trim((string) ($row->direction ?? 'inbound'))),
                        'customer_number' => trim((string) ($row->customernumber ?? '')),
                        'customer_name' => null,
                        'user_name' => null,
                        'recording_url' => $recordingUrl,
                        'duration_sec' => max(0, (int) ($row->duration_sec ?? 0)),
                        'start_time' => $startTime,
                    ]
                );
                $upserts++;
            }
        }

        if ($processed === 0) {
            $this->info('No recent PBX rows found.');
            return self::SUCCESS;
        }

        $source = $usedFallback ? 'vtiger_pbxmanager' : 'asteriskcdrdb.cdr';
        $this->info("Source: {$source}. Processed: {$processed}; Upserts: {$upserts}" . ($dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}

