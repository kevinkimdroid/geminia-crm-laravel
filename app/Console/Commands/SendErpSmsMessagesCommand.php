<?php

namespace App\Console\Commands;

use App\Jobs\SendErpSmsMessagesJob;
use App\Services\ErpSmsMessageService;
use Illuminate\Console\Command;

class SendErpSmsMessagesCommand extends Command
{
    protected $signature = 'erp:send-sms-messages
                            {--limit=50 : Maximum ERP messages to process}
                            {--dry-run : Preview messages without sending or updating ERP}
                            {--queue : Dispatch the queued job instead of running inline}';

    protected $description = 'Send ERP draft SMS only (pending status) and mark successful rows as sent';

    public function handle(ErpSmsMessageService $messages): int
    {
        if (! $messages->canLoadPendingMessages()) {
            $this->error('Cannot load ERP SMS rows. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (erp-clients-api with /messages/sms routes) or enable OCI8 on this server.');

            return self::FAILURE;
        }

        if (! $messages->isReady()) {
            $this->error('ERP SMS messaging is not ready. Check ERP and Advanta SMS configuration.');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');
        if ((bool) $this->option('queue')) {
            SendErpSmsMessagesJob::dispatch($limit, $dryRun);
            $this->info(sprintf(
                'Queued ERP SMS job for up to %d message(s)%s.',
                $limit,
                $dryRun ? ' (dry run)' : ''
            ));

            return self::SUCCESS;
        }

        $summary = $messages->sendPending($limit, $dryRun);

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

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
