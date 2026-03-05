<?php

namespace App\Http\Controllers;

use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MailManagerController extends Controller
{
    /** @var MailService */
    protected $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function create(Request $request): View
    {
        $sender = config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));
        $presetFrom = $request->get('from_address');
        $presetFromName = $request->get('from_name');
        return view('tools.mail-manager-create', [
            'recipientAddress' => $sender,
            'presetFrom' => $presetFrom,
            'presetFromName' => $presetFromName,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_address' => 'required|email',
            'from_name' => 'nullable|string|max:255',
            'to_addresses' => 'nullable|string|max:500',
            'subject' => 'required|string|max:500',
            'body' => 'nullable|string|max:65000',
            'date' => 'nullable|date',
        ]);

        $data = [
            'from_address' => $validated['from_address'],
            'from_name' => $validated['from_name'] ?? null,
            'to_addresses' => $validated['to_addresses'] ?? config('email-service.sender', 'life@geminialife.co.ke'),
            'subject' => $validated['subject'],
            'body_text' => $validated['body'] ?? null,
            'date' => $validated['date'] ?? now(),
        ];

        $id = $this->mailService->createEmail($data);
        return redirect()->route('tools.mail-manager', ['selected' => $id])->with('success', 'Email record created.');
    }

    /** @return View|RedirectResponse */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $selected = $request->get('selected') ? (int) $request->get('selected') : null;
        $perPageParam = $request->get('per_page', '50');
        $perPage = strtolower((string) $perPageParam) === 'all' ? 9999 : max(10, min(9999, (int) $perPageParam));
        $perPage = $perPage ?: 50;
        $offset = ($page - 1) * $perPage;

        $emails = $this->mailService->getEmails($perPage, $offset, $search);
        $total = $this->mailService->getEmailsCount($search);

        $selectedEmail = null;
        if ($selected) {
            $selectedEmail = $this->mailService->getEmail($selected);
        }

        return view('tools.mail-manager', [
            'emails' => $emails,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'perPageParam' => $perPageParam,
            'search' => $search,
            'selected' => $selected,
            'selectedEmail' => $selectedEmail,
            'useMicrosoftGraph' => $this->mailService->useMicrosoftGraph(),
            'useEmailService' => $this->mailService->useHttpEmailService(),
        ]);
    }

    public function createTicketFromEmail(int $id)
    {
        $email = $this->mailService->getEmail($id);
        if (! $email) {
            abort(404);
        }

        $fromAddress = trim($email->from_address ?? '');
        if ($fromAddress === '') {
            return redirect()->route('tools.mail-manager', ['selected' => $id])
                ->with('error', 'Email has no sender address.');
        }

        $crm = app(\App\Services\CrmService::class);
        $contact = $crm->findContactByPhoneOrEmail(null, $fromAddress);

        $contactId = $contact ? (int) $contact->contactid : null;
        $policyNumber = null;

        if ($contactId) {
            $fullContact = $crm->getContact($contactId);
            if ($fullContact && ! empty($fullContact->policy_number ?? '')) {
                $policyNumber = trim((string) $fullContact->policy_number);
            }
        } else {
            $name = trim($email->from_name ?? '') ?: explode('@', $fromAddress)[0] ?? 'Client';
            $parts = explode(' ', $name, 2);
            $contactId = $crm->createContactFromErpClient([
                'first_name' => $parts[0] ?? 'Client',
                'last_name' => $parts[1] ?? '',
                'name' => $name,
                'email' => $fromAddress,
                'email_adr' => $fromAddress,
                'client_name' => $name,
            ]);
            if ($contactId && app()->bound(\App\Services\ErpClientService::class)) {
                $policyNumber = $this->getPolicyFromErpSearch($fromAddress);
            }
        }

        if (! $contactId) {
            return redirect()->route('tools.mail-manager', ['selected' => $id])
                ->with('error', 'Could not find or create a contact for the sender. Please try again or create the contact manually.');
        }

        $params = [
            'from' => 'mail-manager',
            'email_id' => $id,
            'contact_id' => $contactId,
            'title' => $email->subject ?? '(No subject)',
            'description' => \Illuminate\Support\Str::limit($email->body_text ?? '', 5000),
        ];
        if ($policyNumber !== null && $policyNumber !== '') {
            $params['policy'] = $policyNumber;
        }

        return redirect()->route('tickets.create', $params);
    }

    public function show(int $id)
    {
        $email = $this->mailService->getEmail($id);

        if (!$email) {
            abort(404);
        }

        return redirect()->route('tools.mail-manager', ['selected' => $id]);
    }

    /** Get policy number from ERP search by email; returns null if only PIN found. */
    private function getPolicyFromErpSearch(string $email): ?string
    {
        $erpResult = app(\App\Services\ErpClientService::class)->searchClients($email, 5);
        foreach ($erpResult['data'] ?? [] as $row) {
            $v = trim((string) ($row['policy_no'] ?? $row['policy_number'] ?? ''));
            if ($v !== '' && ! preg_match('/^[A-Z]\d{9}[A-Z]$/i', $v)) {
                return $v;
            }
        }
        return null;
    }

    public function fetch(Request $request): RedirectResponse
    {
        set_time_limit(120);
        $limit = max(50, (int) config('email-service.fetch_limit', 25), (int) config('microsoft-graph.fetch_limit', 25));

        try {
            $result = $this->mailService->fetchAndStoreEmails('INBOX', $limit);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'NOOP completed') !== false) {
                $hint = $this->mailService->useMicrosoftGraph()
                    ? 'Check MSGRAPH_* config in .env.'
                    : 'Enable Microsoft Graph (MSGRAPH_ENABLED=true) in .env for Office 365 - see docs.';
                return back()->with('error', 'IMAP fetch failed (Office 365). ' . $hint);
            }
            throw $e;
        }

        if (!empty($result['errors'])) {
            return back()->with('error', implode(' ', $result['errors']));
        }

        Cache::forget('geminia_emails_count');
        $msg = "Fetched {$result['fetched']} emails, stored {$result['stored']} new.";
        return back()->with('success', $msg);
    }
}
