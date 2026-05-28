<?php

namespace App\Console\Commands;

use App\Jobs\SendErpSmsMessagesJob;
use App\Services\ErpSmsMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendErpSmsMessagesCommand extends Command
{
    protected $signature = 'erp:send-sms-messages
                            {--limit=50 : Maximum ERP messages to process}
                            {--dry-run : Preview messages without sending or updating ERP}
                            {--queue : Dispatch the queued job instead of running inline}';

    protected $description = 'Send ERP draft SMS only (pending status) and mark successful rows as sent';

    public function handle(ErpSmsMessageService $messages): int
    {
        $this->info('[' . now()->toDateTimeString() . '] ERP draft SMS send started (limit ' . (int) $this->option('limit') . ').');
        $limit = max(1, min(500, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        if (! $messages->canLoadPendingMessages()) {
            $error = 'Cannot load ERP SMS rows. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (erp-clients-api with /messages/sms routes) or enable OCI8 on this server.';
            $this->error($error);
            $this->notifyFailure($error, [
                'limit' => $limit,
                'dry_run' => $dryRun,
            ]);

            return self::FAILURE;
        }

        if (! $messages->isReady()) {
            $error = 'ERP SMS messaging is not ready. Check ERP and Advanta SMS configuration.';
            $this->error($error);
            $this->notifyFailure($error, [
                'limit' => $limit,
                'dry_run' => $dryRun,
            ]);

            return self::FAILURE;
        }

        if ((bool) $this->option('queue')) {
            SendErpSmsMessagesJob::dispatch($limit, $dryRun);
            $this->info(sprintf(
                'Queued ERP SMS job for up to %d message(s)%s.',
                $limit,
                $dryRun ? ' (dry run)' : ''
            ));

            return self::SUCCESS;
        }

        try {
            $summary = $messages->sendPending($limit, $dryRun);
        } catch (Throwable $e) {
            $error = 'ERP SMS send crashed: ' . $e->getMessage();
            $this->error($error);
            $this->notifyFailure($error, [
                'limit' => $limit,
                'dry_run' => $dryRun,
            ]);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Processed %d ERP SMS message(s): %d sent, %d failed, %d skipped%s.',
            $summary['processed'],
            $summary['sent'],
            $summary['failed'],
            $summary['skipped'],
            $dryRun ? ' (dry run)' : ''
        ));

        foreach (array_slice($summary['results'], 0, 20) as $result) {
            $status = $result['success'] ? ($result['skipped'] ? 'preview' : 'sent') : 'failed';
            $line = sprintf('%s  %s  %s', $status, $result['message_id'] ?? '-', $result['phone'] ?? '-');
            if (! empty($result['error'])) {
                $line .= '  ' . $result['error'];
            }
            $this->line($line);
        }

        if (! $dryRun && (int) ($summary['failed'] ?? 0) > 0) {
            $this->notifyFailure(
                sprintf(
                    'ERP SMS send completed with %d failure(s) out of %d processed.',
                    (int) ($summary['failed'] ?? 0),
                    (int) ($summary['processed'] ?? 0)
                ),
                [
                    'limit' => $limit,
                    'dry_run' => $dryRun,
                    'sent' => (int) ($summary['sent'] ?? 0),
                    'failed' => (int) ($summary['failed'] ?? 0),
                    'skipped' => (int) ($summary['skipped'] ?? 0),
                    'results' => array_slice((array) ($summary['results'] ?? []), 0, 10),
                ]
            );
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function notifyFailure(string $headline, array $context = []): void
    {
        if (! (bool) config('erp.messages_failure_notify_enabled', true)) {
            return;
        }

        $to = trim((string) config('erp.messages_failure_notify_recipient', ''));
        if ($to === '') {
            return;
        }
        $cc = (array) config('erp.messages_failure_notify_cc', []);
        $cc = array_values(array_filter(array_map(
            static fn ($email) => trim((string) $email),
            $cc
        )));

        $subject = 'ERP SMS Failure Alert - ' . now()->format('Y-m-d H:i:s');
        $lines = [
            $headline,
            '',
            'Host: ' . gethostname(),
            'App env: ' . config('app.env'),
            'Time: ' . now()->toDateTimeString(),
            '',
            'Context:',
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];
        $body = implode(PHP_EOL, $lines);

        try {
            Mail::raw($body, function ($message) use ($to, $cc, $subject) {
                $message->to($to)->subject($subject);
                if ($cc !== []) {
                    $message->cc($cc);
                }
            });
        } catch (Throwable $e) {
            Log::error('ERP SMS failure alert email send failed', [
                'to' => $to,
                'cc' => $cc,
                'error' => $e->getMessage(),
                'headline' => $headline,
            ]);
        }
    }
}
