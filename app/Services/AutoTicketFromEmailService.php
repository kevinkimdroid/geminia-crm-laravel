<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auto-create ticket from inbound email and send confirmation with ticket number.
 */
class AutoTicketFromEmailService
{
    public function __construct(
        protected CrmService $crm
    ) {}

    /**
     * Process a newly stored inbound email: create ticket, link it, send auto-reply.
     *
     * @return array{ticket_id: int|null, sent: bool, error: string|null}
     */
    public function processNewInboundEmail(int $emailId): array
    {
        $config = config('tickets.auto_ticket_from_email', []);
        if (empty($config['enabled'])) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'disabled'];
        }

        $email = DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->first();
        if (! $email) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Email not found'];
        }

        $fromAddress = trim($email->from_address ?? '');
        if ($fromAddress === '') {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'No sender'];
        }

        if ($this->isInternalSender($fromAddress)) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Internal sender'];
        }

        $contactResult = $this->resolveOrCreateContactWithPolicy($fromAddress, $email->from_name ?? '');
        $contactId = $contactResult['contact_id'] ?? null;
        $policyNumber = $contactResult['policy_number'] ?? null;

        if (! $contactId) {
            return ['ticket_id' => null, 'sent' => false, 'error' => 'Could not find or create contact'];
        }

        $description = $this->buildDescription($email);
        if ($policyNumber !== null && $policyNumber !== '' && ! looks_like_kra_pin($policyNumber) && ! looks_like_client_id($policyNumber)) {
            $description = trim($description) . "\n\nRelated policy: " . trim($policyNumber);
        }

        try {
            $ticketId = $this->createTicket([
                'title' => $email->subject ?? 'Re: ' . ($email->subject ?: 'Inquiry'),
                'description' => $description,
                'contact_id' => $contactId,
                'source' => $config['source'] ?? 'Email',
            ]);

            DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->update(['ticket_id' => $ticketId]);

            $ticketNo = 'TT' . $ticketId;
            $title = $email->subject ?? 'Re: ' . ($email->subject ?: 'Inquiry');
            $ownerId = (int) ($config['assign_to_user_id'] ?? 1);
            try {
                app(TicketNotificationService::class)->sendTicketCreatedNotification(
                    $ticketId,
                    $ticketNo,
                    $title,
                    $ownerId,
                    $contactId,
                    $policyNumber ?: null,
                    false // skip contact - they get auto-reply
                );
            } catch (\Throwable $notifyEx) {
                Log::warning('AutoTicketFromEmailService: creation notification failed', ['ticket_id' => $ticketId, 'error' => $notifyEx->getMessage()]);
            }

            $sent = $this->sendAutoReply($fromAddress, $email->from_name ?? '', $ticketNo, $email->subject ?? 'Your inquiry');

            \Illuminate\Support\Facades\Cache::forget('geminia_ticket_counts_by_status');
            \Illuminate\Support\Facades\Cache::forget('geminia_tickets_count');
            \App\Events\DashboardStatsUpdated::dispatch();

            return ['ticket_id' => $ticketId, 'sent' => $sent, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('AutoTicketFromEmailService', ['email_id' => $emailId, 'error' => $e->getMessage()]);
            return ['ticket_id' => null, 'sent' => false, 'error' => $e->getMessage()];
        }
    }

    protected function isInternalSender(string $address): bool
    {
        $address = strtolower($address);
        $internal = array_map('strtolower', array_filter([
            config('email-service.sender'),
            config('mail.from.address'),
            'life@geminialife.co.ke',
            'servicinglife@geminialife.co.ke',
            'financelife@geminialife.co.ke',
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
     * Resolve or create contact by email; return contact_id and policy_number when available.
     *
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

        if ($contactId && app()->bound(\App\Services\ErpClientService::class)) {
            $policyNumber = $this->getPolicyFromErpSearchExcludingPin($email);
        }

        return ['contact_id' => $contactId, 'policy_number' => $policyNumber ?: null];
    }

    /** Get policy_number from ERP/client search by email. Uses policy_number column only (never policy_no). */
    protected function getPolicyFromErpSearchExcludingPin(string $email): ?string
    {
        $erpResult = app(\App\Services\ErpClientService::class)->searchClients($email, 5);
        foreach ($erpResult['data'] ?? [] as $row) {
            $v = trim((string) ($row['policy_number'] ?? ''));
            if ($v !== '' && ! looks_like_kra_pin($v)) {
                return $v;
            }
        }
        return null;
    }

    protected function buildDescription(object $email): string
    {
        $text = $email->body_text ?? '';
        $text = \Illuminate\Support\Str::limit($text, 8000);
        $from = trim($email->from_name ?? '') ? "{$email->from_name} <{$email->from_address}>" : $email->from_address;
        return "Received via email from {$from}\n\nSubject: " . ($email->subject ?? '') . "\n\n---\n\n{$text}";
    }

    protected function createTicket(array $data): int
    {
        $config = config('tickets.auto_ticket_from_email', []);
        $userId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
        $ownerId = (int) ($config['assign_to_user_id'] ?? $userId);
        $category = $config['category'] ?? 'Other';
        $now = now()->format('Y-m-d H:i:s');
        $id = (int) DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

        DB::connection('vtiger')->transaction(function () use ($data, $userId, $ownerId, $category, $now, $id) {
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
                'source' => $data['source'] ?? 'Email',
                'label' => $data['title'],
            ]);

            DB::connection('vtiger')->table('vtiger_troubletickets')->insert([
                'ticketid' => $id,
                'ticket_no' => 'TT' . $id,
                'title' => $data['title'],
                'status' => 'Open',
                'priority' => 'Normal',
                'severity' => null,
                'category' => $category,
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
        $config = config('tickets.auto_ticket_from_email', []);
        if (empty($config['auto_reply_enabled'])) {
            return false;
        }

        $fromName = config('mail.from.name', config('app.name'));
        $body = trim($config['auto_reply_body'] ?? '') ?: "Thank you for your email.\n\nWe have received your message and created a support ticket for you.\n\nYour ticket number is: {ticket_no}\n\nWe will respond to your inquiry as soon as possible.\n\nKind regards,\n{$fromName}";
        $body = str_replace(['{ticket_no}', '{{ticket_number}}'], $ticketNo, $body);
        $subject = trim($config['auto_reply_subject'] ?? '') ?: 'Re: ' . $originalSubject;
        $subject = str_replace(['{ticket_no}', '{{ticket_number}}', '{{subject}}'], [$ticketNo, $ticketNo, $originalSubject], $subject);

        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            if ($graph->sendMail($toAddress, $toName ?: null, $subject, $body, false)) {
                return true;
            }
            Log::warning('AutoTicketFromEmailService: Graph send failed, falling back to Laravel Mail');
        }

        try {
            $fromAddress = config('mail.from.address', config('email-service.sender', 'life@geminialife.co.ke'));
            if (config('mail.default') === 'log') {
                Log::info('AutoTicketFromEmailService: MAIL_MAILER=log – email not actually sent. Set MAIL_MAILER=smtp for delivery.');
            }
            Mail::raw($body, function ($message) use ($toAddress, $toName, $subject, $fromAddress, $fromName) {
                $message->to($toAddress, $toName ?: null)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('AutoTicketFromEmailService: auto-reply failed', ['to' => $toAddress, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
