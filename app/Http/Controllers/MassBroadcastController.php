<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\BroadcastRecipientImportService;
use App\Services\BroadcastSendHistoryService;
use App\Services\CrmService;
use App\Services\ErpClientService;
use App\Services\MassBroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MassBroadcastController extends Controller
{
    public function downloadRecipientsTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Broadcast Recipients');

        $sheet->fromArray(
            [['Contact ID', 'Policy number', 'Email', 'Mobile', 'First name']],
            null,
            'A1'
        );

        $sheet->fromArray(
            [
                ['', 'IL-GEMS-2025006599', 'member.one@example.com', '0702000001', 'Member'],
                ['', 'IL-GEMS-2025006600', 'member.two@example.com', '0702000002', 'Member'],
                ['', '', '', '', ''],
                ['Notes', 'Use Contact ID OR Policy OR Email OR Mobile (any one is enough).', '', '', ''],
            ],
            null,
            'A2'
        );

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'broadcast-members-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function extractFirstValidEmail(?string $raw): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return '';
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }

        $parts = preg_split('/[;,\\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $part) {
            $candidate = trim((string) $part);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Extract first valid email from a structured payload row.
     *
     * @param  array<string, mixed>  $row
     */
    protected function extractEmailFromRow(array $row): string
    {
        $preferredKeys = [
            'email_adr', 'emailAdr', 'EMAIL_ADR',
            'client_email', 'CLIENT_EMAIL',
            'mem_email', 'MEM_EMAIL',
            'email', 'EMAIL',
        ];

        foreach ($preferredKeys as $key) {
            if (array_key_exists($key, $row)) {
                $email = $this->extractFirstValidEmail(is_scalar($row[$key]) ? (string) $row[$key] : null);
                if ($email !== '') {
                    return $email;
                }
            }
        }

        // Last-resort: scan any email-like field name from upstream payloads.
        foreach ($row as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            if (! str_contains(strtolower((string) $key), 'email')) {
                continue;
            }
            $email = $this->extractFirstValidEmail((string) $value);
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

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

    /**
     * Collapse repeated list rows that look like the same person.
     * Only deduplicates when both rows share the same full name and either same email or same phone.
     *
     * @param  Collection<int, object>  $customers
     * @return array{customers: Collection<int, object>, duplicatesCollapsed: int}
     */
    protected function deduplicateBroadcastCustomers(Collection $customers): array
    {
        $out = collect();
        $seenKeyToIndex = [];
        $collapsed = 0;

        foreach ($customers as $customer) {
            $name = strtolower(trim((string) (($customer->firstname ?? '') . ' ' . ($customer->lastname ?? ''))));
            $email = strtolower(trim((string) ($customer->email ?? '')));
            $phone = preg_replace('/\D+/', '', trim((string) (($customer->mobile ?? '') ?: ($customer->phone ?? '')))) ?: '';

            $keys = [];
            if ($name !== '' && $email !== '') {
                $keys[] = 'ne|' . $name . '|' . $email;
            }
            if ($name !== '' && $phone !== '') {
                $keys[] = 'np|' . $name . '|' . $phone;
            }

            $existingIndex = null;
            foreach ($keys as $key) {
                if (isset($seenKeyToIndex[$key])) {
                    $existingIndex = $seenKeyToIndex[$key];
                    break;
                }
            }

            if ($existingIndex === null) {
                $customer->duplicate_count = 0;
                $out->push($customer);
                $newIndex = $out->count() - 1;
                foreach ($keys as $key) {
                    $seenKeyToIndex[$key] = $newIndex;
                }
                continue;
            }

            $collapsed++;
            $kept = $out->get($existingIndex);
            $kept->duplicate_count = (int) ($kept->duplicate_count ?? 0) + 1;

            // Keep whichever duplicate has richer contact details for list usability.
            if (trim((string) ($kept->email ?? '')) === '' && $email !== '') {
                $kept->email = (string) ($customer->email ?? '');
            }
            if (trim((string) ($kept->mobile ?? '')) === '' && trim((string) ($customer->mobile ?? '')) !== '') {
                $kept->mobile = (string) ($customer->mobile ?? '');
            }
            if (trim((string) ($kept->phone ?? '')) === '' && trim((string) ($customer->phone ?? '')) !== '') {
                $kept->phone = (string) ($customer->phone ?? '');
            }
            if (trim((string) ($kept->intermediary ?? '')) === '' && trim((string) ($customer->intermediary ?? '')) !== '') {
                $kept->intermediary = (string) ($customer->intermediary ?? '');
            }
            if (trim((string) ($kept->pol_prepared_by ?? '')) === '' && trim((string) ($customer->pol_prepared_by ?? '')) !== '') {
                $kept->pol_prepared_by = (string) ($customer->pol_prepared_by ?? '');
            }

            $out->put($existingIndex, $kept);
        }

        return [
            'customers' => $out->values(),
            'duplicatesCollapsed' => $collapsed,
        ];
    }

    /**
     * Enrich broadcast rows from ERP policy details so Broadcast matches Client details view.
     *
     * @param  Collection<int, object>  $customers
     * @return Collection<int, object>
     */
    protected function enrichBroadcastIntermediaryFromPolicy(
        Collection $customers,
        CrmService $crm,
        ErpClientService $erp,
        string $search = '',
    ): Collection {
        if ($customers->isEmpty() || ! $erp->isClientsViewBackedByErp()) {
            return $customers;
        }

        $contactIds = $customers->pluck('contactid')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($contactIds === []) {
            return $customers;
        }

        $policyByContact = $crm->getContactPolicyNumbersByIds($contactIds);
        $policyCandidates = [];
        foreach ($customers as $customer) {
            $cid = (int) ($customer->contactid ?? 0);
            $policyCandidates[] = trim((string) ($policyByContact[$cid] ?? ''));
            $policyCandidates[] = trim((string) ($customer->policy_number ?? $customer->policy_no ?? ''));
        }
        $policies = array_values(array_unique(array_filter($policyCandidates)));
        if ($policies === []) {
            return $customers;
        }

        $policyMeta = [];
        $cacheEmailByPolicy = [];
        $httpUrl = trim((string) config('erp.clients_http_url', ''));
        $httpEmailByPolicy = [];
        if (
            config('erp.clients_view_source') === 'erp_sync'
            && Schema::hasTable('erp_clients_cache')
            && $policies !== []
        ) {
            $cacheEmailColumn = null;
            foreach (['email_adr', 'email', 'client_email', 'mem_email'] as $candidate) {
                if (Schema::hasColumn('erp_clients_cache', $candidate)) {
                    $cacheEmailColumn = $candidate;
                    break;
                }
            }
            if ($cacheEmailColumn !== null) {
                try {
                    $cacheEmailByPolicy = DB::table('erp_clients_cache')
                        ->whereIn('policy_number', $policies)
                        ->pluck($cacheEmailColumn, 'policy_number')
                        ->mapWithKeys(fn ($value, $policy) => [(string) $policy => $this->extractFirstValidEmail(is_scalar($value) ? (string) $value : null)])
                        ->all();
                } catch (\Throwable $ignored) {
                    $cacheEmailByPolicy = [];
                }
            }
        }
        // Keep list rendering responsive on larger sets while still enriching enough rows for accuracy.
        $lookupLimit = trim($search) !== ''
            ? min(count($policies), max(20, (int) config('mass_broadcast.enrichment_lookup_limit_search', 120)))
            : min(count($policies), max(20, (int) config('mass_broadcast.enrichment_lookup_limit_default', 60)));
        $policyDetailCacheSeconds = max(60, (int) config('mass_broadcast.erp_policy_detail_cache_seconds', 900));
        foreach (array_slice($policies, 0, $lookupLimit) as $policy) {
            $cacheKey = 'broadcast:erp-policy-detail:' . md5(strtoupper($policy));
            $detail = Cache::remember(
                $cacheKey,
                now()->addSeconds($policyDetailCacheSeconds),
                fn () => $erp->getPolicyDetails($policy)
            );
            if (! is_array($detail) || $detail === []) {
                continue;
            }
            $intermediary = trim((string) ($detail['intermediary'] ?? $detail['agn_name'] ?? ''));
            $preparedBy = trim((string) ($detail['pol_prepared_by'] ?? $detail['bra_manager'] ?? ''));
            $product = trim((string) ($detail['product'] ?? $detail['prod_desc'] ?? ''));
            $policyNo = trim((string) ($detail['policy_number'] ?? $detail['policy_no'] ?? $policy));
            $lifeSystem = trim((string) ($detail['life_system'] ?? ''));
            if ($lifeSystem === '' && $product !== '') {
                $lifeSystem = $erp->getLifeSystemFromProduct($product);
            }
            $status = trim((string) ($detail['status'] ?? ''));
            $lifeAssured = trim((string) ($detail['life_assur'] ?? $detail['life_assured'] ?? $detail['client_name'] ?? $detail['name'] ?? ''));
            $email = $this->extractEmailFromRow($detail);
            if ($email === '' && isset($cacheEmailByPolicy[$policy])) {
                $email = (string) $cacheEmailByPolicy[$policy];
            }
            if (
                $email === ''
                && $httpUrl !== ''
                && config('erp.clients_view_source') === 'erp_http'
                && trim($search) !== ''
            ) {
                if (! array_key_exists($policy, $httpEmailByPolicy)) {
                    $resolved = '';
                    try {
                        $debugUrl = preg_replace('#/clients/?$#i', '/clients/debug', $httpUrl) ?: $httpUrl;
                        $dbg = Http::timeout(8)->get($debugUrl, ['policy' => $policy, 'raw' => 1]);
                        if ($dbg->successful()) {
                            $dbgBody = $dbg->json();
                            $rawNonNull = is_array($dbgBody['raw_non_null'] ?? null) ? $dbgBody['raw_non_null'] : [];
                            $resolved = $this->extractEmailFromRow($rawNonNull);
                        }
                        if ($resolved === '') {
                            $res = Http::timeout(8)->get($httpUrl, ['search' => $policy, 'limit' => 12]);
                            if ($res->successful()) {
                                $body = $res->json();
                                $rows = is_array($body['data'] ?? null)
                                    ? $body['data']
                                    : (is_array($body['clients'] ?? null) ? $body['clients'] : []);
                                foreach ($rows as $r) {
                                    $row = is_array($r) ? $r : (array) $r;
                                    $resolved = $this->extractEmailFromRow($row);
                                    if ($resolved !== '') {
                                        break;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $ignored) {
                        $resolved = '';
                    }
                    $httpEmailByPolicy[$policy] = $resolved;
                }
                if (($httpEmailByPolicy[$policy] ?? '') !== '') {
                    $email = (string) $httpEmailByPolicy[$policy];
                }
            }
            $phone = trim((string) ($detail['phone_no'] ?? $detail['mobile'] ?? $detail['phone'] ?? $detail['client_contact'] ?? ''));

            if ($intermediary === '' && $preparedBy === '' && $product === '' && $policyNo === '' && $lifeAssured === '' && $email === '' && $phone === '') {
                continue;
            }
            $policyMeta[$policy] = [
                'intermediary' => $intermediary,
                'pol_prepared_by' => $preparedBy,
                'policy_number' => $policyNo,
                'product' => $product,
                'life_system' => $lifeSystem,
                'status' => $status,
                'life_assur' => $lifeAssured,
                'email' => $email,
                'phone' => $phone,
            ];
        }

        if ($policyMeta === []) {
            return $customers;
        }

        return $customers->map(function ($customer) use ($policyByContact, $policyMeta) {
            $cid = (int) ($customer->contactid ?? 0);
            $policy = trim((string) ($policyByContact[$cid] ?? ''));
            if ($policy === '') {
                $policy = trim((string) ($customer->policy_number ?? $customer->policy_no ?? ''));
            }
            if ($policy === '' || ! isset($policyMeta[$policy])) {
                return $customer;
            }
            $meta = $policyMeta[$policy];
            if (($meta['intermediary'] ?? '') !== '') {
                $customer->intermediary = $meta['intermediary'];
            }
            if (($meta['pol_prepared_by'] ?? '') !== '') {
                $customer->pol_prepared_by = $meta['pol_prepared_by'];
            }
            if (($meta['policy_number'] ?? '') !== '') {
                $customer->policy_number = $meta['policy_number'];
            }
            if (($meta['product'] ?? '') !== '') {
                $customer->product = $meta['product'];
            }
            if (($meta['life_system'] ?? '') !== '') {
                $customer->life_system = $meta['life_system'];
            }
            if (($meta['status'] ?? '') !== '') {
                $customer->status = $meta['status'];
            }
            if (($meta['life_assur'] ?? '') !== '' && trim((string) (($customer->firstname ?? '') . ' ' . ($customer->lastname ?? ''))) === '') {
                $customer->firstname = $meta['life_assur'];
                $customer->lastname = '';
            }
            if (($meta['email'] ?? '') !== '') {
                $customer->email = $meta['email'];
            }
            if (($meta['phone'] ?? '') !== '' && trim((string) (($customer->mobile ?? '') ?: ($customer->phone ?? ''))) === '') {
                $customer->mobile = $meta['phone'];
                $customer->phone = $meta['phone'];
            }

            return $customer;
        });
    }

    public function index(
        Request $request,
        CrmService $crm,
        ErpClientService $erp,
        BroadcastSendHistoryService $history,
    ): View {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

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
        // Enrich on normal list views too, so intermediary values come from ERP policy data (not CRM owner).
        $eagerEnrichMaxRows = max(0, (int) config('mass_broadcast.enrichment_eager_max_rows', 80));
        $shouldEnrich = $search !== ''
            || str_starts_with($clientTypeNorm, 'l|')
            || ($eagerEnrichMaxRows > 0 && $customers->count() <= $eagerEnrichMaxRows);
        if ($shouldEnrich) {
            $customers = $this->enrichBroadcastIntermediaryFromPolicy($customers, $crm, $erp, $search);
        }
        $deduped = $this->deduplicateBroadcastCustomers($customers);
        $customers = $deduped['customers'];
        $duplicatesCollapsed = $deduped['duplicatesCollapsed'];

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

        $emailAdvertTemplates = EmailTemplate::query()
            ->whereIn('module_name', ['Broadcast', 'Marketing'])
            ->orderBy('template_name')
            ->get(['id', 'template_name', 'subject', 'body', 'description', 'module_name']);

        $smsAdvertTemplates = EmailTemplate::query()
            ->where('module_name', 'Broadcast SMS')
            ->orderBy('template_name')
            ->get(['id', 'template_name', 'subject', 'body', 'description']);

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
            'duplicatesCollapsed' => $duplicatesCollapsed,
            'emailAdvertTemplates' => $emailAdvertTemplates,
            'smsAdvertTemplates' => $smsAdvertTemplates,
        ]);
    }

    public function send(
        Request $request,
        MassBroadcastService $broadcast,
        BroadcastRecipientImportService $import,
        CrmService $crm,
    ): RedirectResponse {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $max = (int) config('mass_broadcast.max_recipients', 500);

        $validated = $request->validate([
            'channel' => 'required|in:email,sms',
            'client_type' => 'nullable|string|max:255',
            'contact_ids' => 'nullable|array|max:' . $max,
            'contact_ids.*' => 'integer|min:1',
            'recipients_file' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:12288',
            'subject' => 'exclude_unless:channel,email|required|string|max:200',
            'body' => 'exclude_unless:channel,email|required|string|max:65535',
            'email_attachment' => 'exclude_unless:channel,email|nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,ppt,pptx|max:10240',
            'message' => 'exclude_unless:channel,sms|required|string|max:1600',
        ]);

        @set_time_limit(min(1800, max(600, $max * 5)));

        $back = $this->broadcastListQuery($request);

        $ids = array_values(array_unique(array_map('intval', $validated['contact_ids'] ?? [])));
        $emailOverrides = [];
        $fileEmailRecipients = [];

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
            $emailOverrides = array_replace($emailOverrides, $parsed['email_overrides'] ?? []);
            $fileEmailRecipients = array_merge($fileEmailRecipients, $parsed['email_recipients'] ?? []);
            if (count($parsed['warnings']) > 0) {
                $request->session()->flash(
                    'warning',
                    'Import notes: ' . implode(' ', array_slice($parsed['warnings'], 0, 12))
                        . (count($parsed['warnings']) > 12 ? ' …' : '')
                );
            }
        }

        if ($ids === [] && ! ($validated['channel'] === 'email' && $fileEmailRecipients !== [])) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'Select contacts in the table and/or upload an Excel/CSV file with a header row (Contact ID, Email, Policy number, or Phone).');
        }

        if ($ids !== []) {
            // Validate that selected/uploaded IDs exist in CRM contacts before any send.
            $validIds = $crm->getContactsByIds($ids)
                ->pluck('contactid')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $missingCount = count($ids) - count($validIds);
            $ids = $validIds;
            if ($emailOverrides !== []) {
                $validFlip = array_flip($ids);
                $emailOverrides = array_filter(
                    $emailOverrides,
                    fn ($k) => isset($validFlip[(int) $k]),
                    ARRAY_FILTER_USE_KEY
                );
            }
            if ($ids === [] && ! ($validated['channel'] === 'email' && $fileEmailRecipients !== [])) {
                return redirect()
                    ->route('marketing.broadcast', $back)
                    ->withInput()
                    ->with('error', 'No valid recipients were found from your selection/file. Tip: if your file has Email/Policy/Mobile, leave Contact ID blank unless it is an exact CRM Contact ID.');
            }
            if ($missingCount > 0) {
                $request->session()->flash(
                    'warning',
                    $missingCount . ' uploaded/selected ID(s) were not found in CRM and were skipped.'
                );
            }
        }

        $clientType = trim((string) ($validated['client_type'] ?? 'all'));
        if ($clientType === '') {
            $clientType = 'all';
        }
        if ($ids !== []) {
            $ids = $crm->filterContactIdsByBroadcastClientType($ids, $clientType);
            if ($ids === [] && ! ($validated['channel'] === 'email' && $fileEmailRecipients !== [])) {
                return redirect()
                    ->route('marketing.broadcast', $back)
                    ->withInput()
                    ->with('error', 'No contacts match the selected client type filter (or all rows from the file were excluded).');
            }
        }

        if (count($ids) > $max) {
            return redirect()
                ->route('marketing.broadcast', $back)
                ->withInput()
                ->with('error', 'Too many recipients (' . count($ids) . '). Maximum per send is ' . $max . '.');
        }

        $skipRecentSends = $request->input('skip_recent_sends') === '1' || $request->input('skip_recent_sends') === 1;

        if ($validated['channel'] === 'email') {
            $attachment = null;
            if ($request->hasFile('email_attachment')) {
                $uploaded = $request->file('email_attachment');
                if ($uploaded && $uploaded->isValid()) {
                    $content = file_get_contents($uploaded->getRealPath());
                    if ($content === false) {
                        return redirect()
                            ->route('marketing.broadcast', $back)
                            ->withInput()
                            ->with('error', 'Could not read the selected attachment. Please re-upload and try again.');
                    }
                    $attachment = [
                        'name' => $uploaded->getClientOriginalName() ?: 'attachment',
                        'contentType' => $uploaded->getMimeType() ?: 'application/octet-stream',
                        'content' => $content,
                    ];
                }
            }

            $stats = $broadcast->sendMassEmail(
                $ids,
                $validated['subject'],
                $validated['body'],
                $attachment,
                $skipRecentSends,
                $emailOverrides,
                $fileEmailRecipients
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
                $warn = 'Some messages failed. Check logs and mail/Graph configuration.';
                if (! empty($stats['failure_summary'])) {
                    $warn .= ' Top error(s): ' . (string) $stats['failure_summary'];
                }
                $redirect->with('warning', $warn);
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
