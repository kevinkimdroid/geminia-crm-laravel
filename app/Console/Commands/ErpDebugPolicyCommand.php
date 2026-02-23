<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ErpDebugPolicyCommand extends Command
{
    protected $signature = 'erp:debug-policy {policy : Policy number to fetch (e.g. 0908076694)}';

    protected $description = 'Fetch raw API response for a policy to debug null data';

    public function handle(): int
    {
        $policy = $this->argument('policy');
        $url = config('erp.clients_http_url');

        if (empty($url)) {
            $this->error('ERP_CLIENTS_HTTP_URL not set in .env');
            return 1;
        }

        $url = rtrim($url, '/');
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $fullUrl = $url . $sep . 'policy=' . urlencode($policy) . '&limit=1';

        $this->info("Fetching: {$fullUrl}");
        $this->newLine();

        $response = Http::timeout(15)->get($fullUrl);

        if (! $response->successful()) {
            $this->error('API request failed: ' . $response->status());
            $this->line($response->body());
            return 1;
        }

        $body = $response->json();
        $rows = $body['data'] ?? $body['clients'] ?? [];
        $row = $rows[0] ?? null;

        if (! $row) {
            $this->warn('No data returned for policy ' . $policy);
            if (! empty($body['error'])) {
                $this->error('API error: ' . $body['error']);
            }
            return 1;
        }

        $this->info('API returned data. Keys in response:');
        $this->table(
            ['Key', 'Value'],
            collect($row)->map(fn ($v, $k) => [$k, $v === null ? '(null)' : (is_string($v) && strlen($v) > 60 ? substr($v, 0, 57) . '...' : $v)])->values()->all()
        );

        $this->newLine();
        $this->info('Fields we need for the view:');
        $needed = ['maturity', 'maturity_date', 'prp_dob', 'effective_date', 'kra_pin', 'id_no', 'phone_no', 'paid_mat_amt', 'checkoff'];
        $missing = [];
        foreach ($needed as $key) {
            $val = $row[$key] ?? $row[ucfirst($key)] ?? null;
            $status = ($val !== null && $val !== '') ? '✓' : '✗ NULL';
            if ($val === null || $val === '') {
                $missing[] = $key;
            }
            $this->line("  {$key}: " . ($val ?? '(null)') . " {$status}");
        }

        if (! empty($missing)) {
            $this->newLine();
            $this->warn('The API is NOT returning: ' . implode(', ', $missing));
            $this->line('→ Update erp-clients-api/.env ERP_CLIENTS_LIST_COLUMNS to include the Oracle column names.');
            $parsed = parse_url($url);
            $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : ':5000');
            $this->line('→ Check view columns: ' . $base . '/columns');
        }

        return 0;
    }
}
