<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auto-create CRM tickets from client emails fetched into the pension mailbox inbox.
 */
class PensionAutoTicketFromEmailService
{
    public function __construct(
        protected CrmService $crm,
        protected MailService $mailService,
        protected TicketSlaService $sla
    ) {}

    /**
     * @return array{ticket_id: int|null, sent: bool, error: string|null}
     */
    public function processNewInboundEmail(int $emailId): array
    {
        $config = config('pension.auto_ticket', []);
        if (empty($config['enabled'])) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'disabled'];
        }

        if (! $this->mailService->isPensionMailboxEmail($emailId)) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'not pension mailbox'];
        }

        $email = DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->first();
        if (! $email) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Email not found'];
        }

        if (! empty($email->ticket_id)) {
            return ['ticket_id' => (int) $email->ticket_id, 'sent' => false, 'error' => 'already linked'];
        }

        $fromAddress = trim($email->from_address ?? '');
        if ($fromAddress === '') {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'No sender'];
        }

        if ($this->isInternalSender($fromAddress)) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Internal sender'];
        }

        $ownerId = $this->resolveUserIdByEmail((string) ($config['assign_to_email'] ?? ''));
        if (! $ownerId) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Assignee user not found: ' . ($config['assign_to_email'] ?? '')];
        }

        $contactResult = $this->resolveOrCreateContactWithPolicy($fromAddress, $email->from_name ?? '');
        $contactId = $contactResult['contact_id'] ?? null;
        $policyNumber = $contactResult['policy_number'] ?? null;

        if (! $contactId) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Could not find or create contact'];
        }

        $category = trim((string) ($config['category'] ?? 'Pension Administration'));
        $this->sla->setDepartmentTat($category, (int) ($config['tat_hours'] ?? 24));

        $description = $this->buildDescription($email);
        if ($policyNumber !== null && $policyNumber !== '' && ! looks_like_kra_pin($policyNumber) && ! looks_like_client_id($policyNumber)) {
            $description = trim($description) . "\n\nRelated policy: " . trim($policyNumber);
        }

        try {
            $ticketId = $this->createTicket([
                'title' => $email->subject ?? 'Pension inquiry',
                'description' => $description,
                'contact_id' => $contactId,
                'source' => $config['source'] ?? 'Pension Email',
                'category' => $category,
                'priority' => $config['priority'] ?? 'Normal',
                'owner_id' => $ownerId,
            ]);

            DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->update(['ticket_id' => $ticketId]);

            $ticketNo = 'TT' . $ticketId;
            try {
                app(TicketNotificationService::class)->sendTicketCreatedNotification(
                    $ticketId,
                    $ticketNo,
                    $email->subject ?? 'Pension inquiry',
                    $ownerId,
                    $contactId,
                    $policyNumber ?: null,
                    false
                );
            } catch (\Throwable $notifyEx) {
                Log::warning('PensionAutoTicketFromEmailService: creation notification failed', [
                    'ticket_id' => $ticketId,
                    'error' => $notifyEx->getMessage(),
                ]);
            }

            $sent = $this->sendAutoReply($fromAddress, $email->from_name ?? '', $ticketNo, $email->subject ?? 'Your inquiry');

            Cache::forget('geminia_ticket_counts_by_status');
            Cache::forget('geminia_tickets_count');
            \App\Events\DashboardStatsUpdated::dispatch();

            return ['ticket_id' => $ticketId, 'sent' => $sent, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('PensionAutoTicketFromEmailService', ['email_id' => $emailId, 'error' => $e->getMessage()]);

            return ['ticket_id' => null, 'sent' => false, 'error' => $e->getMessage()];
        }
    }

    protected function isInternalSender(string $address): bool
    {
        $address = strtolower($address);
        $internal = array_map('strtolower', array_filter([
            config('pension.mailbox'),
            config('pension.msgraph_mailbox'),
            config('email-service.sender'),
            config('mail.from.address'),
        ]));

        foreach ($internal as $i) {
            if ($i && (str_contains($address, $i) || str_contains($i, $address))) {
                return true;
            }
        }

        foreach (config('email-service.excluded_sender_domains', []) as $domain) {
            if ($domain && str_ends_with($address, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{contact_id: int|null, policy_number: string|null}
     */
    protected function resolveOrCreateContactWithPolicy(string $email, string $name): array
    {
        $contact = $this->crm->findContactByPhoneOrEmail(null, $email);
        $policyNumber = null;

        if ($contact) {
            $fullContact = $this->crm->getContact((int) $contact->contactid);
            if ($fullContact && ! empty($fullContact->policy_number ?? '')) {
                $policyNumber = trim((string) $fullContact->policy_number);
            }

            return ['contact_id' => (int) $contact->contactid, 'policy_number' => $policyNumber ?: null];
        }

        $name = trim($name) ?: explode('@', $email)[0] ?? 'Client';
        $parts = explode(' ', $name, 2);
        $contactId = $this->crm->createContactFromErpClient([
            'first_name' => $parts[0] ?? 'Client',
            'last_name' => $parts[1] ?? '',
            'name' => $name,
            'email' => $email,
            'email_adr' => $email,
            'client_name' => $name,
        ]);

        if ($contactId && app()->bound(ErpClientService::class)) {
            $erpResult = app(ErpClientService::class)->searchClients($email, 5);
            foreach ($erpResult['data'] ?? [] as $row) {
                $v = trim((string) ($row['policy_number'] ?? ''));
                if ($v !== '' && ! looks_like_kra_pin($v)) {
                    $policyNumber = $v;
                    break;
                }
            }
        }

        return ['contact_id' => $contactId, 'policy_number' => $policyNumber ?: null];
    }

    protected function buildDescription(object $email): string
    {
        $text = \Illuminate\Support\Str::limit($email->body_text ?? '', 8000);
        $from = trim($email->from_name ?? '') ? "{$email->from_name} <{$email->from_address}>" : $email->from_address;
        $mailbox = config('pension.mailbox', 'pensions@geminialife.co.ke');

        return "Received via pension mailbox ({$mailbox}) from {$from}\n\nSubject: " . ($email->subject ?? '') . "\n\n---\n\n{$text}";
    }

    protected function createTicket(array $data): int
    {
        $userId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
        $ownerId = (int) ($data['owner_id'] ?? $userId);
        $now = now()->format('Y-m-d H:i:s');
        $id = (int) DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

        DB::connection('vtiger')->transaction(function () use ($data, $userId, $ownerId, $now, $id) {
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $id,
                'smcreatorid' => $userId,
                'smownerid' => $ownerId,
                'modifiedby' => $userId,
                'setype' => 'HelpDesk',
                'description' => $data['description'] ?? '',
                'createdtime' => $now,
                'modifiedtime' => $now,
                'viewedtime' => null,
                'status' => 1,
                'version' => 0,
                'presence' => 1,
                'deleted' => 0,
                'smgroupid' => 0,
                'source' => $data['source'] ?? 'Pension Email',
                'label' => $data['title'],
            ]);

            DB::connection('vtiger')->table('vtiger_troubletickets')->insert([
                'ticketid' => $id,
                'ticket_no' => 'TT' . $id,
                'title' => $data['title'],
                'status' => 'Open',
                'priority' => $data['priority'] ?? 'Normal',
                'severity' => null,
                'category' => $data['category'] ?? 'Pension Administration',
                'contact_id' => $data['contact_id'],
                'product_id' => null,
                'parent_id' => null,
                'hours' => null,
                'days' => null,
            ]);
        });

        return $id;
    }

    protected function sendAutoReply(string $toAddress, string $toName, string $ticketNo, string $originalSubject): bool
    {
        $config = config('pension.auto_ticket', []);
        if (empty($config['auto_reply_enabled'])) {
            return false;
        }

        $fromName = config('mail.from.name', config('app.name'));
        $body = trim($config['auto_reply_body'] ?? '') ?: "Thank you for contacting Geminia Pension Support.\n\nWe have received your email and created ticket {ticket_no}. Our team will respond within one business day.\n\nKind regards,\nPension Support";
        $body = str_replace(['{ticket_no}', '{{ticket_number}}'], $ticketNo, $body);
        $subject = trim($config['auto_reply_subject'] ?? '') ?: 'Re: ' . $originalSubject;
        $subject = str_replace(['{ticket_no}', '{{ticket_number}}', '{{subject}}'], [$ticketNo, $ticketNo, $originalSubject], $subject);

        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            if ($graph->sendMail($toAddress, $toName ?: null, $subject, $body, false)) {
                return true;
            }
        }

        try {
            $fromAddress = config('pension.mailbox', config('mail.from.address'));
            Mail::raw($body, function ($message) use ($toAddress, $toName, $subject, $fromAddress, $fromName) {
                $message->to($toAddress, $toName ?: null)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('PensionAutoTicketFromEmailService: auto-reply failed', ['to' => $toAddress, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function resolveUserIdByEmail(string $email): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $id = DB::connection('vtiger')->table('vtiger_users')
            ->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(TRIM(email1)) = ?', [$email])
                    ->orWhereRaw('LOWER(TRIM(user_name)) = ?', [$email]);
            })
            ->where('status', 'Active')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
