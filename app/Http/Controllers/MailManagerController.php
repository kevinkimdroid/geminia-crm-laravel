<?php

namespace App\Http\Controllers;

use App\Services\MailService;
use App\Support\MailFetchHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        $mailbox = $this->normalizeMailbox($request->get('mailbox'));
        $sender = $mailbox ?: config('email-service.sender', config('mail.from.address', 'life@geminialife.co.ke'));
        $presetFrom = $request->get('from_address');
        $presetFromName = $request->get('from_name');
        return view('tools.mail-manager-create', [
            'recipientAddress' => $sender,
            'presetFrom' => $presetFrom,
            'presetFromName' => $presetFromName,
            'mailbox' => $mailbox,
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
        $redirectParams = ['selected' => $id];
        if ($mailbox = $this->normalizeMailbox($request->get('mailbox'))) {
            $redirectParams['mailbox'] = $mailbox;
        }
        return redirect()->route('tools.mail-manager', $redirectParams)->with('success', 'Email record created.');
    }

    /** @return View|RedirectResponse */
    public function index(Request $request)
    {
        $rawMailbox = strtolower(trim((string) $request->get('mailbox', '')));
        $mailbox = $this->normalizeMailbox($rawMailbox);

        if ($rawMailbox !== '' && $mailbox !== null && $rawMailbox !== $mailbox) {
            return redirect()->route('tools.mail-manager', array_merge(
                $request->query(),
                ['mailbox' => $mailbox]
            ));
        }

        if ($mailbox === null && $request->boolean('pension')) {
            $mailbox = strtolower(trim((string) config('pension.mailbox', ''))) ?: null;
        }

        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $selected = $request->get('selected') ? (int) $request->get('selected') : null;
        $perPageParam = $request->get('per_page', '50');
        $perPage = strtolower((string) $perPageParam) === 'all' ? 9999 : max(10, min(9999, (int) $perPageParam));
        $perPage = $perPage ?: 50;
        $offset = ($page - 1) * $perPage;

        $emails = $this->mailService->getEmails($perPage, $offset, $search, $mailbox);
        $total = $this->mailService->getEmailsCount($search, $mailbox);

        $selectedEmail = null;
        if ($selected) {
            $selectedEmail = $this->mailService->isPensionMailbox($mailbox)
                ? $this->mailService->getPensionInboxEmail($selected)
                : $this->mailService->getEmail($selected);
            if ($selected && $this->mailService->isPensionMailbox($mailbox) && ! $selectedEmail) {
                $selected = null;
            }
        }

        $mailFetchHealth = MailFetchHealth::get();
        $staleMinutes = max(1, (int) config('email-service.health_stale_minutes', 15));
        $lastSuccess = ! empty($mailFetchHealth['last_success_at'])
            ? Carbon::parse($mailFetchHealth['last_success_at'])
            : null;
        $mailFetchHealth['is_stale'] = ! $lastSuccess || $lastSuccess->lt(now()->subMinutes($staleMinutes));
        $mailFetchHealth['stale_minutes'] = $staleMinutes;

        $pensionLatestEmailAt = null;
        if ($this->mailService->isPensionMailbox($mailbox)) {
            $pensionLatestEmailAt = $this->mailService->getPensionInboxLatestReceivedAt();
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
            'mailbox' => $mailbox,
            'useMicrosoftGraph' => $this->mailService->useMicrosoftGraph(),
            'useEmailService' => $this->mailService->useHttpEmailService(),
            'mailFetchHealth' => $mailFetchHealth,
            'pensionLatestEmailAt' => $pensionLatestEmailAt,
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
        if ($policyNumber !== null && $policyNumber !== '' && ! looks_like_kra_pin($policyNumber)) {
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

    /** Get policy_number from ERP/client search by email. Uses policy_number column only. */
    private function getPolicyFromErpSearch(string $email): ?string
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

    /**
     * Lightweight live feed for the pension inbox list (JSON, no full page reload).
     * Reads from DB only — never blocks on Microsoft Graph.
     */
    public function live(Request $request): JsonResponse
    {
        $mailbox = $this->normalizeMailbox($request->get('mailbox'));
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $selected = $request->get('selected') ? (int) $request->get('selected') : null;
        $perPageParam = $request->get('per_page', '50');
        $perPage = strtolower((string) $perPageParam) === 'all' ? 9999 : max(10, min(9999, (int) $perPageParam));
        $perPage = $perPage ?: 50;
        $offset = ($page - 1) * $perPage;

        $cacheSeconds = max(5, (int) env('PENSION_INBOX_LIVE_CACHE_SECONDS', 20));
        $cacheKey = 'mail:live:' . md5(json_encode([
            'mailbox' => $mailbox,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'selected' => $selected,
        ]));

        $payload = Cache::remember($cacheKey, $cacheSeconds, function () use ($search, $perPage, $offset, $mailbox, $selected) {
            $emails = $this->mailService->getEmails($perPage, $offset, $search, $mailbox);
            $total = $this->mailService->getEmailsCount($search, $mailbox);

            return [
                'success' => true,
                'emails' => array_map(function ($email) {
                    return [
                        'id' => (int) $email->id,
                        'from_name' => $email->from_name,
                        'from_address' => $email->from_address,
                        'subject' => $email->subject,
                        'date_label' => $email->date ? Carbon::parse($email->date)->format('M d') : '',
                        'has_attachments' => (bool) $email->has_attachments,
                        'ticket_id' => $email->ticket_id ? (int) $email->ticket_id : null,
                    ];
                }, $emails),
                'total' => $total,
                'selected' => $selected,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Throttled background Graph fetch for pension inbox (separate from fast list polling).
     */
    public function sync(Request $request): JsonResponse
    {
        $mailbox = $this->normalizeMailbox($request->get('mailbox'));
        if (! $this->mailService->isPensionMailbox($mailbox)) {
            return response()->json(['success' => true, 'synced' => false, 'throttled' => true]);
        }

        $synced = $this->maybeFetchLiveMailbox($mailbox);

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'throttled' => ! $synced,
        ]);
    }

    protected function maybeFetchLiveMailbox(?string $mailbox): bool
    {
        if (! filter_var(env('MAIL_AUTO_FETCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $mailboxKey = strtolower(trim((string) ($mailbox ?? 'life')));
        $throttleKey = 'mail:live-fetch:last-run:' . md5($mailboxKey);
        $lockKey = 'mail:live-fetch:lock:' . md5($mailboxKey);
        $interval = max(60, (int) env('PENSION_INBOX_LIVE_FETCH_SECONDS', 120));

        if ((time() - (int) Cache::get($throttleKey, 0)) < $interval) {
            return false;
        }

        $lock = Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            return false;
        }

        try {
            if ((time() - (int) Cache::get($throttleKey, 0)) < $interval) {
                return false;
            }

            $limit = min(25, $this->mailService->resolveFetchLimit($mailbox));
            $result = $this->mailService->fetchAndStoreEmails('INBOX', $limit, $mailbox);

            if (
                ! empty($result['errors'])
                && ($result['stored'] ?? 0) === 0
                && ($result['fetched'] ?? 0) === 0
            ) {
                return false;
            }

            MailFetchHealth::markSuccess($result, 'live');
            MailService::forgetListCaches();
            Cache::forget('mail:live:' . md5(json_encode(['mailbox' => $mailbox])));
            Cache::put($throttleKey, time(), now()->addHours(2));

            return true;
        } catch (\Throwable $e) {
            Log::warning('MailManager live fetch: ' . $e->getMessage());

            return false;
        } finally {
            $lock->release();
        }
    }

    public function fetch(Request $request): RedirectResponse
    {
        set_time_limit(300);
        $mailbox = $this->normalizeMailbox($request->get('mailbox'));
        $override = $request->filled('limit') ? (int) $request->get('limit') : null;
        $limit = $this->mailService->resolveFetchLimit($mailbox, $override);

        try {
            $result = $this->mailService->fetchAndStoreEmails('INBOX', $limit, $mailbox);
        } catch (\Throwable $e) {
            MailFetchHealth::markFailure($e->getMessage(), 'manual');
            if (strpos($e->getMessage(), 'NOOP completed') !== false) {
                $hint = $this->mailService->useMicrosoftGraph()
                    ? 'Check MSGRAPH_* config in .env.'
                    : 'Enable Microsoft Graph (MSGRAPH_ENABLED=true) in .env for Office 365 - see docs.';
                return back()->with('error', 'IMAP fetch failed (Office 365). ' . $hint);
            }
            throw $e;
        }

        if (!empty($result['errors']) && ($result['stored'] ?? 0) === 0 && ($result['fetched'] ?? 0) === 0) {
            MailFetchHealth::markFailure(implode(' ', $result['errors']), 'manual');
            return back()->with('error', implode(' ', $result['errors']));
        }

        MailFetchHealth::markSuccess($result, 'manual');
        MailService::forgetListCaches();
        $msg = "Fetched {$result['fetched']} pension emails, stored {$result['stored']} new (limit {$limit}).";
        if ($this->mailService->isPensionMailbox($mailbox)) {
            $visible = $this->mailService->getEmailsCount(null, $mailbox);
            $msg .= " {$visible} visible in pension inbox.";
        }
        if (! empty($result['notice'])) {
            $msg .= ' ' . $result['notice'];
        }
        if (! empty($result['errors'])) {
            return back()->with('success', $msg)->with('warning', implode(' ', $result['errors']));
        }
        return back()->with('success', $msg);
    }

    protected function normalizeMailbox(mixed $mailbox): ?string
    {
        $mailbox = strtolower(trim((string) ($mailbox ?? '')));
        if ($mailbox === '' || ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $canonical = strtolower(trim((string) config('pension.mailbox', '')));
        $legacy = array_map('strtolower', [
            'pensions.support@geminialife.co.ke',
            'pensionsandinvestments@geminialife.co.ke',
        ]);

        if ($canonical !== '' && in_array($mailbox, $legacy, true)) {
            return $canonical;
        }

        return $mailbox;
    }
}
