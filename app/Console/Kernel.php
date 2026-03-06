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

        if (filter_var(env('MAIL_AUTO_FETCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $schedule->command('mail:fetch')->everyFiveMinutes();
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
