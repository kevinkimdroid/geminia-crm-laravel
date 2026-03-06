<?php

namespace App\Console\Commands;

use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class TestFeedbackEmailCommand extends Command
{
    protected $signature = 'feedback:test-email
                            {--to= : Override recipient (default: TICKET_FEEDBACK_NOTIFY_EMAIL)}';
    protected $description = 'Send a test feedback notification email to verify delivery';

    public function handle(): int
    {
        $to = $this->option('to') ?: config('tickets.feedback_request.notify_email', 'life@geminialife.co.ke');
        if (empty($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('No valid recipient. Set TICKET_FEEDBACK_NOTIFY_EMAIL in .env or use --to=email@example.com');
            return 1;
        }

        $this->info("Sending test feedback email to: {$to}");

        $notifier = app(TicketNotificationService::class);
        $sent = $notifier->sendFeedbackReceivedNotification(
            0,
            'TT-TEST',
            'Test feedback notification',
            'Test Contact',
            'happy',
            'This is a test to verify feedback emails are delivered.'
        );

        if ($sent) {
            $this->info('Email sent. Check inbox (and spam/junk) at ' . $to);
            return 0;
        }

        $this->error('Email failed. Check storage/logs/laravel.log for details.');
        return 1;
    }
}
