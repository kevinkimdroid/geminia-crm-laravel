<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TestGraphConnectionCommand extends Command
{
    protected $signature = 'mail:test-graph';

    protected $description = 'Test Microsoft Graph token and connection';

    public function handle(): int
    {
        $this->info('Testing Microsoft Graph configuration...');

        $enabled = config('microsoft-graph.enabled');
        $tenant = config('microsoft-graph.tenant_id');
        $clientId = config('microsoft-graph.client_id');
        $clientSecret = config('microsoft-graph.client_secret');
        $mailbox = config('microsoft-graph.mailbox');

        $this->table(
            ['Setting', 'Value'],
            [
                ['MSGRAPH_ENABLED', $enabled ? 'true' : 'false'],
                ['MSGRAPH_TENANT_ID', $tenant ?: '(empty)'],
                ['MSGRAPH_CLIENT_ID', $clientId ? substr($clientId, 0, 8) . '...' : '(empty)'],
                ['MSGRAPH_CLIENT_SECRET', $clientSecret ? '***' . substr($clientSecret, -4) : '(empty)'],
                ['MSGRAPH_MAILBOX', $mailbox ?: '(empty)'],
            ]
        );

        if (! $enabled || ! $tenant || ! $clientId || ! $clientSecret || ! $mailbox) {
            $this->error('Missing or incomplete MSGRAPH_* config in .env');
            return self::FAILURE;
        }

        // Clear cached token so we get a fresh attempt
        Cache::forget('msgraph_token_' . md5($clientId));

        $url = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenant);

        $this->line('');
        $this->line('Requesting access token from Azure...');

        $response = Http::asForm()
            ->timeout(15)
            ->post($url, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        $status = $response->status();
        $body = $response->json() ?? $response->body();

        if (! $response->successful()) {
            $this->error("Token request failed: HTTP {$status}");
            $this->line('');
            if (is_array($body)) {
                $error = $body['error'] ?? 'unknown';
                $desc = $body['error_description'] ?? json_encode($body);
                $this->line("Error: {$error}");
                $this->line("Description: {$desc}");
            } else {
                $this->line($body);
            }
            $this->newLine();
            $this->line('Common fixes:');
            $this->line('  • Invalid client_secret: Copy the secret VALUE from Azure (Certificates & secrets), not the Secret ID.');
            $this->line('  • Secret expired: Create a new client secret in Azure.');
            $this->line('  • Wrong tenant_id: Use Directory (tenant) ID from Azure overview.');
            $this->line('  • Run: php artisan config:clear');
            return self::FAILURE;
        }

        $this->info('Access token obtained successfully.');

        // Try a quick Graph API call
        $token = $response->json()['access_token'] ?? null;
        if ($token) {
            $this->line('');
            $this->line('Testing Graph API (fetching mailbox messages)...');
            $mailboxEncoded = rawurlencode($mailbox);
            $graphUrl = "https://graph.microsoft.com/v1.0/users/{$mailboxEncoded}/mailFolders/inbox/messages?\$top=1";

            $graphResponse = Http::withToken($token)
                ->timeout(15)
                ->get($graphUrl);

            if ($graphResponse->successful()) {
                $this->info('Graph API: Connected. Mail.Read permission OK.');
                return self::SUCCESS;
            }

            $this->warn('Graph API call failed: ' . $graphResponse->status());
            $graphBody = $graphResponse->json();
            $msg = is_array($graphBody) && isset($graphBody['error']['message'])
                ? $graphBody['error']['message']
                : (string) ($graphResponse->body() ?? '');
            $this->line($msg);
            $this->newLine();
            $this->line('403 = Missing permission or admin consent. In Azure:');
            $this->line('  1. App registrations → Geminia CRM Mail → API permissions');
            $this->line('  2. Add permission → Microsoft Graph → Application permissions');
            $this->line('  3. Search Mail.Read → check it → Add permissions');
            $this->line('  4. Click "Grant admin consent for [Your org]"');
        }

        return self::SUCCESS;
    }
}
