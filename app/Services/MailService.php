<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;

class MailService
{
    /** @var string */
    protected $account;

    public function __construct(string $account = 'geminia')
    {
        $this->account = $account;
    }

    /**
     * Check if Microsoft Graph is configured (best for Office 365).
     */
    public function useMicrosoftGraph(): bool
    {
        return app(MicrosoftGraphMailService::class)->isConfigured();
    }

    /**
     * Check if HTTP email service is configured.
     */
    public function useHttpEmailService(): bool
    {
        $url = config('email-service.url', '');
        return ! empty($url) && ! empty(config('email-service.username')) && ! empty(config('email-service.password'));
    }

    /**
     * Fetch emails and store in mail_manager_emails.
     * Priority: 1) Microsoft Graph (Office 365), 2) HTTP API, 3) IMAP.
     */
    public function fetchAndStoreEmails(string $folder = 'INBOX', int $limit = 100, ?string $mailbox = null): array
    {
        if ($this->isPensionMailbox($mailbox)) {
            return $this->fetchPensionMailboxEmails($folder, $limit, $mailbox);
        }

        if ($this->useMicrosoftGraph()) {
            return app(MicrosoftGraphMailService::class)->fetchAndStoreEmails(
                strtolower($folder) === 'inbox' ? 'inbox' : $folder,
                $limit,
                null
            );
        }

        if ($this->useHttpEmailService()) {
            return $this->fetchAndStoreEmailsViaHttp($folder, $limit, null);
        }

        return $this->fetchAndStoreEmailsViaImap($folder, $limit, null);
    }

    /**
     * Fetch only from pensions@ — never from life@ or other primary mailboxes.
     */
    protected function fetchPensionMailboxEmails(string $folder, int $limit, ?string $mailbox): array
    {
        $graph = app(MicrosoftGraphMailService::class);
        if (! $graph->isConfigured()) {
            return [
                'fetched' => 0,
                'stored' => 0,
                'errors' => ['Microsoft Graph is not configured. Set MSGRAPH_* in .env (same app as life@ mail).'],
            ];
        }

        return $graph->fetchAndStoreEmails(
            strtolower($folder) === 'inbox' ? 'inbox' : $folder,
            $limit,
            $mailbox
        );
    }

    public function isPensionMailbox(?string $mailbox): bool
    {
        $mailbox = strtolower(trim((string) ($mailbox ?? '')));
        $pensions = strtolower(trim((string) config('pension.mailbox', '')));

        return $pensions !== '' && $mailbox === $pensions;
    }

    public function resolveFetchLimit(?string $mailbox = null, ?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        if ($this->isPensionMailbox($mailbox)) {
            return max(100, (int) config('pension.fetch_limit', 1000));
        }

        return max(50, (int) config('email-service.fetch_limit', 25), (int) config('microsoft-graph.fetch_limit', 25));
    }

    /**
     * Resolve the Graph/HTTP mailbox UPN for a logical mailbox address.
     */
    public function resolveGraphMailbox(?string $mailbox = null): string
    {
        $mailbox = strtolower(trim((string) ($mailbox ?: config('email-service.sender', config('mail.from.address', '')))));
        $pensions = strtolower(trim((string) config('pension.mailbox', '')));

        if ($pensions !== '' && $mailbox === $pensions) {
            return $pensions;
        }

        return trim((string) config('microsoft-graph.mailbox', config('email-service.sender', '')));
    }

    /**
     * Folder key used when storing emails fetched for a specific mailbox.
     */
    public function mailboxStorageFolder(string $folder, ?string $mailbox = null): string
    {
        $folderKey = strtolower($folder === 'INBOX' ? 'inbox' : $folder);
        $mailbox = strtolower(trim((string) ($mailbox ?? '')));
        $primary = strtolower(trim((string) config('email-service.sender', config('mail.from.address', ''))));

        if ($mailbox === '' || ($primary !== '' && $mailbox === $primary)) {
            return $folderKey;
        }

        return $folderKey . '|' . $mailbox;
    }

    /**
     * True when fetching a secondary mailbox (not the primary life@ inbox).
     */
    public function isAlternateMailbox(?string $mailbox): bool
    {
        return $this->isPensionMailbox($mailbox);
    }

