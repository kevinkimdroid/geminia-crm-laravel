<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Office 365 emails via Microsoft Graph API.
 * Best for Office 365: no IMAP quirks (NOOP etc), OAuth2, works with MFA.
 */
class MicrosoftGraphMailService
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function isConfigured(): bool
    {
        return config('microsoft-graph.enabled')
            && ! empty(config('microsoft-graph.client_id'))
            && ! empty(config('microsoft-graph.client_secret'))
            && ! empty(config('microsoft-graph.mailbox'));
    }

    /**
     * Fetch inbox emails and store in mail_manager_emails.
     */
    public function fetchAndStoreEmails(string $folder = 'inbox', int $limit = 25): array
    {
        $results = ['fetched' => 0, 'stored' => 0, 'errors' => []];

        $token = $this->getAccessToken();
        if (! $token) {
            $results['errors'][] = 'Failed to get Microsoft Graph access token. Check MSGRAPH_* config.';
            return $results;
        }

        $messages = $this->fetchMessages($token, $folder, $limit);
        if (empty($messages)) {
            return $results;
        }

        foreach ($messages as $msg) {
            $results['fetched']++;

            try {
                $uid = $msg['id'] ?? md5(json_encode($msg));
                $exists = DB::connection('vtiger')
                    ->table('mail_manager_emails')
                    ->where('message_uid', $uid)
                    ->where('folder', $folder)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $from = $msg['from']['emailAddress'] ?? [];
                $toList = $msg['toRecipients'] ?? [];
                $ccList = $msg['ccRecipients'] ?? [];
                $body = $msg['body'] ?? [];

                try {
                    $emailId = DB::connection('vtiger')->table('mail_manager_emails')->insertGetId([
                    'message_uid' => $uid,
                    'folder' => $folder,
                    'from_address' => $from['address'] ?? '',
                    'from_name' => $from['name'] ?? null,
                    'to_addresses' => $this->formatRecipients($toList),
                    'cc_addresses' => $this->formatRecipients($ccList),
                    'subject' => $msg['subject'] ?? null,
                    'body_text' => ($body['contentType'] ?? '') === 'text' ? ($body['content'] ?? null) : $this->stripHtml($body['content'] ?? ''),
                    'body_html' => ($body['contentType'] ?? '') === 'html' ? ($body['content'] ?? null) : null,
                    'date' => isset($msg['receivedDateTime']) ? (new \DateTime($msg['receivedDateTime']))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'has_attachments' => (bool) ($msg['hasAttachments'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $errMsg = $e->getMessage();
                    if (str_contains($errMsg, 'Duplicate entry') || str_contains($errMsg, '1062')) {
                        continue;
                    }
                    throw $e;
                }

                $results['stored']++;
                $this->processAutoTicket($emailId);
            } catch (\Throwable $e) {
                $results['errors'][] = $e->getMessage();
                Log::warning('MicrosoftGraphMailService message error: ' . $e->getMessage());
            }
        }

        return $results;
    }

    protected function getAccessToken(): ?string
    {
        $cacheKey = 'msgraph_token_' . md5(config('microsoft-graph.client_id'));

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $tenant = config('microsoft-graph.tenant_id', 'common');
        $url = sprintf(self::TOKEN_URL, $tenant);

        $response = Http::asForm()
            ->timeout(15)
            ->post($url, [
                'client_id' => config('microsoft-graph.client_id'),
                'client_secret' => config('microsoft-graph.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::error('MicrosoftGraphMailService token error: ' . $response->body());
            return null;
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        if ($token && $expiresIn > 60) {
            Cache::put($cacheKey, $token, $expiresIn - 60);
        }
        return $token;
    }

    protected function fetchMessages(string $token, string $folder, int $limit): array
    {
        $mailbox = config('microsoft-graph.mailbox');
        $mailboxEncoded = rawurlencode($mailbox);
        $folderId = strtolower($folder) === 'inbox' ? 'inbox' : $folder;
        $url = self::GRAPH_BASE . "/users/{$mailboxEncoded}/mailFolders/{$folderId}/messages?\$top={$limit}&\$orderby=receivedDateTime%20desc&\$select=id,subject,from,toRecipients,ccRecipients,receivedDateTime,body,hasAttachments";

        $response = Http::withToken($token)
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            Log::error('MicrosoftGraphMailService fetch error: ' . $response->status() . ' ' . $response->body());
            return [];
        }

        $data = $response->json();
        return $data['value'] ?? [];
    }

    protected function formatRecipients(array $recipients): ?string
    {
        if (empty($recipients)) {
            return null;
        }
        $addrs = [];
        foreach ($recipients as $r) {
            $addr = $r['emailAddress']['address'] ?? null;
            if ($addr) {
                $addrs[] = $addr;
            }
        }
        return empty($addrs) ? null : implode(', ', $addrs);
    }

    protected function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function processAutoTicket(int $emailId): void
    {
        try {
            app(AutoTicketFromEmailService::class)->processNewInboundEmail($emailId);
        } catch (\Throwable $e) {
            Log::warning('MicrosoftGraphMailService auto-ticket: ' . $e->getMessage());
        }
    }

    /**
     * Send email via Microsoft Graph (same mailbox used for fetch).
     * Requires Mail.Send application permission in Azure AD.
     */
    public function sendMail(string $toAddress, ?string $toName, string $subject, string $body, bool $bodyIsHtml = false): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return false;
        }

        $mailbox = config('microsoft-graph.mailbox');
        $mailboxEncoded = rawurlencode($mailbox);
        $url = self::GRAPH_BASE . "/users/{$mailboxEncoded}/sendMail";

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => $bodyIsHtml ? 'HTML' : 'Text',
                    'content' => $body,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $toAddress,
                            'name' => $toName ?: null,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->timeout(15)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('MicrosoftGraphMailService sendMail failed: ' . $response->status() . ' ' . $response->body());
            return false;
        }

        return true;
    }
}
