<?php

namespace App\Services;

use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-create complaint in Complaint Register from inbound email (IRA compliance).
 * Runs independently of ticket creation - creates complaints for client emails even
 * when contact resolution fails or ticket auto-creation is disabled.
 */
class AutoComplaintFromEmailService
{
    public function __construct(
        protected CrmService $crm
    ) {}

    /**
     * Process a stored inbound email: create complaint if from external client.
     *
     * @return \App\Models\Complaint|null
     */
    public function processNewInboundEmail(int $emailId): ?Complaint
    {
        $config = config('complaints.auto_from_email', []);
        if (empty($config['enabled'])) {
            return null;
        }

        $email = DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->first();
        if (! $email) {
            return null;
        }

        $fromAddress = trim($email->from_address ?? '');
        if ($fromAddress === '') {
            return null;
        }

        if ($this->isInternalSender($fromAddress)) {
            return null;
        }

        if ($this->emailAlreadyHasComplaint($emailId)) {
            return null;
        }

        try {
            $fromName = trim($email->from_name ?? '') ?: explode('@', $fromAddress)[0] ?? 'Client';
            $description = $this->buildDescription($email);

            $contactId = null;
            $policyNumber = null;
            $contactResult = $this->tryResolveContact($fromAddress, $fromName);
            if ($contactResult) {
                $contactId = $contactResult['contact_id'] ?? null;
                $policyNumber = $contactResult['policy_number'] ?? null;
            }

            if ($policyNumber && $policyNumber !== '' && ! looks_like_kra_pin($policyNumber) && ! looks_like_client_id($policyNumber)) {
                $description = trim($description) . "\n\nRelated policy: " . trim($policyNumber);
            }

            $complaint = Complaint::create([
                'complaint_ref' => Complaint::generateRef(),
                'date_received' => now()->toDateString(),
                'complainant_name' => $fromName,
                'complainant_phone' => null,
                'complainant_email' => $fromAddress,
                'contact_id' => $contactId,
                'policy_number' => $policyNumber,
                'nature' => $config['nature'] ?? 'Other',
                'description' => $description,
                'source' => 'Email',
                'status' => 'Received',
                'priority' => $config['priority'] ?? 'Medium',
            ]);

            if (Schema::connection('vtiger')->hasColumn('mail_manager_emails', 'complaint_id')) {
                DB::connection('vtiger')->table('mail_manager_emails')
                    ->where('id', $emailId)
                    ->update(['complaint_id' => $complaint->id]);
            }

            Log::info('AutoComplaintFromEmailService: created complaint', ['complaint_id' => $complaint->id, 'email_id' => $emailId]);

            return $complaint;
        } catch (\Throwable $e) {
            Log::error('AutoComplaintFromEmailService: failed', ['email_id' => $emailId, 'error' => $e->getMessage()]);
            return null;
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

    protected function emailAlreadyHasComplaint(int $emailId): bool
    {
        if (! Schema::connection('vtiger')->hasColumn('mail_manager_emails', 'complaint_id')) {
            return false;
        }
        $email = DB::connection('vtiger')->table('mail_manager_emails')->where('id', $emailId)->first();
        return $email && $email->complaint_id !== null;
    }

    /**
     * @return array{contact_id: int, policy_number: string|null}|null
     */
    protected function tryResolveContact(string $email, string $name): ?array
    {
        try {
            $contact = $this->crm->findContactByPhoneOrEmail(null, $email);
            if ($contact) {
                $fullContact = $this->crm->getContact((int) $contact->contactid);
                $policyNumber = $fullContact && ! empty($fullContact->policy_number ?? '')
                    ? trim((string) $fullContact->policy_number)
                    : null;
                return ['contact_id' => (int) $contact->contactid, 'policy_number' => $policyNumber];
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
                $policyNumber = $this->getPolicyFromErp($email);
                return ['contact_id' => $contactId, 'policy_number' => $policyNumber];
            }

            return $contactId ? ['contact_id' => $contactId, 'policy_number' => null] : null;
        } catch (\Throwable $e) {
            Log::debug('AutoComplaintFromEmailService: contact resolve failed', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function getPolicyFromErp(string $email): ?string
    {
        $erpResult = app(ErpClientService::class)->searchClients($email, 5);
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
        $subject = trim($email->subject ?? '');
        $body = static::extractClearComplaintText($email->body_text ?? '');

        $lines = [];
        if ($subject !== '') {
            $lines[] = $subject;
        }
        if ($body !== '') {
            $lines[] = '';
            $lines[] = $body;
        }

        return trim(implode("\n", $lines)) ?: 'Complaint received via email.';
    }

    /**
     * Extract clear complaint content from email body - strip signatures, quoted replies, excess whitespace.
     */
    public static function extractClearComplaintText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $clear = [];
        $stopPhrases = [
            'regards', 'best regards', 'kind regards', 'sincerely', 'thanks', 'thank you',
            'sent from my', 'get outlook', 'on behalf of', 'original message', 'forward',
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($clear !== [] && end($clear) !== '') {
                    $clear[] = '';
                }
                continue;
            }
            if (preg_match('/^>{1,}\s*/', $line) || preg_match('/^\|\s*/', $line)) {
                break;
            }
            if (preg_match('/^[-_=*]{3,}/', $trimmed)) {
                break;
            }
            if (preg_match('/^on\s+.+wrote:/i', $trimmed) || preg_match('/^from:\s/i', $trimmed) || preg_match('/^to:\s/i', $trimmed) || preg_match('/^subject:\s/i', $trimmed)) {
                break;
            }
            $lower = strtolower($trimmed);
            $stop = false;
            foreach ($stopPhrases as $phrase) {
                if (str_starts_with($lower, $phrase) || $lower === $phrase) {
                    $stop = true;
                    break;
                }
            }
            if ($stop) {
                break;
            }
            $clear[] = $trimmed;
        }

        $result = implode("\n", $clear);
        $result = preg_replace("/\n{3,}/", "\n\n", $result);
        return \Illuminate\Support\Str::limit(trim($result), 5000);
    }

    /**
     * Clean a stored complaint description for display/export - strips email boilerplate from old format.
     */
    public static function cleanDescriptionForExport(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return '';
        }
        $desc = $description;

        if (str_contains($desc, 'Received via email from') && preg_match('/---\s*\n+/', $desc)) {
            $parts = preg_split('/---\s*\n+/', $desc, 2);
            $header = trim($parts[0] ?? '');
            $body = trim($parts[1] ?? '');
            $subject = '';
            if (preg_match('/Subject:\s*(.+?)(?:\n|$)/is', $header, $m)) {
                $subject = trim($m[1]);
            }
            $cleanBody = static::extractClearComplaintText($body);
            $lines = array_filter([$subject, '', $cleanBody]);
            return trim(implode("\n", $lines));
        }

        return static::extractClearComplaintText($desc);
    }
}
