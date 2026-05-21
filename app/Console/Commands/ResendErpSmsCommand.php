<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Services\AdvantaSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResendErpSmsCommand extends Command
{
    protected $signature = 'erp:resend-sms
                            {--from= : Start date (Y-m-d), default yesterday 00:00}
                            {--to= : End date (Y-m-d), default today end}
                            {--days=2 : Used when --from omitted: calendar days back from today inclusive}
                            {--dry-run : List messages only, do not call Advanta}
                            {--force : Send without confirmation prompt}
                            {--history-resend : Required when ERP_SMS_ALLOW_HISTORY_RESEND is false}';

    protected $description = '[Disabled by default] Resend old SMS from CRM logs — use erp:send-sms-messages for draft only';

    public function handle(AdvantaSmsService $sms): int
    {
        if (! config('erp.messages_allow_history_resend', false) && ! $this->option('history-resend')) {
            $this->error('History resend is disabled. Only ERP draft SMS are sent.');
            $this->line('  Send drafts: php artisan erp:send-sms-messages');
            $this->line('  Or use Tools → ERP Messaging → Send draft SMS');

            return self::FAILURE;
        }

        if (! $sms->isConfigured()) {
            $this->error('Advanta SMS is not configured (ADVANTA_* in .env).');

            return self::FAILURE;
        }

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        [$from, $to] = $this->resolveRange();
        $dryRun = (bool) $this->option('dry-run');

        $logs = SmsLog::query()
            ->whereNotNull('erp_message_id')
            ->where('sent_at', '>=', $from)
            ->where('sent_at', '<=', $to)
            ->orderByDesc('sent_at')
            ->get();

        $rows = [];
        foreach ($logs as $log) {
            $id = trim((string) $log->erp_message_id);
            if ($id === '') {
                continue;
            }
            if (isset($rows[$id])) {
                continue;
            }
            $phone = trim((string) $log->phone);
            $message = trim((string) $log->message);
            if ($phone === '' || $message === '') {
                continue;
            }
            $rows[$id] = [
                'erp_message_id' => $id,
                'erp_policy_no' => $log->erp_policy_no,
                'phone' => $phone,
                'message' => $message,
                'original_sent_at' => $log->sent_at,
            ];
        }

        $count = count($rows);
        if ($count === 0) {
            $this->warn(sprintf(
                'No ERP SMS logs found between %s and %s.',
                $from->format('Y-m-d H:i'),
                $to->format('Y-m-d H:i')
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d unique ERP message(s) to resend (%s → %s)%s.',
            $count,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $dryRun ? ' [dry run]' : ''
        ));

        if (! $dryRun && ! $this->option('force') && ! $this->confirm("Resend {$count} SMS now? Customers may receive duplicate texts.", false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        if ($dryRun) {
            $this->table(
                ['ERP ID', 'Phone', 'Originally sent', 'Preview'],
                collect($rows)->take(25)->map(fn ($r) => [
                    $r['erp_message_id'],
                    $r['phone'],
                    $r['original_sent_at']?->format('Y-m-d H:i') ?? '—',
                    \Illuminate\Support\Str::limit($r['message'], 60),
                ])->all()
            );
            if ($count > 25) {
                $this->line('… and ' . ($count - 25) . ' more.');
            }

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($rows as $row) {
            $bar->advance();
            $normalized = $sms->normalizePhone($row['phone']);

            $result = $sms->send($normalized, $row['message']);
            $success = (bool) ($result['success'] ?? false);
            $error = $success ? null : (string) ($result['error'] ?? 'SMS send failed.');

            try {
                SmsLog::create([
                    'contact_id' => null,
                    'erp_message_id' => $row['erp_message_id'],
                    'erp_policy_no' => $row['erp_policy_no'],
                    'phone' => $normalized,
                    'message' => $row['message'],
                    'status' => $success ? 'sent' : 'failed',
                    'delivery_status' => $success ? 'submitted' : 'failed',
                    'error_message' => $error,
                    'provider_response' => is_array($result['response'] ?? null) ? $result['response'] : null,
                    'user_id' => null,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('ERP SMS resend log failed', ['error' => $e->getMessage(), 'erp_message_id' => $row['erp_message_id']]);
            }

            if ($success) {
                $sent++;
            } else {
                $failed++;
                Log::warning('ERP SMS resend failed', [
                    'erp_message_id' => $row['erp_message_id'],
                    'phone' => $normalized,
                    'error' => $error,
                ]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Resend complete: {$sent} sent, {$failed} failed (of {$count}).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(): array
    {
        $fromOpt = trim((string) $this->option('from'));
        $toOpt = trim((string) $this->option('to'));

        if ($fromOpt !== '') {
            $from = Carbon::parse($fromOpt)->startOfDay();
            $to = $toOpt !== ''
                ? Carbon::parse($toOpt)->endOfDay()
                : now()->endOfDay();

            return [$from, $to];
        }

        $days = max(1, min(31, (int) $this->option('days')));
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        return [$from, $to];
    }
}
