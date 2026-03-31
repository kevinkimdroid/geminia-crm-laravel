<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ErpTestMortgageRenewalsCommand extends Command
{
    protected $signature = 'erp:test-mortgage-renewals {--window=30 : Days ahead for MENDR_RENEWAL_DATE window}';

    protected $description = 'Verify ERP HTTP API returns filtered mortgage renewal counts (not the full 564-row list)';

    public function handle(): int
    {
        $url = rtrim((string) config('erp.clients_http_url'), '/');
        if ($url === '') {
            $this->error('Set ERP_CLIENTS_HTTP_URL in .env then run: php artisan config:clear');

            return 1;
        }

        $window = max(1, min(120, (int) $this->option('window')));
        $from = Carbon::now()->startOfDay()->format('Y-m-d');
        $to = Carbon::now()->startOfDay()->addDays($window)->format('Y-m-d');

        $this->info('ERP URL: '.$url);
        $this->line("Window: {$window} days ({$from} … {$to} inclusive)");
        $this->newLine();

        $baseQuery = [
            'system' => 'mortgage',
            'mortgage_upcoming_renewals' => '1',
            'mendr_window_days' => $window,
            'mendr_renewal_from' => $from,
            'mendr_renewal_to' => $to,
        ];

        $filteredTotal = null;
        try {
            $res = Http::timeout(45)->get($url, array_merge($baseQuery, ['count_only' => '1']));
            if (! $res->successful()) {
                $this->error('HTTP '.$res->status().' '.$res->body());

                return 1;
            }
            $filteredTotal = (int) ($res->json()['total'] ?? 0);
            $this->info("Count (renewal window): {$filteredTotal}");
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        try {
            $res = Http::timeout(45)->get($url, array_merge($baseQuery, ['limit' => '2', 'offset' => '0']));
            if ($res->successful()) {
                $json = $res->json();
                $data = $json['data'] ?? [];
                $this->line('Sample rows (check MENDR dates fall in window):');
                foreach (array_slice($data, 0, 2) as $row) {
                    $pol = $row['policy_number'] ?? $row['pol_policy_no'] ?? '?';
                    $md = $row['mendr_renewal_date'] ?? $row['mendrRenewalDate'] ?? '?';
                    $this->line("  • {$pol}  MENDR: {$md}");
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Sample fetch failed: '.$e->getMessage());
        }

        $this->newLine();
        try {
            $all = Http::timeout(45)->get($url, [
                'system' => 'mortgage',
                'count_only' => '1',
            ]);
            if ($all->successful()) {
                $full = (int) ($all->json()['total'] ?? 0);
                $this->line("Full mortgage view count (no date filter): {$full}");
                if ($full > 0 && $filteredTotal !== null) {
                    if ($filteredTotal >= $full) {
                        $this->warn('Filtered count is NOT smaller than full count — restart erp-clients-api so the latest app.py is loaded, or check API logs.');
                    } else {
                        $this->info('OK: renewal filter reduces the list.');
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Could not compare full count: '.$e->getMessage());
        }

        $this->newLine();
        $this->line('If counts look wrong, restart the Python service that serves this URL (see erp-clients-api/README.md).');

        return 0;
    }
}
