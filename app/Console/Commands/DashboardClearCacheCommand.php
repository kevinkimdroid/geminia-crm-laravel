<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DashboardClearCacheCommand extends Command
{
    protected $signature = 'dashboard:clear-cache
                            {--all : Clear entire application cache (includes dashboard)}';

    protected $description = 'Clear dashboard stats cache so numbers refresh correctly';

    public function handle(): int
    {
        if ($this->option('all')) {
            $this->call('cache:clear');
            $this->info('Application cache cleared. Dashboard numbers will refresh on next load.');
            return self::SUCCESS;
        }

        $keys = [
            'geminia_dashboard_stats_all',
            'geminia_contacts_count',
            'geminia_leads_count',
            'geminia_deals_count',
            'geminia_clients_count',
            'erp_clients_cache_total',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Bump clients list version so all clients_list_* caches become stale
        Cache::put('clients_list_version', time(), 86400);

        // Clear per-user dashboard stats (geminia_dashboard_stats_{userId})
        try {
            $users = \App\Models\VtigerUser::pluck('id');
            foreach ($users as $id) {
                Cache::forget('geminia_dashboard_stats_' . $id);
            }
        } catch (\Throwable $e) {
            // Ignore if vtiger users table not available
        }

        $this->info('Dashboard cache cleared. Numbers will refresh on next load.');
        return self::SUCCESS;
    }
}
