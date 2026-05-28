<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Services\AdvantaSmsService;
use App\Services\ErpMessagingHttpClient;
use App\Services\ErpSmsMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiagnoseErpSmsCommand extends Command
{
    protected $signature = 'erp:diagnose-sms
                            {--test-advanta : Send a 1-character test to ADVANTA_TEST_MOBILE if set}';

    protected $description = 'Diagnose ERP SMS + Advanta connectivity, scheduler, and API routes';

    public function handle(
        ErpSmsMessageService $messages,
        ErpMessagingHttpClient $http,
        AdvantaSmsService $advanta
    ): int {
        $this->info('ERP SMS diagnostics');
        $this->newLine();

        $this->line('Queue: ' . config('queue.default'));
        $this->line('Auto-send enabled: ' . (config('erp.messages_auto_send_enabled') ? 'yes' : 'no'));
        $this->line('Advanta configured: ' . ($advanta->isConfigured() ? 'yes' : 'no'));
        $this->line('ERP HTTP configured: ' . ($http->isConfigured() ? 'yes' : 'no'));

        if (! $http->isConfigured()) {
            $this->error('ERP messages API base URL is missing.');

            return self::FAILURE;
        }

        $base = $http->baseUrl();
        $this->line('API base: ' . $base);

        $batchUrl = $base . '/messages/sms/mark-sent-batch';
        try {
            $batchProbe = Http::withOptions(['connect_timeout' => 3])->timeout(10)
                ->post($batchUrl, ['sms_codes' => ['0']]);
            if ($batchProbe->status() === 404) {
                $this->error('mark-sent-batch route missing (HTTP 404). Restart erp-clients-api (python app.py) so batch ERP updates work.');
            } else {
                $this->info('mark-sent-batch route: HTTP ' . $batchProbe->status() . ' (expected 200 or 400, not 404)');
            }
        } catch (\Throwable $e) {
            $this->error('Cannot reach ERP API at ' . $base . ' — ' . $e->getMessage());
            $this->line('  Start: erp-clients-api\\start.bat  OR  composer run dev  (includes erp-api on port 5000)');
        }

        try {
            $pending = $messages->pendingCount();
            $this->info('Pending in ERP: ' . $pending);
        } catch (\Throwable $e) {
            $this->error('Cannot read pending count: ' . $e->getMessage());
        }

        $last = SmsLog::whereNotNull('erp_message_id')->orderByDesc('sent_at')->first();
        if ($last) {
            $mid = is_array($last->provider_response)
                ? ($last->provider_response['responses'][0]['messageid'] ?? null)
                : null;
            $this->line('Last CRM ERP SMS log: #' . $last->id . ' at ' . $last->sent_at . ' status=' . $last->status
                . ($mid ? ' advanta_id=' . $mid : ''));
        } else {
            $this->warn('No ERP SMS rows in sms_logs yet.');
        }

        if (config('queue.default') === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync — use database + queue:work so Send now does not hit browser timeouts.');
        }

        if (config('erp.messages_auto_send_enabled')) {
            $this->line('Scheduled task: erp:send-sms-messages every 5 min (requires schedule:work or cron).');
            if (! $this->isSchedulerLikelyRunning()) {
                $this->warn('No recent ERP SMS job log today — start: php artisan schedule:work');
                $this->line('  Dev: composer run dev (includes scheduler + queue:listen --timeout=660)');
            } else {
                $this->info('Recent ERP SMS scheduler activity found in logs.');
            }
        } else {
            $this->warn('Auto-send is OFF (ERP_MESSAGES_AUTO_SEND_ENABLED=false).');
        }

        if ($this->option('test-advanta')) {
            $mobile = trim((string) env('ADVANTA_TEST_MOBILE', ''));
            if ($mobile === '') {
                $this->warn('Set ADVANTA_TEST_MOBILE in .env to run a live Advanta test.');

                return self::SUCCESS;
            }
            $this->line('Sending Advanta test to ' . $mobile . ' ...');
            $result = $advanta->send($mobile, 'Geminia CRM SMS test — safe to ignore.');
            if ($result['success'] ?? false) {
                $this->info('Advanta OK — messageid: ' . ($result['advanta_message_id'] ?? 'see provider_response'));
            } else {
                $this->error('Advanta failed: ' . ($result['error'] ?? 'unknown'));
            }
        }

        $schedulerLog = storage_path('logs/erp-sms-scheduler.log');
        if (is_file($schedulerLog)) {
            $tail = array_slice(file($schedulerLog, FILE_IGNORE_NEW_LINES) ?: [], -5);
            if ($tail !== []) {
                $this->newLine();
                $this->line('Last lines of erp-sms-scheduler.log:');
                foreach ($tail as $line) {
                    $this->line('  ' . $line);
                }
            }
        } else {
            $this->warn('No erp-sms-scheduler.log yet — scheduler has not run erp:send-sms-messages since this log was added.');
            $this->line('  Start: scripts\\run-scheduler-loop.bat  OR  php artisan schedule:work');
        }

        $this->newLine();
        $this->comment('To process pending now: php artisan erp:send-sms-messages --limit=50');

        return self::SUCCESS;
    }

    private function isSchedulerLikelyRunning(): bool
    {
        $logFile = storage_path('logs/laravel.log');
        if (! is_file($logFile)) {
            return false;
        }
        $tail = @file_get_contents($logFile, false, null, max(0, filesize($logFile) - 80000));
        if (! is_string($tail)) {
            return false;
        }

        return str_contains($tail, 'ERP SMS messages job completed')
            && str_contains($tail, now()->format('Y-m-d'));
    }
}
