<?php

namespace App\Console\Commands;

use App\Services\MailService;
use App\Support\MailFetchHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MailHealthCommand extends Command
{
    protected $signature = 'mail:health {--json : Output health as JSON}';

    protected $description = 'Show mail fetch health (last successful pull, last error, source)';

    public function handle(MailService $mailService): int
    {
        $health = MailFetchHealth::get();
        $staleMinutes = max(1, (int) config('email-service.health_stale_minutes', 15));
        $lastSuccess = null;
        if (! empty($health['last_success_at'])) {
            try {
                $lastSuccess = Carbon::parse($health['last_success_at']);
            } catch (\Throwable $e) {
                $lastSuccess = null;
            }
        }
        $isStale = ! $lastSuccess || $lastSuccess->lt(now()->subMinutes($staleMinutes));
        $source = $mailService->useMicrosoftGraph()
            ? 'microsoft-graph'
            : ($mailService->useHttpEmailService() ? 'http-service' : 'imap');

        $payload = [
            'source' => $source,
            'status' => $health['status'] ?? 'unknown',
            'trigger' => $health['trigger'] ?? null,
            'last_attempt_at' => $health['last_attempt_at'] ?? null,
            'last_success_at' => $health['last_success_at'] ?? null,
            'fetched' => (int) ($health['fetched'] ?? 0),
            'stored' => (int) ($health['stored'] ?? 0),
            'error' => $health['error'] ?? null,
            'is_stale' => $isStale,
            'stale_minutes' => $staleMinutes,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('Mail Fetch Health');
        $this->newLine();

        $this->table(
            ['Key', 'Value'],
            [
                ['Source', $payload['source']],
                ['Status', $payload['status']],
                ['Trigger', $payload['trigger'] ?? 'n/a'],
                ['Last Attempt', $this->formatTimestamp($payload['last_attempt_at'])],
                ['Last Success', $this->formatTimestamp($payload['last_success_at'])],
                ['Stale', $payload['is_stale'] ? 'yes' : 'no'],
                ['Stale Threshold (minutes)', (string) $payload['stale_minutes']],
                ['Fetched (last success)', (string) $payload['fetched']],
                ['Stored (last success)', (string) $payload['stored']],
                ['Last Error', $payload['error'] ?? 'n/a'],
            ]
        );

        if ($payload['is_stale']) {
            $this->warn("Mail fetch is stale (no success in the last {$payload['stale_minutes']} minute(s)).");
        }

        return self::SUCCESS;
    }

    private function formatTimestamp(?string $value): string
    {
        if (! $value) {
            return 'never';
        }

        try {
            $time = Carbon::parse($value);
            return $time->toDateTimeString() . ' (' . $time->diffForHumans() . ')';
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
