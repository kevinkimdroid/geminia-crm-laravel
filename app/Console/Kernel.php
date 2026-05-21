<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('maturities:sync')->dailyAt('06:00');
        $schedule->command('tickets:create-maturity-reminders')->dailyAt('08:00');
        $schedule->command('tickets:sla-violation-reminders')->hourly();

        if (filter_var(env('PBX_CDR_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $schedule->command('pbx:sync-cdr --minutes=30 --limit=500')->everyMinute();
            $schedule->command('pbx:health')->everyFiveMinutes();
        }

        if (filter_var(env('MAIL_AUTO_FETCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $schedule->command('mail:fetch')->everyFiveMinutes();
        }

        if (config('erp.agency_advances_notify_enabled', false)) {
            $schedule->command('finance:notify-agency-advances')->dailyAt('07:30');
        }

        if (config('erp.messages_auto_send_enabled', false)) {
            $limit = max(1, min(500, (int) config('erp.messages_auto_send_limit', 50)));
            // Run as artisan command (not queued job) so schedule:work is not limited by queue:listen --timeout=60.
            $schedule->command('erp:send-sms-messages', ['--limit' => $limit])
                ->everyFiveMinutes()
                ->withoutOverlapping(15)
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
