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
    public function fetchAndStoreEmails(string $folder = 'INBOX', int $limit = 100): array
    {
        if ($this->useMicrosoftGraph()) {
            return app(MicrosoftGraphMailService::class)->fetchAndStoreEmails(
                strtolower($folder) === 'inbox' ? 'inbox' : $folder,
                config('microsoft-graph.fetch_limit', $limit)
            );
        }

        if ($this->useHttpEmailService()) {
            return $this->fetchAndStoreEmailsViaHttp($folder, $limit);
        }

        return $this->fetchAndStoreEmailsViaImap($folder, $limit);
    }

    /**
     * Fetch emails via HTTP API (email service at 10.10.1.111:8080).
     */
    protected function fetchAndStoreEmailsViaHttp(string $folder, int $limit): array
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
                    ->timeout(10)
                    ->connectTimeout(5)
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
                        ->timeout(10)
                        ->connectTimeout(5)
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

                DB::connection('vtiger')->table('mail_manager_emails')->insert([
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
            } catch (\Throwable $e) {
                $results['errors'][] = $e->getMessage();
                Log::warning('MailService::fetchViaHttp message error: ' . $e->getMessage());
            }
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
    protected function fetchAndStoreEmailsViaImap(string $folder, int $limit): array
    {
        $results = ['fetched' => 0, 'stored' => 0, 'errors' => []];

        try {
            $cm = new ClientManager(config('imap'));
            $client = $cm->account($this->account);
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
                        ->where('folder', $targetFolder->path)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $from = $message->getFrom()->first();
                    $to = $message->getTo()->map(fn ($a) => $a->mail)->implode(', ');
                    $cc = $message->getCc()->count() > 0
                        ? $message->getCc()->map(fn ($a) => $a->mail)->implode(', ')
                        : null;

                    DB::connection('vtiger')->table('mail_manager_emails')->insert([
                        'message_uid' => $uid,
                        'folder' => $targetFolder->path,
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

        return $results;
    }

    /**
     * Get paginated emails for list view. Selects only list columns (excludes body_text/body_html for speed).
     */
    public function getEmails(int $perPage = 20, int $offset = 0, ?string $search = null): array
    {
        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->select('id', 'from_address', 'from_name', 'subject', 'date', 'has_attachments')
            ->orderByDesc('date');

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

    public function getEmailsCount(?string $search = null): int
    {
        if (!$search || trim($search) === '') {
            return (int) Cache::remember('geminia_emails_count', 60, fn () => $this->fetchEmailsCount(null));
        }
        return $this->fetchEmailsCount($search);
    }

    protected function fetchEmailsCount(?string $search): int
    {
        $query = DB::connection('vtiger')->table('mail_manager_emails');

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

    public function getEmail(int $id): ?object
    {
        return DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get emails sent to a contact (from life@geminialife.co.ke or configured sender).
     * Matches to_addresses/cc_addresses containing the contact's email(s).
     */
    public function getEmailsForContact(object $contact, int $perPage = 20, int $offset = 0): array
    {
        $emails = $this->getContactEmailAddresses($contact);
        if (empty($emails)) {
            return [];
        }

        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));

        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->select('id', 'from_address', 'from_name', 'subject', 'date', 'has_attachments')
            ->where('from_address', 'like', '%' . $sender . '%')
            ->where(function ($q) use ($emails) {
                foreach ($emails as $addr) {
                    if (trim($addr) !== '') {
                        $q->orWhere('to_addresses', 'like', '%' . $addr . '%')
                            ->orWhere('cc_addresses', 'like', '%' . $addr . '%');
                    }
                }
            })
            ->orderByDesc('date')
            ->offset($offset)
            ->limit($perPage);

        return $query->get()->all();
    }

    /**
     * Count emails sent to a contact.
     */
    public function getEmailsForContactCount(object $contact): int
    {
        $emails = $this->getContactEmailAddresses($contact);
        if (empty($emails)) {
            return 0;
        }

        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));

        $query = DB::connection('vtiger')
            ->table('mail_manager_emails')
            ->where('from_address', 'like', '%' . $sender . '%')
            ->where(function ($q) use ($emails) {
                foreach ($emails as $addr) {
                    if (trim($addr) !== '') {
                        $q->orWhere('to_addresses', 'like', '%' . $addr . '%')
                            ->orWhere('cc_addresses', 'like', '%' . $addr . '%');
                    }
                }
            });

        return $query->count();
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
