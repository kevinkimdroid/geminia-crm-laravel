<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use App\Services\MassBroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditLifeStatementController extends Controller
{
    public function index(Request $request, CrmService $crm): View
    {
        $search = trim((string) $request->get('search', ''));
        $limit = min(
            (int) config('mass_broadcast.max_recipients', 500),
            max(50, (int) $request->get('limit', 250))
        );

        $customers = $crm->getCustomersForBroadcast(
            $limit,
            0,
            $search !== '' ? $search : null,
            crm_owner_filter(),
            'name',
            'all',
            null,
        );

        return view('marketing.credit-life-statements', [
            'customers' => $customers,
            'search' => $search,
            'maxRecipients' => (int) config('mass_broadcast.max_recipients', 500),
        ]);
    }

    public function send(
        Request $request,
        CrmService $crm,
        MassBroadcastService $broadcast,
    ): RedirectResponse {
        $max = (int) config('mass_broadcast.max_recipients', 500);

        $validated = $request->validate([
            'contact_ids' => 'required|array|min:1|max:' . $max,
            'contact_ids.*' => 'integer|min:1',
            'statement_period' => 'required|string|max:120',
            'statement_to_date' => 'required|date',
            'subject' => 'nullable|string|max:200',
            'body' => 'nullable|string|max:65535',
            'email_attachment' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,ppt,pptx|max:10240',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['contact_ids'])));
        $validIds = $crm->getContactsByIds($ids)
            ->pluck('contactid')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($validIds === []) {
            return redirect()
                ->route('marketing.credit-life-statements')
                ->withInput()
                ->with('error', 'No valid recipients were found.');
        }

        $uploaded = $request->file('email_attachment');
        if (! $uploaded || ! $uploaded->isValid()) {
            return redirect()
                ->route('marketing.credit-life-statements')
                ->withInput()
                ->with('error', 'Please upload a valid statement attachment.');
        }

        $content = file_get_contents($uploaded->getRealPath());
        if ($content === false) {
            return redirect()
                ->route('marketing.credit-life-statements')
                ->withInput()
                ->with('error', 'Could not read the selected attachment. Please re-upload and try again.');
        }

        $attachment = [
            'name' => $uploaded->getClientOriginalName() ?: 'statement',
            'contentType' => $uploaded->getMimeType() ?: 'application/octet-stream',
            'content' => $content,
        ];

        $statementPeriod = trim((string) $validated['statement_period']);
        $statementToDate = \Carbon\Carbon::parse((string) $validated['statement_to_date'])->format('d M Y');
        $contextTokens = [
            'statement_period' => $statementPeriod,
            'statement_to_date' => $statementToDate,
            'period' => $statementPeriod,
            'to_date' => $statementToDate,
        ];

        $subjectTemplate = trim((string) ($validated['subject'] ?? ''));
        if ($subjectTemplate === '') {
            $subjectTemplate = 'Your Credit Life statement for {{statement_period}}';
        }

        $bodyTemplate = trim((string) ($validated['body'] ?? ''));
        if ($bodyTemplate === '') {
            $bodyTemplate = "Dear {{firstname}},\n\nPlease find attached your Credit Life statement for {{statement_period}} (as at {{statement_to_date}}).\n\nFor assistance, please contact 0709 551 150 or life@geminialife.co.ke.\n\nKind regards,\nGeminia Life Insurance";
        }

        $stats = $broadcast->sendMassEmail(
            $validIds,
            $subjectTemplate,
            $bodyTemplate,
            $attachment,
            true,
            [],
            [],
            $contextTokens
        );

        $msg = sprintf(
            'Credit Life statements dispatch finished: %d sent, %d failed.',
            $stats['sent'],
            $stats['failed']
        );
        if ($stats['skipped_no_email'] > 0) {
            $msg .= ' ' . $stats['skipped_no_email'] . ' contact(s) had no valid email.';
        }

        if ($stats['failed'] > 0) {
            return redirect()->route('marketing.credit-life-statements')->with('warning', $msg);
        }

        return redirect()->route('marketing.credit-life-statements')->with('success', $msg);
    }
}
