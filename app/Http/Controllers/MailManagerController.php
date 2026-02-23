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

    /** @return View|RedirectResponse */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $emails = $this->mailService->getEmails($perPage, $offset, $search);
        $total = $this->mailService->getEmailsCount($search);

        return view('tools.mail-manager', [
            'emails' => $emails,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
            'useMicrosoftGraph' => $this->mailService->useMicrosoftGraph(),
            'useEmailService' => $this->mailService->useHttpEmailService(),
        ]);
    }

    public function show(int $id): View
    {
        $email = $this->mailService->getEmail($id);

        if (!$email) {
            abort(404);
        }

        return view('tools.mail-manager-show', ['email' => $email]);
    }

    public function fetch(Request $request): RedirectResponse
    {
        set_time_limit(120);
        $limit = config('email-service.fetch_limit', 25);

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
