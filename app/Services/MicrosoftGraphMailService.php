<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    public function fetchAndStoreEmails(string $folder = 'inbox', int $limit = 25, ?string $mailbox = null): array
    {
        $results = ['fetched' => 0, 'stored' => 0, 'errors' => []];

        $token = $this->getAccessToken();
        if (! $token) {
            $results['errors'][] = 'Failed to get Microsoft Graph access token. Check MSGRAPH_* config.';
            return $results;
        }

        $mailService = app(MailService::class);
        $storageFolder = $mailService->mailboxStorageFolder($folder, $mailbox);
        $fetchResult = ['messages' => [], 'status' => null, 'error' => null];
        $messages = [];

        if ($mailService->isPensionMailbox($mailbox)) {
            $canonical = $mailService->pensionInboxCanonicalAddress();
            $delivery = $mailService->pensionGraphFetchMailbox();
            $graphUpn = ($delivery !== '' && $delivery !== $canonical) ? $delivery : $canonical;

            $fetchResult = $this->requestInboxMessages($token, $folder, $limit, $graphUpn);
            $messages = $fetchResult['messages'];

            if ($messages === [] && $graphUpn === $canonical && $delivery !== '' && $delivery !== $canonical) {
                $fetchResult = $this->requestInboxMessages($token, $folder, $limit, $delivery);
                $messages = $fetchResult['messages'];
            }

            $groupId = trim((string) config('pension.graph_group_id', ''));
            if ($messages === [] && $groupId !== '') {
                $groupResult = $this->requestGroupInboxMessages($token, $folder, $limit, $groupId);
                if ($groupResult['messages'] !== []) {
                    $fetchResult = $groupResult;
                    $messages = $groupResult['messages'];
                }
            }
        } else {
            $graphMailbox = $mailService->resolveGraphMailbox($mailbox);
            $fetchResult = $this->requestInboxMessages($token, $folder, $limit, $graphMailbox);
            $messages = $fetchResult['messages'];
        }

        if ($messages === [] && ($fetchResult['error'] ?? null)) {
            $results['errors'][] = $mailService->isPensionMailbox($mailbox)
                ? $this->formatPensionFetchError($fetchResult['error'])
                : $fetchResult['error'];
        }

        foreach ($messages as $msg) {
            $results['fetched']++;

            try {
                $uid = $msg['id'] ?? md5(json_encode($msg));
                $exists = DB::connection('vtiger')
                    ->table('mail_manager_emails')
                    ->where('message_uid', $uid)
                    ->where('folder', $storageFolder)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $from = $msg['from']['emailAddress'] ?? [];
                $fromAddress = strtolower(trim((string) ($from['address'] ?? '')));

                $toList = $msg['toRecipients'] ?? [];
                $ccList = $msg['ccRecipients'] ?? [];
                $toAddresses = $this->formatRecipients($toList);
                $ccAddresses = $this->formatRecipients($ccList);

                if ($mailService->isPensionMailbox($mailbox) && ! $mailService->isPensionInboxStorableMessage($fromAddress, $toAddresses ?? '', $ccAddresses ?? '')) {
                    continue;
                }

                $body = $msg['body'] ?? [];
                $receivedAt = $msg['receivedDateTime'] ?? $msg['sentDateTime'] ?? null;

                try {
                    $emailId = DB::connection('vtiger')->table('mail_manager_emails')->insertGetId([
                    'message_uid' => $uid,
                    'folder' => $storageFolder,
                    'from_address' => $from['address'] ?? '',
                    'from_name' => $from['name'] ?? null,
                    'to_addresses' => $toAddresses,
                    'cc_addresses' => $ccAddresses,
                    'subject' => $msg['subject'] ?? null,
                    'body_text' => ($body['contentType'] ?? '') === 'text' ? ($body['content'] ?? null) : $this->stripHtml($body['content'] ?? ''),
                    'body_html' => ($body['contentType'] ?? '') === 'html' ? ($body['content'] ?? null) : null,
                    'date' => $receivedAt ? (new \DateTime($receivedAt))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
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
                $this->processAutoComplaint($emailId);
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

        try {
            $response = Http::asForm()
                ->retry($this->httpRetries(), $this->httpRetrySleepMs())
                ->withOptions(['connect_timeout' => $this->httpConnectTimeout()])
                ->timeout($this->httpTimeout())
                ->post($url, [
                    'client_id' => config('microsoft-graph.client_id'),
                    'client_secret' => config('microsoft-graph.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]);
        } catch (Throwable $e) {
            Log::warning('MicrosoftGraphMailService token request failed: ' . $e->getMessage());
            return null;
        }

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

    /**
     * @return array{messages: array<int, array<string, mixed>>, status: int|null, error: string|null}
     */
    protected function requestInboxMessages(string $token, string $folder, int $limit, ?string $mailbox = null): array
    {
        $mailbox = $mailbox ?: config('microsoft-graph.mailbox');
        $mailboxEncoded = rawurlencode($mailbox);
        $folderId = strtolower($folder) === 'inbox' ? 'inbox' : $folder;
        $pageSize = min(100, max(1, $limit));
        $select = 'id,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,body,hasAttachments';
        $query = "\$top={$pageSize}&\$orderby=receivedDateTime%20desc&\$select={$select}";

        $sinceDays = (int) config('pension.fetch_since_days', 0);
        $pensionFetch = strtolower(trim((string) config('pension.graph_fetch_mailbox', config('pension.msgraph_mailbox', ''))));
        if ($sinceDays > 0 && $pensionFetch !== '' && strtolower(trim((string) $mailbox)) === $pensionFetch) {
            $since = now()->subDays($sinceDays)->startOfDay()->utc()->format('Y-m-d\TH:i:s\Z');
            $query .= '&$filter=' . rawurlencode("receivedDateTime ge {$since}");
        }

        $url = self::GRAPH_BASE . "/users/{$mailboxEncoded}/mailFolders/{$folderId}/messages?{$query}";

        $messages = [];
        $status = null;
        $error = null;

        while ($url !== null && count($messages) < $limit) {
            $page = $this->requestGraphJson($token, $url);
            $status = $page['status'] ?? $status;

            if ($page['error'] !== null) {
                $error = $page['error'];
                break;
            }

            $batch = $page['data']['value'] ?? [];
            if ($batch === []) {
                break;
            }

            $messages = array_merge($messages, $batch);
            $url = $page['data']['@odata.nextLink'] ?? null;
        }

        if ($messages === [] && $error !== null && ($status ?? 0) === 404) {
            $error = "Mailbox {$mailbox} is not available in Microsoft Graph ({$error}).";
        }

        return [
            'messages' => array_slice($messages, 0, $limit),
            'status' => $status,
            'error' => $error,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, status: int|null, error: string|null}
     */
    protected function requestGraphJson(string $token, string $url): array
    {
        try {
            $response = Http::withToken($token)
                ->retry($this->httpRetries(), $this->httpRetrySleepMs())
                ->withOptions(['connect_timeout' => $this->httpConnectTimeout()])
                ->timeout(max(60, $this->httpTimeout()))
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('MicrosoftGraphMailService fetch exception: ' . $e->getMessage());

            return ['data' => [], 'status' => null, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->body();
            Log::error('MicrosoftGraphMailService fetch error: ' . $response->status() . ' ' . $response->body());

            return ['data' => [], 'status' => $response->status(), 'error' => $message];
        }

        return ['data' => $response->json(), 'status' => $response->status(), 'error' => null];
    }

    /**
     * @return array{messages: array<int, array<string, mixed>>, status: int|null, error: string|null}
     */
    protected function requestGroupInboxMessagesByMail(string $token, string $folder, int $limit, string $groupMail): array
    {
        $lookup = $this->lookupGroupIdByMail($token, $groupMail);
        if (($lookup['id'] ?? null) === null) {
            return [
                'messages' => [],
                'status' => $lookup['status'] ?? null,
                'error' => $lookup['error'] ?? ('Could not resolve Microsoft 365 group for ' . $groupMail . '.'),
            ];
        }

        return $this->requestGroupInboxMessages($token, $folder, $limit, $lookup['id']);
    }

    /**
     * @return array{id: string|null, status: int|null, error: string|null}
     */
    protected function lookupGroupIdByMail(string $token, string $groupMail): array
    {
        $filter = rawurlencode("mail eq '" . str_replace("'", "''", $groupMail) . "'");
        $url = self::GRAPH_BASE . "/groups?\$filter={$filter}&\$select=id,mail,displayName";

        try {
            $response = Http::withToken($token)
                ->retry($this->httpRetries(), $this->httpRetrySleepMs())
                ->withOptions(['connect_timeout' => $this->httpConnectTimeout()])
                ->timeout(max(30, $this->httpTimeout()))
                ->get($url);
        } catch (Throwable $e) {
            return ['id' => null, 'status' => null, 'error' => $e->getMessage()];
        }

        if ($response->status() === 403) {
            return [
                'id' => null,
                'status' => 403,
                'error' => 'Azure app lacks Group.Read.All to find the pensions group automatically.',
            ];
        }

        if (! $response->successful()) {
            $body = $response->json();

            return [
                'id' => null,
                'status' => $response->status(),
                'error' => $body['error']['message'] ?? $response->body(),
            ];
        }

        $groups = $response->json()['value'] ?? [];
        $id = $groups[0]['id'] ?? null;
        if ($id === null) {
            return [
                'id' => null,
                'status' => 404,
                'error' => 'No Microsoft 365 group found with mail address ' . $groupMail . '.',
            ];
        }

        return ['id' => $id, 'status' => $response->status(), 'error' => null];
    }

    /**
     * @return array{messages: array<int, array<string, mixed>>, status: int|null, error: string|null}
     */
    protected function requestGroupInboxMessages(string $token, string $folder, int $limit, string $groupId): array
    {
        $folderId = strtolower($folder) === 'inbox' ? 'inbox' : $folder;
        $select = 'id,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,body,hasAttachments';
        $url = self::GRAPH_BASE . "/groups/{$groupId}/mailFolders/{$folderId}/messages?\$top={$limit}&\$orderby=receivedDateTime%20desc&\$select={$select}";

        try {
            $response = Http::withToken($token)
                ->retry($this->httpRetries(), $this->httpRetrySleepMs())
                ->withOptions(['connect_timeout' => $this->httpConnectTimeout()])
                ->timeout(max(30, $this->httpTimeout()))
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('MicrosoftGraphMailService group fetch exception: ' . $e->getMessage());

            return ['messages' => [], 'status' => null, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->body();
            if ($response->status() === 403) {
                $message = 'Azure app cannot read the pensions group mailbox (Mail.Read + group access required).';
            }
            Log::error('MicrosoftGraphMailService group fetch error: ' . $response->status() . ' ' . $response->body());

            return ['messages' => [], 'status' => $response->status(), 'error' => $message];
        }

        return ['messages' => $response->json()['value'] ?? [], 'status' => $response->status(), 'error' => null];
    }

    protected function formatPensionFetchError(string $detail): string
    {
        $mailbox = config('pension.mailbox', 'pensions@geminialife.co.ke');

        return trim($detail) . ' pensions@ is a group mailbox (no password). Ask IT for either: '
            . '(1) MSGRAPH_PENSIONS_GROUP_ID — the Azure Object ID of the Microsoft 365 group for '
            . $mailbox . ', or (2) add Group.Read.All (application) to the CRM Azure app and grant admin consent.';
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
            $mail = app(MailService::class);
            if ($mail->isPensionMailboxEmail($emailId)) {
                app(PensionAutoTicketFromEmailService::class)->processNewInboundEmail($emailId);
            } else {
                app(AutoTicketFromEmailService::class)->processNewInboundEmail($emailId);
            }
        } catch (\Throwable $e) {
            Log::warning('MicrosoftGraphMailService auto-ticket: ' . $e->getMessage());
        }
    }

    protected function processAutoComplaint(int $emailId): void
    {
        $mail = app(MailService::class);
        if ($mail->isPensionMailboxEmail($emailId)) {
            return;
        }

        try {
            app(AutoComplaintFromEmailService::class)->processNewInboundEmail($emailId);
        } catch (\Throwable $e) {
            Log::warning('MicrosoftGraphMailService auto-complaint: ' . $e->getMessage());
        }
    }

    /**
     * Send email via Microsoft Graph (same mailbox used for fetch).
     * Requires Mail.Send application permission in Azure AD.
     */
    /**
     * @param  array<int, array{name: string, contentType: string, content: string}>  $attachments  Raw file bytes in `content`
     */
    public function sendMail(string $toAddress, ?string $toName, string $subject, string $body, bool $bodyIsHtml = false, array $attachments = []): bool
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

        $message = [
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
        ];

        if ($attachments !== []) {
            $message['attachments'] = [];
            foreach ($attachments as $a) {
                if (empty($a['name']) || ! isset($a['content'])) {
                    continue;
                }
                $message['attachments'][] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $a['name'],
                    'contentType' => $a['contentType'] ?? 'application/octet-stream',
                    'contentBytes' => base64_encode($a['content']),
                ];
            }
        }

        $payload = ['message' => $message];

        try {
            $response = Http::withToken($token)
                ->retry($this->httpRetries(), $this->httpRetrySleepMs())
                ->withOptions(['connect_timeout' => $this->httpConnectTimeout()])
                ->timeout($this->httpTimeout())
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('MicrosoftGraphMailService sendMail exception: ' . $e->getMessage(), [
                'mailbox' => $mailbox,
                'to' => $toAddress,
            ]);
            return false;
        }

        if (! $response->successful()) {
            Log::warning('MicrosoftGraphMailService sendMail failed: ' . $response->status() . ' ' . $response->body());
            return false;
        }

        return true;
    }

    protected function httpConnectTimeout(): float
    {
        return (float) config('microsoft-graph.http.connect_timeout', 10);
    }

    protected function httpTimeout(): float
    {
        return (float) config('microsoft-graph.http.timeout', 20);
    }

    protected function httpRetries(): int
    {
        return max(0, (int) config('microsoft-graph.http.retries', 2));
    }

    protected function httpRetrySleepMs(): int
    {
        return max(0, (int) config('microsoft-graph.http.retry_sleep_ms', 300));
    }
}
