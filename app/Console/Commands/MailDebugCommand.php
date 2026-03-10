<?php

namespace App\Console\Commands;

use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MailDebugCommand extends Command
{
    protected $signature = 'mail:debug
                            {--fetch : Run mail:fetch and show results}
                            {--logs=20 : Number of recent log lines to show}';

    protected $description = 'Debug email auto-fetch and auto-ticket creation (run on server)';

    public function handle(MailService $mailService): int
    {
        $this->info('=== Email Auto-Create Debug ===');
        $this->newLine();

        // 1. Config checks
        $this->info('1. Configuration');
        $mailFetchEnabled = filter_var(env('MAIL_AUTO_FETCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $ticketAutoEnabled = filter_var(env('TICKET_AUTO_FROM_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $complaintAutoEnabled = filter_var(env('COMPLAINT_AUTO_FROM_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['MAIL_AUTO_FETCH_ENABLED', env('MAIL_AUTO_FETCH_ENABLED', 'true'), $mailFetchEnabled ? '✓ OK' : '✗ OFF – scheduler will skip mail:fetch'],
                ['TICKET_AUTO_FROM_EMAIL_ENABLED', env('TICKET_AUTO_FROM_EMAIL_ENABLED', 'false'), $ticketAutoEnabled ? '✓ OK' : '✗ OFF – no tickets from email'],
                ['COMPLAINT_AUTO_FROM_EMAIL_ENABLED', env('COMPLAINT_AUTO_FROM_EMAIL_ENABLED', 'false'), $complaintAutoEnabled ? '✓ OK' : '✗ OFF – no complaints from email'],
            ]
        );

        if (! $ticketAutoEnabled && ! $complaintAutoEnabled) {
            $this->warn('   → Enable at least one: TICKET_AUTO_FROM_EMAIL_ENABLED=true or COMPLAINT_AUTO_FROM_EMAIL_ENABLED=true');
        }
        $this->newLine();

        // 2. Email backend detection
        $this->info('2. Email Backend');
        $backend = 'IMAP';
        if ($mailService->useMicrosoftGraph()) {
            $backend = 'Microsoft Graph';
        } elseif ($mailService->useHttpEmailService()) {
            $backend = 'HTTP API';
        }

        $this->line("   Detected: <info>{$backend}</info>");
        if ($backend === 'Microsoft Graph') {
            $this->line('   MSGRAPH_ENABLED=true, MSGRAPH_CLIENT_ID=*, MSGRAPH_MAILBOX=' . config('microsoft-graph.mailbox', '(not set)'));
        } elseif ($backend === 'HTTP API') {
            $this->line('   EMAIL_SERVICE_URL=' . config('email-service.url', '(not set)'));
        } else {
            $this->line('   Using MAIL_MAILER, MAIL_HOST, MAIL_USERNAME');
            $this->line('   MAIL_HOST=' . config('mail.mailers.smtp.host', env('MAIL_HOST', '(not set)')));
        }
        $this->newLine();

        // 3. Cron / Scheduler
        $this->info('3. Scheduler (must run every minute)');
        $this->line('   Cron should run: * * * * * cd /path/to/geminia-crm-laravel && php artisan schedule:run');
        $this->line('   On CentOS (Apache user): crontab -u apache -e');
        $this->line('   Or: crontab -u nginx -e  (if using Nginx)');
        $this->line('   Verify: php artisan schedule:list');
        $this->newLine();

        // 4. Recent emails in DB
        $this->info('4. Recent stored emails');
        try {
            $count = DB::connection('vtiger')->table('mail_manager_emails')->count();
            $recent = DB::connection('vtiger')
                ->table('mail_manager_emails')
                ->orderByDesc('id')
                ->limit(3)
                ->get(['id', 'from_address', 'subject', 'created_at']);
            $this->line("   Total in DB: {$count}");
            if ($recent->isNotEmpty()) {
                foreach ($recent as $e) {
                    $this->line("   - #{$e->id} from {$e->from_address}: " . substr($e->subject ?? '(no subject)', 0, 50));
                }
            } else {
                $this->warn('   No emails stored yet. Run: php artisan mail:fetch');
            }
        } catch (\Throwable $e) {
            $this->error('   Error: ' . $e->getMessage());
        }
        $this->newLine();

        // 5. Run fetch if requested
        if ($this->option('fetch')) {
            $this->info('5. Running mail:fetch...');
            try {
                $result = $mailService->fetchAndStoreEmails('INBOX', 10);
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Fetched', $result['fetched']],
                        ['Stored (new)', $result['stored']],
                    ]
                );
                if (! empty($result['errors'])) {
                    $this->warn('Errors:');
                    foreach ($result['errors'] as $err) {
                        $this->line("   - {$err}");
                    }
                }
                if ($result['fetched'] === 0 && empty($result['errors'])) {
                    $this->warn('   No emails fetched. Check: connection to mail server, credentials.');
                }
            } catch (\Throwable $e) {
                $this->error('   Fetch failed: ' . $e->getMessage());
            }
            $this->newLine();
        } else {
            $this->line('5. Run with --fetch to test mail:fetch: php artisan mail:debug --fetch');
            $this->newLine();
        }

        // 6. Recent logs
        $logCount = (int) $this->option('logs');
        $logPath = storage_path('logs/laravel.log');
        $this->info("6. Recent log lines (mail/ticket related, last {$logCount})");
        if (File::exists($logPath)) {
            $lines = $this->tailFile($logPath, 500);
            $filtered = $this->filterLogLines($lines, ['mail', 'Mail', 'ticket', 'Ticket', 'fetch', 'Graph', 'IMAP', 'Connection'], $logCount);
            if ($filtered !== []) {
                foreach ($filtered as $line) {
                    $this->line('   ' . $line);
                }
            } else {
                $this->line('   (no matching log lines)');
            }
        } else {
            $this->line('   Log file not found: ' . $logPath);
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line('If emails are fetched but no tickets: check TICKET_AUTO_FROM_EMAIL_ENABLED=true');
        $this->line('If sender domain is excluded: see EMAIL_EXCLUDED_SENDER_DOMAINS in config/email-service.php');
        $this->line('Logs: tail -f storage/logs/laravel.log');

        return 0;
    }

    protected function tailFile(string $path, int $lines): array
    {
        $content = File::get($path);
        $all = explode("\n", $content);
        return array_slice($all, -$lines);
    }

    protected function filterLogLines(array $lines, array $keywords, int $limit): array
    {
        $out = [];
        foreach (array_reverse($lines) as $line) {
            if ($limit > 0 && count($out) >= $limit) {
                break;
            }
            foreach ($keywords as $kw) {
                if (str_contains($line, $kw)) {
                    $out[] = trim($line);
                    break;
                }
            }
        }
        return array_reverse($out);
    }
}
