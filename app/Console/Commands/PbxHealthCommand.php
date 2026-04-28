<?php

namespace App\Console\Commands;

use App\Models\PbxCall;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PbxHealthCommand extends Command
{
    protected $signature = 'pbx:health {--json : Output health as JSON}';

    protected $description = 'Show PBX health (AGI port, CDR access, and recent call sync freshness)';

    public function handle(): int
    {
        $agiHost = (string) env('PBX_AGI_HOST', '10.1.1.86');
        $agiPort = (int) env('PBX_AGI_PORT', 4573);
        $agiTimeout = max(1, (int) env('PBX_AGI_TIMEOUT', 3));
        $staleMinutes = max(1, (int) env('PBX_HEALTH_STALE_MINUTES', 15));

        $agiReachable = false;
        $agiError = null;
        try {
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($agiHost, $agiPort, $errno, $errstr, $agiTimeout);
            if ($socket) {
                fclose($socket);
                $agiReachable = true;
            } else {
                $agiError = $errstr !== '' ? $errstr : ("errno {$errno}");
            }
        } catch (\Throwable $e) {
            $agiError = $e->getMessage();
        }

        $cdrAccess = false;
        $cdrError = null;
        try {
            DB::connection('vtiger')->table('asteriskcdrdb.cdr')->limit(1)->value('uniqueid');
            $cdrAccess = true;
        } catch (\Throwable $e) {
            $cdrError = $e->getMessage();
        }

        $lastCall = PbxCall::query()->orderByDesc('start_time')->first(['start_time', 'updated_at']);
        $lastCallAt = null;
        if ($lastCall?->start_time) {
            $lastCallAt = Carbon::parse($lastCall->start_time);
        } elseif ($lastCall?->updated_at) {
            $lastCallAt = Carbon::parse($lastCall->updated_at);
        }
        $isStale = ! $lastCallAt || $lastCallAt->lt(now()->subMinutes($staleMinutes));

        $status = ($agiReachable && $cdrAccess && ! $isStale) ? 'ok' : 'degraded';

        $payload = [
            'status' => $status,
            'agi' => [
                'host' => $agiHost,
                'port' => $agiPort,
                'reachable' => $agiReachable,
                'error' => $agiError,
            ],
            'cdr' => [
                'select_access' => $cdrAccess,
                'error' => $cdrError,
            ],
            'sync' => [
                'last_call_at' => $lastCallAt?->toDateTimeString(),
                'is_stale' => $isStale,
                'stale_minutes' => $staleMinutes,
            ],
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            return $status === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        $this->info('PBX Health');
        $this->newLine();
        $this->table(
            ['Check', 'Status', 'Details'],
            [
                [
                    'AGI port',
                    $agiReachable ? 'OK' : 'FAIL',
                    $agiReachable ? "{$agiHost}:{$agiPort} reachable" : ("{$agiHost}:{$agiPort} unreachable" . ($agiError ? " ({$agiError})" : '')),
                ],
                [
                    'CDR SELECT',
                    $cdrAccess ? 'OK' : 'FAIL',
                    $cdrAccess ? 'asteriskcdrdb.cdr readable' : ($cdrError ?: 'Access denied'),
                ],
                [
                    'PBX sync freshness',
                    $isStale ? 'STALE' : 'OK',
                    $lastCallAt ? ($lastCallAt->toDateTimeString() . ' (' . $lastCallAt->diffForHumans() . ')') : 'No local pbx_calls rows yet',
                ],
            ]
        );

        if ($status !== 'ok') {
            $this->warn('PBX health is degraded. Resolve failed checks above.');
        }

        return $status === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}

