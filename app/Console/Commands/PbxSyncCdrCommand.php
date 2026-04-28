<?php

namespace App\Console\Commands;

use App\Models\PbxCall;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
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
            $rows = $this->fetchCdrRows('vtiger', $minutes, $limit);

            foreach ($rows as $row) {
                $externalId = $this->buildExternalId($row);
                if ($externalId === '') {
                    continue;
                }

                $startTime = ! empty($row->calldate) ? \Carbon\Carbon::parse((string) $row->calldate) : null;
                $src = trim((string) ($row->src ?? ''));
                $dst = trim((string) ($row->dst ?? ''));
                $clid = trim((string) ($row->clid ?? ''));
                $dstChannel = trim((string) ($row->dstchannel ?? ''));
                $dcontext = strtolower(trim((string) ($row->dcontext ?? '')));
                $direction = $this->detectDirection($dcontext, $src, $dst);
                $customerNumber = $this->resolveCustomerNumber($direction, $src, $dst, $clid);
                $answeredExt = $this->extractExtensionFromChannel($dstChannel);
                $rawUser = $direction === 'inbound'
                    ? ($answeredExt ?: ($this->isLikelyExtension($dst) ? $dst : null))
                    : ($this->isLikelyExtension($src) ? $src : null);
                $userName = $this->resolveUserNameFromExtension($rawUser);

                $disposition = strtolower(trim((string) ($row->disposition ?? '')));
                $status = $this->mapDispositionToStatus($disposition);

                $duration = max(0, (int) (($row->billsec ?? 0) ?: ($row->duration ?? 0)));

                $recordingUrl = null;
                $recordingFile = trim((string) ($row->recordingfile ?? ''));
                if ($this->isLikelyExtension($customerNumber) && $recordingFile !== '') {
                    $fromFilename = $this->extractExternalNumberFromRecordingFile($recordingFile);
                    if ($fromFilename !== null) {
                        $customerNumber = $fromFilename;
                    }
                }
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
                        'user_name' => $userName,
                        'recording_url' => $recordingUrl,
                        'duration_sec' => $duration,
                        'start_time' => $startTime,
                    ]
                );
                $upserts++;
            }
        } catch (\Throwable $e) {
            $directHost = trim((string) env('PBX_CDR_DB_HOST', ''));
            if ($directHost !== '') {
                try {
                    $rows = $this->fetchCdrRows('pbx_cdr', $minutes, $limit);
                    foreach ($rows as $row) {
                        $externalId = $this->buildExternalId($row);
                        if ($externalId === '') {
                            continue;
                        }

                        $startTime = ! empty($row->calldate) ? \Carbon\Carbon::parse((string) $row->calldate) : null;
                        $src = trim((string) ($row->src ?? ''));
                        $dst = trim((string) ($row->dst ?? ''));
                        $clid = trim((string) ($row->clid ?? ''));
                        $dstChannel = trim((string) ($row->dstchannel ?? ''));
                        $dcontext = strtolower(trim((string) ($row->dcontext ?? '')));
                        $direction = $this->detectDirection($dcontext, $src, $dst);
                        $customerNumber = $this->resolveCustomerNumber($direction, $src, $dst, $clid);
                        $answeredExt = $this->extractExtensionFromChannel($dstChannel);
                        $rawUser = $direction === 'inbound'
                            ? ($answeredExt ?: ($this->isLikelyExtension($dst) ? $dst : null))
                            : ($this->isLikelyExtension($src) ? $src : null);
                        $userName = $this->resolveUserNameFromExtension($rawUser);
                        $disposition = strtolower(trim((string) ($row->disposition ?? '')));
                        $status = $this->mapDispositionToStatus($disposition);
                        $duration = max(0, (int) (($row->billsec ?? 0) ?: ($row->duration ?? 0)));
                        $recordingUrl = null;
                        $recordingFile = trim((string) ($row->recordingfile ?? ''));
                        if ($this->isLikelyExtension($customerNumber) && $recordingFile !== '') {
                            $fromFilename = $this->extractExternalNumberFromRecordingFile($recordingFile);
                            if ($fromFilename !== null) {
                                $customerNumber = $fromFilename;
                            }
                        }
                        if ($monitorBase !== '' && $recordingFile !== '') {
                            $recordingFile = ltrim($recordingFile, '/');
                            $recordingUrl = $startTime
                                ? ($monitorBase . '/' . $startTime->copy()->timezone(config('app.timezone', 'Africa/Nairobi'))->format('Y/m/d') . '/' . $recordingFile)
                                : ($monitorBase . '/' . $recordingFile);
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
                                'user_name' => $userName,
                                'recording_url' => $recordingUrl,
                                'duration_sec' => $duration,
                                'start_time' => $startTime,
                            ]
                        );
                        $upserts++;
                    }
                    $this->info("Source: pbx_cdr. Processed: {$processed}; Upserts: {$upserts}" . ($dryRun ? ' (dry-run)' : ''));
                    return self::SUCCESS;
                } catch (\Throwable $directErr) {
                    $this->warn('Direct CDR DB access failed, using vtiger_pbxmanager fallback: ' . $directErr->getMessage());
                }
            } else {
                $this->warn('CDR access failed, using vtiger_pbxmanager fallback: ' . $e->getMessage());
            }

            $usedFallback = true;

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

    protected function fetchCdrRows(string $connection, int $minutes, int $limit)
    {
        if ($connection === 'pbx_cdr') {
            Config::set('database.connections.pbx_cdr.host', env('PBX_CDR_DB_HOST'));
            Config::set('database.connections.pbx_cdr.port', env('PBX_CDR_DB_PORT', '3306'));
            Config::set('database.connections.pbx_cdr.database', env('PBX_CDR_DB_DATABASE', 'asteriskcdrdb'));
            Config::set('database.connections.pbx_cdr.username', env('PBX_CDR_DB_USERNAME'));
            Config::set('database.connections.pbx_cdr.password', env('PBX_CDR_DB_PASSWORD'));
        }

        $table = $connection === 'pbx_cdr' ? 'cdr' : 'asteriskcdrdb.cdr';

        return DB::connection($connection)
            ->table($table)
            ->select([
                'uniqueid',
                'calldate',
                'src',
                'dst',
                'clid',
                'dstchannel',
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
    }

    protected function mapDispositionToStatus(string $disposition): string
    {
        return match (strtolower(trim($disposition))) {
            'answered' => 'completed',
            'busy' => 'busy',
            'no answer', 'no-answer', 'noanswer' => 'no-answer',
            'failed' => 'failed',
            'congestion', 'chanunavail' => 'no-response',
            default => $disposition !== '' ? strtolower(trim($disposition)) : 'unknown',
        };
    }

    protected function detectDirection(string $dcontext, string $src, string $dst): string
    {
        if (str_contains($dcontext, 'from-trunk') || str_contains($dcontext, 'from-pstn')) {
            return 'inbound';
        }
        if (str_contains($dcontext, 'from-internal')) {
            return 'outbound';
        }
        if ($this->isLikelyExtension($dst) && ! $this->isLikelyExtension($src)) {
            return 'inbound';
        }
        if ($this->isLikelyExtension($src) && ! $this->isLikelyExtension($dst)) {
            return 'outbound';
        }
        return 'inbound';
    }

    protected function isLikelyExtension(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value);
        return $digits !== '' && strlen($digits) >= 2 && strlen($digits) <= 6;
    }

    protected function resolveCustomerNumber(string $direction, string $src, string $dst, string $clid = ''): string
    {
        $src = trim($src);
        $dst = trim($dst);
        $clid = trim($clid);
        $srcIsExt = $this->isLikelyExtension($src);
        $dstIsExt = $this->isLikelyExtension($dst);
        $clidDigits = preg_replace('/\D/', '', $clid);

        if ($direction === 'inbound') {
            if (! $srcIsExt) {
                return $src;
            }
            if ($clidDigits !== '' && ! $this->isLikelyExtension($clidDigits)) {
                return $clidDigits;
            }
            if (! $dstIsExt) {
                return $dst;
            }
            return $src;
        }

        if (! $dstIsExt) {
            return $dst;
        }
        if ($clidDigits !== '' && ! $this->isLikelyExtension($clidDigits)) {
            return $clidDigits;
        }
        if (! $srcIsExt) {
            return $src;
        }
        return $dst;
    }

    protected function buildExternalId(object $row): string
    {
        $uniqueId = trim((string) ($row->uniqueid ?? ''));
        if ($uniqueId === '') {
            return '';
        }
        $seq = (string) ($row->sequence ?? '');
        if ($seq !== '') {
            return $uniqueId . ':' . $seq;
        }
        $calldate = trim((string) ($row->calldate ?? ''));
        $src = trim((string) ($row->src ?? ''));
        $dst = trim((string) ($row->dst ?? ''));
        $disposition = trim((string) ($row->disposition ?? ''));
        return $uniqueId . ':' . substr(sha1($calldate . '|' . $src . '|' . $dst . '|' . $disposition), 0, 10);
    }

    protected function extractExtensionFromChannel(string $channel): ?string
    {
        $channel = trim($channel);
        if ($channel === '') {
            return null;
        }
        if (preg_match('/(?:SIP|PJSIP|IAX2)\/(\d{2,6})/i', $channel, $m)) {
            return $m[1];
        }
        return null;
    }

    protected function extractExternalNumberFromRecordingFile(string $recordingFile): ?string
    {
        $file = trim($recordingFile);
        if ($file === '') {
            return null;
        }
        // Common naming pattern: rg-620-00254702087824-YYYYMMDD-HHMMSS-uniqueid.wav
        if (preg_match('/^rg-\d{2,6}-(\d{7,15})-/i', $file, $m)) {
            return $m[1];
        }
        return null;
    }

    protected function resolveUserNameFromExtension(?string $extension): ?string
    {
        $ext = trim((string) $extension);
        if ($ext === '') {
            return null;
        }

        static $map = null;
        if ($map === null) {
            $map = [];
            try {
                $rows = DB::connection('vtiger')
                    ->table('vtiger_users as u')
                    ->leftJoin('vtiger_user_preferences as up', function ($join) {
                        $join->on('u.id', '=', 'up.userid')
                            ->where('up.key', '=', 'phone_crm_extension');
                    })
                    ->whereNotNull('up.value')
                    ->where('up.value', '<>', '')
                    ->select('u.first_name', 'u.last_name', 'u.user_name', 'up.value as extension')
                    ->get();

                foreach ($rows as $row) {
                    $k = preg_replace('/\D/', '', (string) ($row->extension ?? ''));
                    if ($k === '') {
                        continue;
                    }
                    $name = trim((string) (($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
                    if ($name === '') {
                        $name = trim((string) ($row->user_name ?? ''));
                    }
                    if ($name !== '') {
                        $map[$k] = $name;
                    }
                }
            } catch (\Throwable) {
                $map = [];
            }
        }

        $digits = preg_replace('/\D/', '', $ext);
        if ($digits !== '' && isset($map[$digits])) {
            return $map[$digits];
        }
        return $ext;
    }
}