    /**
     * Fetch emails via HTTP API (email service at 10.10.1.111:8080).
     */
    protected function fetchAndStoreEmailsViaHttp(string $folder, int $limit, ?string $mailbox = null): array
    {
        $results = ['fetched' => 0, 'stored' => 0, 'errors' => []];

        $baseUrl = config('email-service.url');
        $username = config('email-service.username');
        $password = config('email-service.password');
        $limit = config('email-service.fetch_limit', $limit);

        $customEndpoint = config('email-service.fetch_endpoint', '');
        $getEndpoints = $customEndpoint
            ? [$baseUrl . '/' . ltrim($customEndpoint, '/') . '?limit=' . $limit]
            : [
                $baseUrl . '/emails?limit=' . $limit,
                $baseUrl . '/api/emails?limit=' . $limit,
                $baseUrl . '/inbox?limit=' . $limit,
            ];

        $postEndpoints = $customEndpoint ? [] : [
            $baseUrl . '/fetch',
            $baseUrl . '/api/fetch',
        ];

        $emails = [];
        $lastError = null;

        foreach ($getEndpoints as $url) {
            try {
                $response = Http::withBasicAuth($username, $password)
                    ->timeout(8)
                    ->get($url);

                if ($response->successful()) {
                    $body = $response->json();
                    $emails = $this->extractEmailsFromResponse($body);
                    if (! empty($emails)) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('MailService::fetchViaHttp GET ' . $url . ': ' . $e->getMessage());
            }
        }

        if (empty($emails)) {
            foreach ($postEndpoints as $url) {
                try {
                        $response = Http::withBasicAuth($username, $password)
                            ->timeout(8)
                            ->post($url, [
                            'username' => $username,
                            'password' => $password,
                            'folder' => $folder,
                            'limit' => $limit,
                        ]);

                    if ($response->successful()) {
                        $body = $response->json();
                        $emails = $this->extractEmailsFromResponse($body);
                        if (! empty($emails)) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    Log::warning('MailService::fetchViaHttp POST ' . $url . ': ' . $e->getMessage());
                }
            }
        }

        if (empty($emails)) {
            $isConnectionError = $lastError && ((strpos($lastError, 'Timeout') !== false) || (strpos($lastError, 'Failed to connect') !== false) || (strpos($lastError, 'Connection refused') !== false));
            if ($isConnectionError && config('email-service.fallback_to_imap', true)) {
                Log::warning('MailService: HTTP service unreachable, falling back to IMAP. ' . $lastError);
                return $this->fetchAndStoreEmailsViaImap($folder, $limit);
            }
            $results['errors'][] = 'Email service returned no emails. ' . ($lastError ?: 'Check EMAIL_SERVICE_URL, MAIL_USERNAME, MAIL_PASSWORD.');
            Log::error('MailService::fetchViaHttp: No emails from ' . $baseUrl);
            return $results;
        }

        foreach ($emails as $msg) {
            $results['fetched']++;

            try {
                $uid = $this->normalizeEmailUid($msg);
                $exists = DB::connection('vtiger')
                    ->table('mail_manager_emails')
                    ->where('message_uid', $uid)
                    ->where('folder', $folder)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $emailId = DB::connection('vtiger')->table('mail_manager_emails')->insertGetId([
                    'message_uid' => $uid,
                    'folder' => $folder,
                    'from_address' => $this->extractFromAddress($msg),
                    'from_name' => $this->extractFromName($msg),
                    'to_addresses' => $this->extractToAddresses($msg),
                    'cc_addresses' => $this->extractCcAddresses($msg),
                    'subject' => $this->extractSubject($msg),
                    'body_text' => $this->extractBodyText($msg),
                    'body_html' => $this->extractBodyHtml($msg),
                    'date' => $this->extractDate($msg),
                    'has_attachments' => $this->extractHasAttachments($msg),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $results['stored']++;
                $this->processAutoTicketFromEmail($emailId);
                $this->processAutoComplaintFromEmail($emailId);
            } catch (\Throwable $e) {
                $results['errors'][] = $e->getMessage();
                Log::warning('MailService::fetchViaHttp message error: ' . $e->getMessage());
            }
        }

        if (($results['stored'] ?? 0) > 0) {
            self::forgetListCaches();
        }

        return $results;
    }

    /**
     * Extract email array from API response (handles various formats).
     */
    protected function extractEmailsFromResponse(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }
        if (isset($body['emails']) && is_array($body['emails'])) {
            return $body['emails'];
        }
        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }
        if (isset($body['result']) && is_array($body['result'])) {
            return $body['result'];
        }
        if (isset($body['messages']) && is_array($body['messages'])) {
            return $body['messages'];
        }
        $first = reset($body);
        if (is_array($first) && (isset($first['from_address']) || isset($first['from']) || isset($first['subject']))) {
            return array_values($body);
        }

        return [];
    }

    protected function normalizeEmailUid(array $msg): string
    {
        $id = $msg['message_uid'] ?? $msg['uid'] ?? $msg['id'] ?? $msg['message_id'] ?? null;
        if ($id !== null) {
            return (string) $id;
        }
        return md5(($msg['from_address'] ?? $msg['from'] ?? '') . '|' . ($msg['date'] ?? '') . '|' . ($msg['subject'] ?? ''));
    }

    protected function extractFromAddress(array $msg): string
    {
        $from = $msg['from_address'] ?? $msg['from'] ?? $msg['from_email'] ?? null;
        if (is_string($from)) {
            return $from;
        }
        if (is_array($from) && isset($from['address'])) {
            return $from['address'];
        }
        return '';
    }

    protected function extractFromName(array $msg): ?string
    {
        $name = $msg['from_name'] ?? $msg['from_personal'] ?? $msg['sender_name'] ?? null;
        if (is_string($name)) {
            return $name;
        }
        if (is_array($msg['from'] ?? null) && isset($msg['from']['name'])) {
            return $msg['from']['name'];
        }
        return null;
    }

    protected function extractToAddresses(array $msg): ?string
    {
        $to = $msg['to_addresses'] ?? $msg['to'] ?? $msg['to_address'] ?? null;
        if (is_string($to)) {
            return $to;
        }
        if (is_array($to)) {
            return implode(', ', array_map(fn ($a) => is_string($a) ? $a : ($a['address'] ?? $a['mail'] ?? ''), $to));
        }
        return null;
    }

    protected function extractCcAddresses(array $msg): ?string
    {
        $cc = $msg['cc_addresses'] ?? $msg['cc'] ?? null;
        if (is_string($cc)) {
            return $cc;
        }
        if (is_array($cc)) {
            return implode(', ', array_map(fn ($a) => is_string($a) ? $a : ($a['address'] ?? $a['mail'] ?? ''), $cc));
        }
        return null;
    }

    protected function extractSubject(array $msg): ?string
    {
        return $msg['subject'] ?? null;
    }

    protected function extractBodyText(array $msg): ?string
    {
        return $msg['body_text'] ?? $msg['body'] ?? $msg['text'] ?? $msg['plain'] ?? null;
    }

    protected function extractBodyHtml(array $msg): ?string
    {
        return $msg['body_html'] ?? $msg['html'] ?? $msg['html_body'] ?? null;
    }

    protected function extractDate(array $msg): ?string
    {
        $d = $msg['date'] ?? $msg['received'] ?? $msg['created_at'] ?? null;
        if ($d === null) {
            return null;
        }
        if ($d instanceof \DateTimeInterface) {
            return $d->format('Y-m-d H:i:s');
        }
        if (is_numeric($d)) {
            return date('Y-m-d H:i:s', (int) $d);
        }
        try {
            return (new \DateTime($d))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function extractHasAttachments(array $msg): bool
    {
        return (bool) ($msg['has_attachments'] ?? $msg['attachments'] ?? false);
    }

    /**
     * Fetch emails via IMAP (fallback when HTTP service not configured).
     */
    protected function fetchAndStoreEmailsViaImap(string $folder, int $limit, ?string $mailbox = null, ?string $account = null): array
    {
        $results = ['fetched' => 0, 'stored' => 0, 'errors' => []];
        $storageFolder = $mailbox
            ? $this->mailboxStorageFolder($folder, $mailbox)
            : (strtolower($folder) === 'inbox' ? 'inbox' : $folder);

        try {
            $cm = new ClientManager(config('imap'));
            $client = $cm->account($account ?? $this->account);
            $client->connect();

            $folders = $client->getFolders();
            $targetFolder = $folders->where('path', $folder)->first() ?? $folders->where('name', $folder)->first() ?? $folders->first();

            if (! $targetFolder) {
                $results['errors'][] = 'No folder found';
                return $results;
            }

            $messages = $targetFolder->messages()->limit($limit)->get();

            foreach ($messages as $message) {
                $results['fetched']++;

                try {
                    $uid = (string) $message->getUid();
                    $exists = DB::connection('vtiger')
                        ->table('mail_manager_emails')
                        ->where('message_uid', $uid)
                        ->where('folder', $storageFolder)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $from = $message->getFrom()->first();
                    $to = $message->getTo()->map(fn ($a) => $a->mail)->implode(', ');
                    $cc = $message->getCc()->count() > 0
                        ? $message->getCc()->map(fn ($a) => $a->mail)->implode(', ')
                        : null;

                    $emailId = DB::connection('vtiger')->table('mail_manager_emails')->insertGetId([
                        'message_uid' => $uid,
                        'folder' => $storageFolder,
                        'from_address' => ($from ? $from->mail : null) ?? '',
                        'from_name' => $from ? $from->personal : null,
                        'to_addresses' => $to ?: null,
                        'cc_addresses' => $cc,
                        'subject' => $message->getSubject(),
                        'body_text' => $message->getTextBody(),
                        'body_html' => $message->getHTMLBody(),
                        'date' => ($d = $message->getDate()) ? $d->format('Y-m-d H:i:s') : null,
                        'has_attachments' => $message->getAttachments()->count() > 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $results['stored']++;
                    $this->processAutoTicketFromEmail($emailId);
                    $this->processAutoComplaintFromEmail($emailId);
                } catch (\Throwable $e) {
                    $results['errors'][] = $e->getMessage();
                    Log::warning('MailService::fetchAndStoreEmails message error: ' . $e->getMessage());
                }
            }

            $client->disconnect();
        } catch (ImapBadRequestException $e) {
            if (strpos($e->getMessage(), 'NOOP completed') !== false) {
                Log::info('MailService: Ignoring NOOP response quirk from IMAP server (Microsoft 365 etc)');
                return $results;
            }
            $results['errors'][] = $e->getMessage();
            Log::error('MailService::fetchAndStoreEmails: ' . $e->getMessage());
        } catch (ConnectionFailedException $e) {
            $results['errors'][] = 'IMAP connection failed: ' . $e->getMessage();
            Log::error('MailService::fetchAndStoreEmails connection: ' . $e->getMessage());
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'NOOP completed') !== false) {
                Log::info('MailService: Ignoring NOOP response quirk (Microsoft 365/Outlook)');
                return $results;
            }
            $results['errors'][] = $e->getMessage();
            Log::error('MailService::fetchAndStoreEmails: ' . $e->getMessage());
        }

        if (($results['stored'] ?? 0) > 0) {
            self::forgetListCaches();
        }

        return $results;
    }

    protected function processAutoTicketFromEmail(int $emailId): void
    {
        try {
            if ($this->isPensionMailboxEmail($emailId)) {
                app(PensionAutoTicketFromEmailService::class)->processNewInboundEmail($emailId);
            } else {
                app(AutoTicketFromEmailService::class)->processNewInboundEmail($emailId);
            }
        } catch (\Throwable $e) {
            Log::warning('MailService auto-ticket: ' . $e->getMessage());
        }
    }

    public function isPensionMailboxEmail(int $emailId): bool
    {
        $email = DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->first();
        if (! $email) {
            return false;
        }

        $pensionMailbox = strtolower(trim((string) config('pension.mailbox', '')));
        if ($pensionMailbox === '') {
            return false;
        }

        $folders = $this->mailboxStorageFolders('inbox', $pensionMailbox);
        $folder = strtolower(trim((string) ($email->folder ?? '')));

        return in_array($folder, array_map('strtolower', $folders), true);
    }

    protected function processAutoComplaintFromEmail(int $emailId): void
    {
        if ($this->isPensionMailboxEmail($emailId)) {
            return;
        }

        try {
            app(AutoComplaintFromEmailService::class)->processNewInboundEmail($emailId);
        } catch (\Throwable $e) {
            Log::warning('MailService auto-complaint: ' . $e->getMessage());
        }
    }

    /**
     * Get paginated emails for list view. Selects only list columns (excludes body_text/body_html for speed).
     */
    public function getEmails(int $perPage = 20, int $offset = 0, ?string $search = null, ?string $mailbox = null): array
    {
        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->select('id', 'from_address', 'from_name', 'subject', 'date', 'has_attachments', 'ticket_id')
            ->orderByDesc('date');

        $this->applyMailboxFilter($query, $mailbox);
        $this->applyPensionInboxExclusions($query, $mailbox);

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('from_address', 'like', $term)
                    ->orWhere('from_name', 'like', $term)
                    ->orWhere('body_text', 'like', $term);
            });
        }

        return $query->offset($offset)->limit($perPage)->get()->all();
    }

    public function getEmailsCount(?string $search = null, ?string $mailbox = null): int
    {
        $mailboxKey = strtolower(trim((string) ($mailbox ?? '')));
        if ((! $search || trim($search) === '') && $mailboxKey === '') {
            return (int) Cache::remember('geminia_emails_count', 60, fn () => $this->fetchEmailsCount(null, null));
        }

        if ((! $search || trim($search) === '') && $this->isPensionMailbox($mailbox)) {
            return (int) Cache::remember('geminia_pension_emails_count', 60, fn () => $this->fetchEmailsCount(null, $mailbox));
        }

        return $this->fetchEmailsCount($search, $mailbox);
    }

    public static function forgetListCaches(): void
    {
        Cache::forget('geminia_emails_count');
        Cache::forget('geminia_pension_emails_count');
        Cache::forget('mail:pension:latest_at');
    }

    protected function fetchEmailsCount(?string $search, ?string $mailbox = null): int
    {
        $query = DB::connection('vtiger')->table('mail_manager_emails');
        $this->applyMailboxFilter($query, $mailbox);
        $this->applyPensionInboxExclusions($query, $mailbox);

        if ($search && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('from_address', 'like', $term)
                    ->orWhere('from_name', 'like', $term)
                    ->orWhere('body_text', 'like', $term);
            });
        }

