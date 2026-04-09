<?php

namespace App\Http\Controllers;

use App\Services\BroadcastRecipientImportService;
use App\Services\BroadcastSendHistoryService;
use App\Services\CrmService;
use App\Services\ErpClientService;
use App\Services\MassBroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MassBroadcastController extends Controller
{
    /**
     * Query string params to keep when returning to the broadcast list (GET or after POST).
     *
     * @return array<string, mixed>
     */
    protected function broadcastListQuery(Request $request): array
    {
        $q = [];
        $s = trim((string) $request->get('search', ''));
        if ($s !== '') {
            $q['search'] = $s;
        }
        $ct = trim((string) $request->get('client_type', ''));
        if ($ct !== '' && $ct !== 'all') {
            $q['client_type'] = $ct;
        }
        if ($request->boolean('hide_list_email_recent')) {
            $q['hide_list_email_recent'] = '1';
        }
        if ($request->boolean('hide_list_sms_recent')) {
            $q['hide_list_sms_recent'] = '1';
        }

        return $q;
    }

    public function index(
        Request $request,
        CrmService $crm,
        ErpClientService $erp,
        BroadcastSendHistoryService $history,
    ): View {
        $search = trim((string) $request->get('search', ''));
        $clientType = (string) $request->get('client_type', 'all');
        $limit = min(
            (int) config('mass_broadcast.max_recipients', 500),
            max(50, (int) $request->get('limit', 250))
        );

        $clientTypeNorm = $clientType !== '' ? $clientType : 'all';

        $skipDays = (int) config('mass_broadcast.skip_recent_days', 14);
        $excludeContactIds = [];
        if ($history->tableReady() && $skipDays > 0) {
            if ($request->boolean('hide_list_email_recent')) {
                $excludeContactIds = array_merge(
                    $excludeContactIds,
                    $history->allContactIdsWithRecentSend('email', $skipDays)
                );
            }
            if ($request->boolean('hide_list_sms_recent')) {
                $excludeContactIds = array_merge(
                    $excludeContactIds,
                    $history->allContactIdsWithRecentSend('sms', $skipDays)
                );
            }
        }
        $excludeContactIds = array_values(array_unique(array_map('intval', $excludeContactIds)));

        $customers = $crm->getCustomersForBroadcast(
            $limit,
            0,
            $search !== '' ? $search : null,
            crm_owner_filter(),
            'name',
            $clientTypeNorm,
            $excludeContactIds !== [] ? $excludeContactIds : null,
        );

        $broadcastUsesErpClients = $erp->isClientsViewBackedByErp();
        $lifeSystemOptions = [];
        if ($broadcastUsesErpClients) {
            foreach (['group', 'individual'] as $sys) {
                $lifeSystemOptions[] = [
                    'value' => 'l|' . $sys,
                    'label' => $erp->getClientSystemLabel($sys),
                ];
            }
            if ($erp->optionalClientsSegmentConfigured('mortgage')) {
                $lifeSystemOptions[] = [
                    'value' => 'l|mortgage',
                    'label' => $erp->getClientSystemLabel('mortgage'),
                ];
            }
            if ($erp->optionalClientsSegmentConfigured('group_pension')) {
                $lifeSystemOptions[] = [
                    'value' => 'l|group_pension',
                    'label' => $erp->getClientSystemLabel('group_pension'),
                ];
            }
        }

        $lastBroadcastByContact = [];
        if ($history->tableReady() && $customers->isNotEmpty()) {
            $lastBroadcastByContact = $history->lastSuccessfulSendByContact(
                $customers->pluck('contactid')->map(fn ($id) => (int) $id)->all()
            );
        }

        return view('marketing.broadcast', [
            'customers' => $customers,
            'search' => $search,
            'clientType' => $clientTypeNorm,
            'recordSources' => $crm->getDistinctContactRecordSources(),
            'contactTypeValues' => $crm->getDistinctBroadcastContactTypeValues(),
            'contactTypeCf' => config('mass_broadcast.contact_type_cf'),
            'maxRecipients' => (int) config('mass_broadcast.max_recipients', 500),
            'excelMaxRows' => (int) config('mass_broadcast.excel_max_rows', 5000),
            'broadcastUsesErpClients' => $broadcastUsesErpClients,
            'broadcastLifeSegmentNeedsErp' => str_starts_with($clientTypeNorm, 'l|') && ! $broadcastUsesErpClients,
            'lifeSystemOptions' => $lifeSystemOptions,
            'hideListEmailRecent' => $request->boolean('hide_list_email_recent'),
            'hideListSmsRecent' => $request->boolean('hide_list_sms_recent'),
            'skipRecentDays' => $skipDays,
            'lastBroadcastByContact' => $lastBroadcastByContact,
            'broadcastHistoryReady' => $history->tableReady(),
        ]);
    }

    public function send(
        Request $request,
        MassBroadcastService $broadcast,
        BroadcastRecipientImportService $import,
        CrmService $crm,
    ): RedirectResponse {
        $max = (int) config('mass_broadcast.max_recipients', 500);

        $validated = $request->validate([
            'channel' => 'required|in:email,sms',
            'client_type' => 'nullable|string|max:255',
            'contact_ids' => 'nullable|array|max:' . $max,
            'contact_ids.*' => 'integer|min:1',
            'recipients_file' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:12288',
            'subject' => 'exclude_unless:channel,email|required|string|max:200',
            'body' => 'exclude_unless:channel,email|required|string|max:65535',
            'message' => 'exclude_unless:channel,sms|required|string|max:1600',
        ]);

        set_time_limit(min(600, max(120, $max * 3)));

        $back = $this->broadcastListQuery($request);

        $ids = array_values(array_unique(array_map('intval', $validated['contact_ids'] ?? [])));

        if ($request->hasFile('recipients_file')) {
            $parsed = $import->resolveContactIdsFromUpload($request->file('recipients_file'));
            if ($parsed['ids'] === []) {
                $hint = $parsed['warnings'] !== []
                    ? implode(' ', array_slice($parsed['warnings'], 0, 5))
                    : 'No columns matched. Use headers: Contact ID, Email, Policy number, or Phone.';

                return redirect()
                    ->route('marketing.broadcast', $back)
                    ->withInput()
                    ->with('error', 'Could not resolve any contacts from the file. ' . $hint);
            }
            $ids = array_values(array_unique(array_merge($ids, $parsed['ids'])));
            if (count($parsed['warnings']) > 0) {
                $request->session()->flash(
                    'warning',
                    'Import notes: ' . implode(' ', array_slice($parsed['warnings'], 0, 12))
                        . (count($parsed['warnings']) > 12 ? ' …' : '')
                );
            }
        }

        if ($ids === []) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'Select contacts in the table and/or upload an Excel/CSV file with a header row (Contact ID, Email, Policy number, or Phone).');
        }

        $clientType = trim((string) ($validated['client_type'] ?? 'all'));
        if ($clientType === '') {
            $clientType = 'all';
        }
        $ids = $crm->filterContactIdsByBroadcastClientType($ids, $clientType);
        if ($ids === []) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'No contacts match the selected client type filter (or all rows from the file were excluded).');
        }

        if (count($ids) > $max) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'Too many recipients (' . count($ids) . '). Maximum per send is ' . $max . '.');
        }

        $skipRecentSends = $request->input('skip_recent_sends') === '1' || $request->input('skip_recent_sends') === 1;

        if ($validated['channel'] === 'email') {
            $stats = $broadcast->sendMassEmail(
                $ids,
                $validated['subject'],
                $validated['body'],
                $skipRecentSends
            );
            $msg = sprintf(
                'Email broadcast finished: %d sent, %d failed.',
                $stats['sent'],
                $stats['failed']
            );
            if ($stats['skipped_no_email'] > 0) {
                $msg .= ' ' . $stats['skipped_no_email'] . ' contact(s) had no valid email.';
            }
            if ($stats['duplicate_emails_skipped'] > 0) {
                $msg .= ' ' . $stats['duplicate_emails_skipped'] . ' duplicate address(es) skipped.';
            }
            if (($stats['skipped_recent'] ?? 0) > 0) {
                $msg .= ' ' . $stats['skipped_recent'] . ' skipped (already received a mass email in the last '
                    . (int) config('mass_broadcast.skip_recent_days', 14) . ' days).';
            }
            $redirect = redirect()->route('marketing.broadcast', $back)->with('success', $msg);
            if (($stats['failed'] ?? 0) > 0) {
                $redirect->with('warning', 'Some messages failed. Check logs and mail/Graph configuration.');
            }

            return $redirect;
        }

        $stats = $broadcast->sendMassSms($ids, $validated['message'], $skipRecentSends);

        if (! empty($stats['not_configured'])) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'SMS is not configured. Set ADVANTA_API_KEY, ADVANTA_PARTNER_ID, and ADVANTA_SHORTCODE in .env.');
        }

        $msg = sprintf(
            'SMS broadcast finished: %d sent, %d failed.',
            $stats['sent'],
            $stats['failed']
        );
        if ($stats['skipped_no_phone'] > 0) {
            $msg .= ' ' . $stats['skipped_no_phone'] . ' contact(s) had no phone.';
        }
        if ($stats['duplicate_phones_skipped'] > 0) {
            $msg .= ' ' . $stats['duplicate_phones_skipped'] . ' duplicate number(s) skipped.';
        }
        if (($stats['skipped_recent'] ?? 0) > 0) {
            $msg .= ' ' . $stats['skipped_recent'] . ' skipped (already received a mass SMS in the last '
                . (int) config('mass_broadcast.skip_recent_days', 14) . ' days).';
        }

        if ($stats['failed'] === 0 && $stats['sent'] > 0) {
            return redirect()->route('marketing.broadcast', $back)->with('success', $msg);
        }
        if ($stats['sent'] > 0) {
            return redirect()->route('marketing.broadcast', $back)->with('warning', $msg);
        }

        return redirect()->route('marketing.broadcast', $back)->withInput()->with('error', $msg);
    }
}
