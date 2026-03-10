<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ErpTestConnectionCommand extends Command
{
    protected $signature = 'erp:test-connection';

    protected $description = 'Test ERP API and Oracle connectivity (diagnose Oracle connection failed)';

    public function handle(): int
    {
        $url = config('erp.clients_http_url');
        if (empty($url)) {
            $this->error('ERP_CLIENTS_HTTP_URL is not set in .env');
            $this->line('Add: ERP_CLIENTS_HTTP_URL=http://127.0.0.1:5000/clients');
            $this->line('Then: php artisan config:clear');
            return 1;
        }

        $this->info('ERP_CLIENTS_HTTP_URL: ' . $url);
        $this->newLine();

        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . ':' . ($parsed['port'] ?? '5000');

        // 1. Test /health (Oracle connectivity from API)
        $this->info('1. Testing ERP API health (Oracle connection)...');
        try {
            $health = Http::timeout(10)->get($base . '/health');
            if ($health->successful()) {
                $body = $health->json();
                if (($body['status'] ?? '') === 'ok') {
                    $this->info('   ✓ API is running. Oracle connected.');
                } else {
                    $this->error('   ✗ API returned: ' . ($body['message'] ?? $health->body()));
                    $this->line('   → Fix erp-clients-api/.env (ORACLE_DSN, ORACLE_USER, ORACLE_PASSWORD)');
                    return 1;
                }
            } else {
                $this->error('   ✗ Cannot reach ERP API. Status: ' . $health->status());
                $this->line('   Body: ' . $health->body());
                $this->newLine();
                $this->warn('   → Is geminia-erp-api running? systemctl status geminia-erp-api');
                $this->warn('   → SELinux may block Apache: sudo setsebool -P httpd_can_network_connect 1');
                $this->warn('   → Try from shell: curl ' . $base . '/health');
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('   ✗ Request failed: ' . $e->getMessage());
            $this->newLine();
            $this->warn('   → ERP API not running or unreachable. Start: sudo systemctl start geminia-erp-api');
            $this->warn('   → Or run manually: cd erp-clients-api && python3 app.py');
            return 1;
        }

        $this->newLine();

        // 2. Test /clients
        $this->info('2. Testing /clients endpoint...');
        try {
            $response = Http::timeout(15)->get($url, ['limit' => 1]);
            if ($response->successful()) {
                $body = $response->json();
                $total = $body['total'] ?? 0;
                $data = $body['data'] ?? $body['clients'] ?? [];
                $this->info('   ✓ Clients OK. Total: ' . $total . ', fetched: ' . count($data));
            } else {
                $err = $response->json()['error'] ?? $response->body();
                $this->error('   ✗ API error: ' . $err);
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('   ✗ Request failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('All checks passed. ERP connection is OK.');
        $this->line('If the web app still shows "Oracle connection failed", clear config cache: php artisan config:clear');

        return 0;
    }
}