        return $query->count();
    }

    protected function applyMailboxFilter($query, ?string $mailbox): void
    {
        $mailbox = strtolower(trim((string) ($mailbox ?? '')));
        if ($mailbox === '') {
            return;
        }

        $folders = $this->mailboxStorageFolders('inbox', $mailbox);
        if (count($folders) === 1) {
            $query->where('folder', $folders[0]);
        } else {
            $query->whereIn('folder', $folders);
        }
    }

    /**
     * Storage folder keys for a mailbox (includes legacy pension folder aliases).
     *
     * @return list<string>
     */
    public function mailboxStorageFolders(string $folder, ?string $mailbox = null): array
    {
        return [$this->mailboxStorageFolder($folder, $mailbox)];
    }

    /**
     * @return list<string>
     */
    public function pensionInboxExcludedAddresses(): array
    {
        return config('pension.inbox_exclude_addresses', []);
    }

    /**
     * @return list<string>
     */
    public function pensionInboxOutboundExcludedAddresses(): array
    {
        return config('pension.inbox_outbound_exclude_addresses', []);
    }

    public function pensionGraphFetchMailbox(): string
    {
        return strtolower(trim((string) config('pension.graph_fetch_mailbox', config('pension.msgraph_mailbox', ''))));
    }

    /**
     * Additional team mailboxes to scan for pension CC/sent mail not delivered to pensions.support@.
     *
     * @return list<string>
     */
    public function pensionGraphScanMailboxes(): array
    {
        $configured = config('pension.graph_scan_mailboxes', []);
        if (! is_array($configured)) {
            $configured = [];
        }

        $assignee = strtolower(trim((string) config('pension.auto_ticket.assign_to_email', '')));
        $primary = array_filter([
            strtolower(trim($this->pensionGraphFetchMailbox())),
            strtolower(trim($this->pensionInboxCanonicalAddress())),
        ]);

        $addresses = array_values(array_unique(array_filter(array_merge(
            $configured,
            $assignee !== '' ? [$assignee] : []
        ))));

        return array_values(array_filter($addresses, fn ($address) => ! in_array($address, $primary, true)));
    }

    /**
     * Mailboxes that count as mail to pensions@ (includes internal delivery alias).
     *
     * @return list<string>
     */
    public function pensionInboxRecipientAddresses(): array
    {
        $addresses = [
            $this->pensionInboxCanonicalAddress(),
            $this->pensionGraphFetchMailbox(),
        ];

        return array_values(array_filter(array_unique(array_filter($addresses))));
    }

    public function messageHasPensionRecipient(?string $to, ?string $cc): bool
    {
        $combined = strtolower(trim((string) $to . ' ' . (string) $cc));
        if ($combined === '') {
            return false;
        }

        foreach ($this->pensionInboxRecipientAddresses() as $address) {
            if ($address !== '' && str_contains($combined, $address)) {
                return true;
            }
        }

        return false;
    }

    public function isPensionInboxOutboundExcludedSender(?string $from): bool
    {
        $from = strtolower(trim((string) $from));
        if ($from === '') {
            return true;
        }

        foreach ($this->pensionInboxOutboundExcludedAddresses() as $excluded) {
            if ($excluded !== '' && str_contains($from, $excluded)) {
                return true;
            }
        }

        foreach ($this->pensionInboxExcludedAddresses() as $excluded) {
            if ($excluded !== '' && str_contains($from, $excluded)) {
                return true;
            }
        }

        if (config('pension.inbox_exclude_life_mailbox', true)) {
            $life = $this->pensionInboxLifeMailbox();
            if ($life !== '' && str_contains($from, $life)) {
                return true;
            }
        }

        return false;
    }

    public function pensionInboxCanonicalAddress(): string
    {
        return strtolower(trim((string) config('pension.mailbox', 'pensions@geminialife.co.ke')));
    }

    public function pensionInboxLifeMailbox(): string
    {
        return strtolower(trim((string) config('pension.life_mailbox', 'life@geminialife.co.ke')));
    }

    public function isPensionInboxStorableMessage(?string $from, ?string $to, ?string $cc): bool
    {
        if ($this->isPensionInboxOutboundExcludedSender($from)) {
            return false;
        }

        return strtolower(trim((string) $from)) !== '';
    }

    public function isPensionInboxEligibleMessage(?string $from, ?string $to, ?string $cc): bool
    {
        if ($this->isPensionInboxOutboundExcludedSender($from)) {
            return false;
        }

        if (config('pension.inbox_require_canonical_recipient', true) && ! $this->messageHasPensionRecipient($to, $cc)) {
            return false;
        }

        if (config('pension.inbox_exclude_life_mailbox', true)) {
            $life = $this->pensionInboxLifeMailbox();
            $to = strtolower(trim((string) $to));
            $cc = strtolower(trim((string) $cc));
            if ($life !== '' && str_contains($to, $life) && ! $this->messageHasPensionRecipient($to, $cc)) {
                return false;
            }
        }

        return true;
    }

    public function isPensionInboxEligibleEmail(object $email): bool
    {
        return $this->isPensionInboxEligibleMessage(
            $email->from_address ?? '',
            $email->to_addresses ?? '',
            $email->cc_addresses ?? ''
        );
    }

    public function sanitizePensionMailboxText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $canonical = $this->pensionInboxCanonicalAddress();
        foreach ($this->pensionInboxRecipientAddresses() as $address) {
            if ($address !== '' && $address !== $canonical) {
                $text = str_ireplace($address, $canonical, $text);
            }
        }

        $fetchMailbox = $this->pensionGraphFetchMailbox();
        if ($fetchMailbox !== '' && $fetchMailbox !== $canonical) {
            $text = str_ireplace($fetchMailbox, $canonical, $text);
        }

        foreach ($this->pensionInboxOutboundExcludedAddresses() as $excluded) {
            if ($excluded !== '' && $excluded !== $canonical) {
                $text = str_ireplace($excluded, $canonical, $text);
            }
        }

        if (config('pension.inbox_exclude_life_mailbox', true)) {
            $life = $this->pensionInboxLifeMailbox();
            if ($life !== '' && $life !== $canonical) {
                $text = preg_replace('/,?\s*' . preg_quote($life, '/') . '/i', '', $text) ?? $text;
                $text = preg_replace('/' . preg_quote($life, '/') . '\s*,?\s*/i', '', $text) ?? $text;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    public function sanitizePensionInboxEmail(object $email): object
    {
        $email->from_address = $this->sanitizePensionMailboxText($email->from_address ?? '');
        $email->to_addresses = $this->sanitizePensionMailboxText($email->to_addresses ?? '');
        $email->cc_addresses = $this->sanitizePensionMailboxText($email->cc_addresses ?? '');
        $email->body_text = $this->sanitizePensionMailboxText($email->body_text ?? '');
        if (! empty($email->body_html)) {
            $email->body_html = $this->sanitizePensionMailboxText($email->body_html);
        }

        return $email;
    }

    public function getPensionInboxEmail(int $id): ?object
    {
        $email = $this->getEmail($id);
        if (! $email || ! $this->isPensionInboxEligibleEmail($email)) {
            return null;
        }

        return $this->sanitizePensionInboxEmail($email);
    }

    /** @deprecated Use pensionInboxExcludedAddresses() */
    public function pensionInboxExcludedSenders(): array
    {
        return $this->pensionInboxExcludedAddresses();
    }

    public function isPensionInboxExcludedSender(?string $address): bool
    {
        $address = strtolower(trim((string) $address));
        if ($address === '') {
            return false;
        }

        foreach ($this->pensionInboxExcludedAddresses() as $excluded) {
            if ($excluded !== '' && str_contains($address, $excluded)) {
                return true;
            }
        }

        return false;
    }

    protected function applyPensionInboxExclusions($query, ?string $mailbox): void
    {
        if (! $this->isPensionMailbox($mailbox)) {
            return;
        }

        $canonical = $this->pensionInboxCanonicalAddress();

        if (config('pension.inbox_require_canonical_recipient', true)) {
            $query->where(function ($q) use ($canonical) {
                foreach ($this->pensionInboxRecipientAddresses() as $address) {
                    if ($address === '') {
                        continue;
                    }
                    $like = '%' . $address . '%';
                    $q->orWhereRaw('LOWER(COALESCE(to_addresses, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(cc_addresses, "")) LIKE ?', [$like]);
                }
            });
        }

        foreach ($this->pensionInboxOutboundExcludedAddresses() as $excluded) {
            if ($excluded === '') {
                continue;
            }
            $query->whereRaw('LOWER(COALESCE(from_address, "")) NOT LIKE ?', ['%' . $excluded . '%']);
        }

        foreach ($this->pensionInboxExcludedAddresses() as $excluded) {
            if ($excluded === '') {
                continue;
            }
            $query->whereRaw('LOWER(COALESCE(from_address, "")) NOT LIKE ?', ['%' . $excluded . '%']);
        }

        if (config('pension.inbox_exclude_life_mailbox', true)) {
            $life = $this->pensionInboxLifeMailbox();
            if ($life !== '') {
                $like = '%' . $life . '%';
                $query->whereRaw('LOWER(COALESCE(from_address, "")) NOT LIKE ?', [$like]);
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(COALESCE(to_addresses, "")) NOT LIKE ?', [$like])
                        ->orWhere(function ($inner) {
                            foreach ($this->pensionInboxRecipientAddresses() as $address) {
                                if ($address === '') {
                                    continue;
                                }
                                $recipientLike = '%' . $address . '%';
                                $inner->orWhereRaw('LOWER(COALESCE(to_addresses, "")) LIKE ?', [$recipientLike])
                                    ->orWhereRaw('LOWER(COALESCE(cc_addresses, "")) LIKE ?', [$recipientLike]);
                            }
                        });
                });
            }
        }
    }

    public function getPensionInboxLatestReceivedAt(): ?\Carbon\Carbon
    {
        $latest = Cache::remember('mail:pension:latest_at', 120, function () {
            $folder = $this->mailboxStorageFolder('inbox', config('pension.mailbox'));

            return DB::connection('vtiger')
                ->table('mail_manager_emails')
                ->where('folder', $folder)
                ->max('date');
        });

        return $latest ? \Carbon\Carbon::parse($latest) : null;
    }

    public function getEmail(int $id): ?object
    {
        return DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get emails for a contact: outbound (life@ → client) and inbound (client → life@geminialife.co.ke).
     */
    public function getEmailsForContact(object $contact, int $perPage = 20, int $offset = 0): array
    {
        $contactEmails = $this->getContactEmailAddresses($contact);
        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));

        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->select('id', 'from_address', 'from_name', 'to_addresses', 'subject', 'date', 'has_attachments');

        $query->where(function ($q) use ($contactEmails, $sender) {
            $q->where(function ($sub) use ($contactEmails, $sender) {
                foreach ($contactEmails as $addr) {
                    if (trim($addr) !== '') {
                        $sub->orWhere(function ($inner) use ($addr, $sender) {
                            $inner->where('from_address', 'like', '%' . $sender . '%')
                                ->where(function ($to) use ($addr) {
                                    $to->where('to_addresses', 'like', '%' . $addr . '%')
                                        ->orWhere('cc_addresses', 'like', '%' . $addr . '%');
                                });
                        });
                    }
                }
            });
            if (! empty($contactEmails)) {
                $q->orWhere(function ($sub) use ($contactEmails, $sender) {
                    foreach ($contactEmails as $addr) {
                        if (trim($addr) !== '') {
                            $sub->orWhere(function ($inner) use ($addr, $sender) {
                                $inner->where('from_address', 'like', '%' . $addr . '%')
                                    ->where(function ($to) use ($sender) {
                                        $to->where('to_addresses', 'like', '%' . $sender . '%')
                                            ->orWhere('cc_addresses', 'like', '%' . $sender . '%');
                                    });
                            });
                        }
                    }
                });
            }
        });

        return $query->orderByDesc('date')->offset($offset)->limit($perPage)->get()->all();
    }

    /**
     * Count emails for a contact (outbound + inbound).
     */
    public function getEmailsForContactCount(object $contact): int
    {
        $contactEmails = $this->getContactEmailAddresses($contact);
        if (empty($contactEmails)) {
            return 0;
        }

        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));

        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->where(function ($q) use ($contactEmails, $sender) {
                $q->where(function ($sub) use ($contactEmails, $sender) {
                    foreach ($contactEmails as $addr) {
                        if (trim($addr) !== '') {
                            $sub->orWhere(function ($inner) use ($addr, $sender) {
                                $inner->where('from_address', 'like', '%' . $sender . '%')
                                    ->where(function ($to) use ($addr) {
                                        $to->where('to_addresses', 'like', '%' . $addr . '%')
                                            ->orWhere('cc_addresses', 'like', '%' . $addr . '%');
                                    });
                            });
                        }
                    }
                });
                $q->orWhere(function ($sub) use ($contactEmails, $sender) {
                    foreach ($contactEmails as $addr) {
                        if (trim($addr) !== '') {
                            $sub->orWhere(function ($inner) use ($addr, $sender) {
                                $inner->where('from_address', 'like', '%' . $addr . '%')
                                    ->where(function ($to) use ($sender) {
                                        $to->where('to_addresses', 'like', '%' . $sender . '%')
                                            ->orWhere('cc_addresses', 'like', '%' . $sender . '%');
                                    });
                            });
                        }
                    }
                });
            });

        return $query->count();
    }

    /**
     * Manually create an email record (e.g. client sent to life@geminialife.co.ke).
     */
    public function createEmail(array $data): int
    {
        $uid = 'manual-' . uniqid('', true) . '-' . random_int(1000, 9999);
        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));

        $row = [
            'message_uid' => $uid,
            'folder' => 'INBOX',
            'from_address' => $data['from_address'] ?? '',
            'from_name' => $data['from_name'] ?? null,
            'to_addresses' => $data['to_addresses'] ?? $sender,
            'cc_addresses' => $data['cc_addresses'] ?? null,
            'subject' => $data['subject'] ?? '(No subject)',
            'body_text' => $data['body_text'] ?? $data['body'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'date' => $data['date'] ?? now(),
            'has_attachments' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection('vtiger')->table('mail_manager_emails')->insertGetId($row);
        self::forgetListCaches();

        return (int) $id;
    }

    protected function getContactEmailAddresses(object $contact): array
    {
        $addresses = [];
        $fields = ['email', 'email1', 'secondaryemail', 'otheremail', 'email2'];
        foreach ($fields as $field) {
            $addr = $contact->{$field} ?? null;
            if ($addr && is_string($addr) && filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
                $addresses[] = strtolower(trim($addr));
            }
        }
        return array_unique($addresses);
    }
}
