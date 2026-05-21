<?php

namespace App\Console\Commands;

use App\Services\ErpMessagingHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestErpMessagesHttpCommand extends Command
{
    protected $signature = 'erp:test-messages-api {--limit=1 : limit query param}';

    protected $description = 'Test erp-clients-api SMS pending (GET /messages/sms/pending then /api/messages/sms/pending) and /routes probe';

    public function handle(ErpMessagingHttpClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('No API base URL. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (or ERP_CLIENTS_HTTP_URL host).');

            return self::FAILURE;
        }

        $base = $client->baseUrl();
        $limit = max(1, min(50, (int) $this->option('limit')));
        $this->info('Resolved base URL (used for SMS): ' . $base);
        $this->line('From config — finance_http_base: ' . (trim((string) config('erp.finance_http_base')) !== '' ? (string) config('erp.finance_http_base') : '(empty)'));
        $this->line('From config — messages_http_base: ' . (trim((string) config('erp.messages_http_base')) !== '' ? (string) config('erp.messages_http_base') : '(empty, falls back to finance)'));
        $clientsUrl = trim((string) config('erp.clients_http_url', ''));
        $this->line('From config — ERP_CLIENTS_HTTP_URL: ' . ($clientsUrl !== '' ? $clientsUrl : '(empty)'));
        $this->comment('After changing .env: php artisan config:clear (or avoid config:cache until values are final).');

        $token = trim((string) config('erp.messages_http_token', ''));
        if ($token === '') {
            $token = trim((string) config('erp.finance_http_token'));
        }
        $this->line('Bearer token: ' . ($token !== '' ? '(set, ' . strlen($token) . ' chars)' : '(empty — API may return 401)'));

        // Same order as ErpMessagingHttpClient: no /api prefix first, then /api/...
        $paths = [
            '/messages/sms/pending',
            '/api/messages/sms/pending',
        ];

        $http = Http::withOptions(['connect_timeout' => 5])->timeout(60)->acceptJson();
        if ($token !== '') {
            $http = $http->withToken($token);
        }

        $ok = false;
        foreach ($paths as $path) {
            $url = $base . $path;
            $this->line('GET ' . $url . '?limit=' . $limit . ' ...');
            $r = $http->get($url, ['limit' => $limit]);
            $this->line('  → HTTP ' . $r->status());
            if ($r->successful()) {
                $data = $r->json('data');
                $count = is_array($data) ? count($data) : 0;
                $this->info('  OK — JSON data rows: ' . $count);
                $ok = true;
                break;
            }
            $err = $r->json('error');
            $this->warn('  ' . (is_string($err) ? $err : $r->body()));
        }

        $countRes = $http->get($base . '/messages/sms/pending', ['count_only' => 1]);
        if ($countRes->status() === 404) {
            $countRes = $http->get($base . '/api/messages/sms/pending', ['count_only' => 1]);
        }
        if ($countRes->successful() && $countRes->json('count') !== null) {
            $this->info('Pending count (fast): ' . (int) $countRes->json('count'));
        } else {
            $this->warn('count_only=1 not available — restart erp-clients-api with latest app.py');
        }

        $batchRes = $http->asJson()->post($base . '/messages/sms/mark-sent-batch', ['sms_codes' => ['0']]);
        if ($batchRes->status() === 404) {
            $batchRes = $http->asJson()->post($base . '/api/messages/sms/mark-sent-batch', ['sms_codes' => ['0']]);
        }
        if ($batchRes->status() === 404) {
            $this->error('mark-sent-batch missing (HTTP 404). Restart erp-clients-api — without it, sends are much slower and may time out.');
        } else {
            $this->info('mark-sent-batch route: HTTP ' . $batchRes->status() . ' (OK if not 404)');
        }

        if (! $ok) {
            $this->error('No working SMS pending route. Deploy latest erp-clients-api and restart the API process.');
            $this->newLine();
            $this->comment('Route table probe (no auth — confirms you hit erp-clients-api and code version):');
            $routesProbeWorked = false;
            foreach (['/routes', '/api/routes'] as $probe) {
                $url = $base . $probe;
                $this->line('GET ' . $url . ' ...');
                $probeRes = Http::withOptions(['connect_timeout' => 5])->timeout(30)->get($url);
                $this->line('  → HTTP ' . $probeRes->status());
                if (! $probeRes->successful()) {
                    continue;
                }
                $routes = $probeRes->json('routes');
                if (! is_array($routes)) {
                    $this->warn('  Unexpected JSON (not erp-clients-api /routes shape).');

                    continue;
                }
                $routesProbeWorked = true;
                $smsRules = [];
                foreach ($routes as $item) {
                    $rule = is_array($item) ? ($item['rule'] ?? '') : '';
                    if (is_string($rule) && str_contains($rule, 'messages/sms')) {
                        $smsRules[] = $rule;
                    }
                }
                if ($smsRules === []) {
                    $this->warn('  No rules containing "messages/sms" — API is running an old app.py without SMS routes.');
                } else {
                    $this->info('  SMS routes on server: ' . implode(', ', $smsRules));
                }
                break;
            }

            if (! $routesProbeWorked) {
                $this->warn('Neither /routes nor /api/routes returned JSON — FINANCE_ERP_HTTP_BASE / ERP_MESSAGES_HTTP_BASE may point at the wrong host (not erp-clients-api).');
            }

            return self::FAILURE;
        }

        $this->info('CRM tries /messages/... first, then /api/messages/... on 404 (same as finance routes).');

        return self::SUCCESS;
    }
}
