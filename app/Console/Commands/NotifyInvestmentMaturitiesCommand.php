<?php

namespace App\Console\Commands;

use App\Services\InvestmentMaturityService;
use App\Services\MicrosoftGraphMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyInvestmentMaturitiesCommand extends Command
{
    protected $signature = 'maturities:notify-investment
                            {--days=14 : Due window in days}
                            {--resend : Resend all due rows, including already-sent}
                            {--dry-run : Show rows without sending}';

    protected $description = 'Email investment maturities due in the next N days, skipping already-sent policies';

    public function handle(InvestmentMaturityService $service): int
    {
        $days = max(1, min(30, (int) $this->option('days')));
        $resend = (bool) $this->option('resend');
        $to = trim((string) config('maturities.investment_notifications.to', 'douglas.nyakwara@geminialife.co.ke'));
        $cc = $this->ccRecipients();

        if (! $service->notificationsTableExists()) {
            $this->error('Tracking table missing. Run php artisan migrate.');

            return self::FAILURE;
        }

        try {
            $due = $service->dueWithinDays($days);
            $unsent = $service->unsentRows($due, $to);
            $targetRows = $resend ? $due : $unsent;
            $this->info('Due rows: ' . $due->count() . ' | New unsent rows: ' . $unsent->count() . ' | To send now: ' . $targetRows->count());

            if ($targetRows->isEmpty()) {
                $this->comment('No new rows to email.');

                return self::SUCCESS;
            }

            if ($this->option('dry-run')) {
                foreach ($targetRows->take(20) as $row) {
                    $this->line(
                        trim((string) ($row->pol_policy_no ?? '')) . ' | '
                        . trim((string) ($row->pol_maturity_date ?? '')) . ' | '
                        . trim((string) ($row->full_name ?? ''))
                    );
                }

                return self::SUCCESS;
            }

            $subject = '[Maturities] Investment policies due in next ' . $days . ' days (' . $targetRows->count() . ')';
            $html = view('emails.investment-maturities-notification', [
                'rows' => $targetRows,
                'days' => $days,
                'generatedAt' => now(),
                'resend' => $resend,
            ])->render();

            if (! $this->sendNotificationEmail($to, $cc, $subject, $html)) {
                $this->error('Failed: could not send email (Graph/SMTP failed).');

                return self::FAILURE;
            }

            $service->markAsSent($targetRows, $to, $cc !== [] ? implode(',', $cc) : null);
            $this->info('Email sent to ' . $to . ' (cc: ' . ($cc !== [] ? implode(', ', $cc) : 'none') . ').');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function ccRecipients(): array
    {
        $cc = config('maturities.investment_notifications.cc', []);
        if (is_string($cc)) {
            $cc = explode(',', $cc);
        }

        return array_values(array_filter(array_map(fn ($value) => trim((string) $value), (array) $cc)));
    }

    /**
     * @param  array<int, string>  $cc
     */
    private function sendNotificationEmail(string $to, array $cc, string $subject, string $html): bool
    {
        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            $ok = $graph->sendMail($to, null, $subject, $html, true);
            if (! $ok) {
                Log::warning('Investment maturities command: Graph send failed for primary recipient', ['to' => $to]);
            } else {
                foreach ($cc as $ccAddress) {
                    $ccOk = $graph->sendMail($ccAddress, null, $subject, $html, true);
                    if (! $ccOk) {
                        Log::warning('Investment maturities command: Graph send failed for CC recipient', ['cc' => $ccAddress]);
                    }
                }

                return true;
            }
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Mail::send([], [], function ($message) use ($to, $cc, $subject, $html) {
                    $message->to($to)->subject($subject)->setBody($html, 'text/html');
                    if ($cc !== []) {
                        $message->cc($cc);
                    }
                });

                return true;
            } catch (\Throwable $e) {
                Log::warning('Investment maturities command: SMTP send failed', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(800 * 1000);
                }
            }
        }

        return false;
    }
}

