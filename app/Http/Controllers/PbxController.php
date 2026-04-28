<?php

namespace App\Http\Controllers;

use App\Models\PbxCall;
use App\Models\PbxCallRecipient;
use App\Services\CrmService;
use App\Services\ErpClientService;
use App\Services\PbxConfigService;
use App\Services\PbxExtensionMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PbxController extends Controller
{
    /** @var array<string,string> */
    protected array $customerNameByPhoneCache = [];
    /** @var array<string,string> */
    protected array $recordingUrlByUniqueIdCache = [];

    public function __construct(
        protected PbxConfigService $pbxConfig,
        protected PbxExtensionMappingService $extensionMapping,
        protected CrmService $crm,
        protected ErpClientService $erpClients
    ) {}

    public function index(Request $request)
    {
        if ($this->pbxConfig->isConfigured() && ! $this->shouldPreferLocalLogs()) {
            return $this->indexFromVtiger($request);
        }

        return $this->indexFromLocal($request);
    }

    /**
     * Prefer local logs when they are fresher than vtiger logs.
     * This keeps PBX Manager useful when callback/connector sync lags.
     */
    protected function shouldPreferLocalLogs(): bool
    {
        try {
            // Deterministic real-time behavior:
            // once local webhook rows exist, use local list as source of truth.
            if (PbxCall::query()->exists()) {
                return true;
            }

            // Fallback to vtiger only when local has no rows.
            return false;
        } catch (\Throwable) {
            // Any vtiger/read issue should not hide local call history.
            return PbxCall::query()->exists();
        }
    }

    protected function indexFromVtiger(Request $request)
    {
        $durationCols = config('services.pbx.duration_columns', ['billduration', 'totalduration']);
        $durationExpr = implode(', ', array_map(fn ($c) => "NULLIF(p.{$c}, 0)", $durationCols));
        $durationRaw = $durationExpr ? "COALESCE({$durationExpr}, 0) as duration_sec" : '0 as duration_sec';

        $query = DB::connection('vtiger')
            ->table('vtiger_pbxmanager as p')
            ->leftJoin('vtiger_users as u', 'p.user', '=', 'u.id')
            ->select(
                'p.pbxmanagerid',
                'p.user as pbx_user',
                'p.direction',
                'p.callstatus as call_status',
                'p.customernumber as customer_number',
                'p.customer as customer_name',
                'p.recordingurl as recording_url',
                'p.sourceuuid',
                'p.starttime as start_time',
                DB::raw($durationRaw),
                DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as user_name")
            );

        if ($request->filled('list')) {
            if ($request->list === 'Completed Calls') {
                $query->where('p.callstatus', 'completed');
            } elseif ($request->list === 'No Response Calls') {
                $query->whereIn('p.callstatus', ['no-response', 'no-answer', 'busy']);
            } elseif ($request->list === 'Received Calls') {
                $claimedIds = PbxCallRecipient::where('call_source', 'vtiger')->pluck('call_id')->toArray();
                $query->whereIn('p.pbxmanagerid', $claimedIds ?: [0]);
            }
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('p.customernumber', 'like', $term)
                    ->orWhere('p.customer', 'like', $term)
                    ->orWhere('u.first_name', 'like', $term)
                    ->orWhere('u.last_name', 'like', $term);
            });
        }

        $query->orderByDesc('p.starttime');
        $total = $query->count();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $calls = new LengthAwarePaginator(
            $items->map(fn ($r) => $this->toCallDto($r, true)),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $this->mergeClaimedRecipients($calls, 'vtiger');

        return view('tools.pbx-manager', [
            'calls' => $calls,
            'currentList' => $request->get('list', ''),
            'pbxSource' => 'vtiger',
            'pbxCanCall' => $this->pbxConfig->isConfigured(),
            'defaultExtension' => config('services.pbx.default_extension', env('PBX_DEFAULT_EXTENSION', '')),
        ]);
    }

    protected function indexFromLocal(Request $request)
    {
        $query = PbxCall::query();

        if ($request->filled('list')) {
            if ($request->list === 'Completed Calls') {
                $query->where('call_status', 'completed');
            } elseif ($request->list === 'No Response Calls') {
                $query->whereIn('call_status', ['no-response', 'no-answer', 'busy']);
            } elseif ($request->list === 'Received Calls') {
                $claimedIds = PbxCallRecipient::where('call_source', 'local')->pluck('call_id')->toArray();
                $query->whereIn('id', $claimedIds ?: [0]);
            }
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('user_name', 'like', $term);
            });
        }

        $calls = $query->orderByDesc('start_time')->paginate(50)->withQueryString();
        $calls->setCollection(
            $calls->getCollection()->map(fn ($row) => $this->toCallDto($row, false))
        );

        $this->mergeClaimedRecipients($calls, 'local');

        return view('tools.pbx-manager', [
            'calls' => $calls,
            'currentList' => $request->get('list', ''),
            'pbxSource' => 'local',
            'pbxCanCall' => $this->pbxConfig->isConfigured(),
            'defaultExtension' => config('services.pbx.default_extension', env('PBX_DEFAULT_EXTENSION', '')),
        ]);
    }

    /**
     * Lightweight live feed for PBX table.
     * Returns recent local calls as JSON to avoid full page reload.
     */
    public function live(Request $request): JsonResponse
    {
        $query = PbxCall::query();

        if ($request->filled('list')) {
            if ($request->list === 'Completed Calls') {
                $query->where('call_status', 'completed');
            } elseif ($request->list === 'No Response Calls') {
                $query->whereIn('call_status', ['no-response', 'no-answer', 'busy']);
            } elseif ($request->list === 'Received Calls') {
                $claimedIds = PbxCallRecipient::where('call_source', 'local')->pluck('call_id')->toArray();
                $query->whereIn('id', $claimedIds ?: [0]);
            }
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('user_name', 'like', $term);
            });
        }

        $rows = $query->orderByDesc('start_time')->limit(50)->get();
        $callIds = $rows->pluck('id')->all();
        $claims = PbxCallRecipient::query()
            ->where('call_source', 'local')
            ->whereIn('call_id', $callIds ?: [0])
            ->get()
            ->keyBy('call_id');

        $items = $rows->map(function (PbxCall $row) use ($claims) {
            $dto = $this->toCallDto($row, false);
            $claim = $claims->get($row->id);
            if ($claim && trim((string) ($claim->received_by_user_name ?? '')) !== '') {
                $dto->user_name = trim((string) $claim->received_by_user_name);
                $dto->received_by_user_id = $claim->received_by_user_id;
            }
            $displayTimezone = (string) config('app.timezone', 'Africa/Nairobi');
            $displayStart = $dto->start_time ? $dto->start_time->copy()->timezone($displayTimezone) : null;

            return [
                'id' => $dto->id,
                'call_status' => (string) ($dto->call_status ?? 'unknown'),
                'direction' => (string) ($dto->direction ?? 'unknown'),
                'customer_number' => (string) ($dto->customer_number ?? ''),
                'reason_for_calling' => (string) ($dto->reason_for_calling ?? ''),
                'customer_name' => (string) ($dto->customer_name ?? ''),
                'user_name' => (string) ($dto->user_name ?? ''),
                'recording_url' => (string) ($dto->recording_url ?? ''),
                'recording_route' => (! empty($dto->id) && ! empty($dto->recording_url))
                    ? route('tools.pbx-manager.recording', ['pbxCall' => $dto->id])
                    : null,
                'duration_sec' => (int) ($dto->duration_sec ?? 0),
                'received_by_user_id' => $dto->received_by_user_id ?? null,
                'start_time' => $displayStart ? $displayStart->toIso8601String() : null,
                'start_time_label' => $displayStart ? $displayStart->format('d M Y, h:i A') : '—',
                'updated_at' => $row->updated_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'calls' => $items,
            'count' => $items->count(),
            'ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * Allow the logged-in user to claim they received a call.
     * Overrides PBX/vTiger user when incorrect or empty.
     */
    public function claim(Request $request)
    {
        $validated = $request->validate([
            'call_id' => 'required|integer|min:1',
            'source' => 'required|in:vtiger,local',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name ?? '';

        PbxCallRecipient::updateOrCreate(
            [
                'call_source' => $validated['source'],
                'call_id' => (int) $validated['call_id'],
            ],
            [
                'received_by_user_id' => $user->id,
                'received_by_user_name' => $fullName,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Call recorded as received by you.',
            'user_name' => $fullName,
        ]);
    }

    /**
     * Claim the most recent unclaimed call for the logged-in user (session-based).
     * Uses session to know who received the call — one-click for "I just answered the last call".
     */
    public function claimLatest(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name ?? '';
        $source = $this->pbxConfig->isConfigured() ? 'vtiger' : 'local';

        $call = null;
        if ($source === 'vtiger') {
            $claimedIds = PbxCallRecipient::where('call_source', 'vtiger')->pluck('call_id')->toArray();
            $query = DB::connection('vtiger')
                ->table('vtiger_pbxmanager')
                ->whereNotIn('pbxmanagerid', $claimedIds ?: [0])
                ->orderByDesc('starttime')
                ->select('pbxmanagerid')
                ->limit(1);
            $row = $query->first();
            if ($row) {
                $call = (object) ['id' => $row->pbxmanagerid, 'source' => 'vtiger'];
            }
        } else {
            $claimedIds = PbxCallRecipient::where('call_source', 'local')->pluck('call_id')->toArray();
            $row = PbxCall::whereNotIn('id', $claimedIds ?: [0])
                ->orderByDesc('start_time')
                ->select('id')
                ->first();
            if ($row) {
                $call = (object) ['id' => $row->id, 'source' => 'local'];
            }
        }

        if (! $call) {
            return response()->json([
                'success' => false,
                'message' => 'No unclaimed call found. All calls are already attributed.',
            ]);
        }

        PbxCallRecipient::updateOrCreate(
            ['call_source' => $call->source, 'call_id' => $call->id],
            ['received_by_user_id' => $user->id, 'received_by_user_name' => $fullName]
        );

        return response()->json([
            'success' => true,
            'message' => 'Most recent call recorded as received by you.',
            'user_name' => $fullName,
            'call_id' => $call->id,
        ]);
    }

    protected function toCallDto(object $row, bool $fromVtiger): object
    {
        $recordingUrl = $row->recording_url ?? null;
        $externalId = trim((string) ($row->external_id ?? ''));
        $isUndatedMonitorUrl = is_string($recordingUrl)
            && str_contains($recordingUrl, '/monitor/')
            && ! preg_match('#/monitor/\d{4}/\d{2}/\d{2}/#', $recordingUrl);
        if ($externalId !== '' && $isUndatedMonitorUrl) {
            $datedMonitorUrl = $this->resolveRecordingUrlFromCdrUniqueId($externalId);
            if (! empty($datedMonitorUrl)) {
                $recordingUrl = $datedMonitorUrl;
            }
        }
        $isLegacyRecordingEndpoint = is_string($recordingUrl) && str_contains($recordingUrl, '/recording?id=');
        if ($isLegacyRecordingEndpoint) {
            $legacyUniqueId = $this->extractUniqueIdFromRecordingUrl((string) $recordingUrl);
            if ($legacyUniqueId !== '') {
                $legacyResolvedUrl = $this->resolveRecordingUrlFromCdrUniqueId($legacyUniqueId);
                if (! empty($legacyResolvedUrl)) {
                    $recordingUrl = $legacyResolvedUrl;
                    $isLegacyRecordingEndpoint = false;
                }
            }
        }
        if ($externalId !== '' && ($recordingUrl === null || $recordingUrl === '' || $isLegacyRecordingEndpoint)) {
            $cdrResolvedUrl = $this->resolveRecordingUrlFromCdrUniqueId($externalId);
            if (! empty($cdrResolvedUrl)) {
                $recordingUrl = $cdrResolvedUrl;
            }
        }
        if ($externalId !== '' && is_string($recordingUrl) && str_contains($recordingUrl, '/recording?id=')) {
            $patternResolvedUrl = $this->resolveRecordingUrlByFilenamePattern(
                $externalId,
                trim((string) ($row->customer_number ?? '')),
                $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null
            );
            if (! empty($patternResolvedUrl)) {
                $recordingUrl = $patternResolvedUrl;
            }
        }
        if (! $recordingUrl && ! empty($row->sourceuuid) && $fromVtiger) {
            $recordingUrl = $this->pbxConfig->getRecordingUrl($row->sourceuuid);
        } elseif (! $recordingUrl && ! $fromVtiger && $externalId !== '') {
            $recordingUrl = $this->pbxConfig->getRecordingUrl($externalId);
        }
        if ($externalId !== '' && is_string($recordingUrl) && str_contains($recordingUrl, '/recording?id=')) {
            $cdrResolvedUrl = $this->resolveRecordingUrlFromCdrUniqueId($externalId);
            if (! empty($cdrResolvedUrl)) {
                $recordingUrl = $cdrResolvedUrl;
            } else {
                $patternResolvedUrl = $this->resolveRecordingUrlByFilenamePattern(
                    $externalId,
                    trim((string) ($row->customer_number ?? '')),
                    $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null
                );
                if (! empty($patternResolvedUrl)) {
                    $recordingUrl = $patternResolvedUrl;
                }
            }
        }
        if (! $recordingUrl && $externalId !== '') {
            $recordingUrl = $this->resolveRecordingUrlFromCdrUniqueId($externalId);
        }
        if (! $recordingUrl && $externalId !== '') {
            $recordingUrl = $this->resolveRecordingUrlByFilenamePattern(
                $externalId,
                trim((string) ($row->customer_number ?? '')),
                $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null
            );
        }

        $userName = trim($row->user_name ?? '') ?: null;
        if ($fromVtiger && isset($row->pbx_user)) {
            $userName = $this->extensionMapping->resolveUserName($userName, $row->pbx_user);
        } elseif ($userName && preg_match('/^\d{2,6}$/', $userName)) {
            $resolvedUser = $this->extensionMapping->resolveUserName(null, $userName);
            if ($resolvedUser) {
                $userName = $resolvedUser;
            }
        }
        if (! $userName && isset($row->pbx_user)) {
            $pbxUser = trim((string) $row->pbx_user);
            if ($pbxUser !== '') {
                $resolvedUser = $this->extensionMapping->resolveUserName(null, $pbxUser);
                $userName = $resolvedUser ?: ('Ext ' . $pbxUser);
            }
        } elseif ($userName && preg_match('/^\d{2,6}$/', $userName)) {
            $userName = 'Ext ' . $userName;
        }

        $customerNumber = trim((string) ($row->customer_number ?? ''));
        $customerName = trim((string) ($row->customer_name ?? ''));
        if (($customerName === '' || preg_replace('/\D/', '', $customerName) === preg_replace('/\D/', '', $customerNumber)) && $customerNumber !== '') {
            $resolvedCustomerName = $this->resolveCustomerNameByPhone($customerNumber, false);
            if ($resolvedCustomerName !== '') {
                $customerName = $resolvedCustomerName;
            }
        }

        $durationSec = (int) ($row->duration_sec ?? 0);
        $status = strtolower(trim((string) ($row->call_status ?? '')));
        $startAt = $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null;
        if ($durationSec <= 0 && in_array($status, ['received', 'completed'], true) && $startAt) {
            $updatedAt = (isset($row->updated_at) && $row->updated_at)
                ? \Carbon\Carbon::parse($row->updated_at)
                : null;
            if ($updatedAt && $updatedAt->greaterThan($startAt)) {
                $durationSec = max(0, $startAt->diffInSeconds($updatedAt));
            }
        }

        return (object) [
            'id' => $row->pbxmanagerid ?? $row->id ?? null,
            'call_status' => $row->call_status ?? null,
            'direction' => $row->direction ?? null,
            'customer_number' => $customerNumber !== '' ? $customerNumber : null,
            'reason_for_calling' => null,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'user_name' => $userName,
            'recording_url' => $recordingUrl,
            'recording_path' => null,
            'duration_sec' => $durationSec,
            'start_time' => $startAt,
            'from_vtiger' => $fromVtiger,
        ];
    }

    protected function resolveRecordingUrlFromCdrUniqueId(string $uniqueId): ?string
    {
        $uniqueId = trim($uniqueId);
        if ($uniqueId === '') {
            return null;
        }
        if (array_key_exists($uniqueId, $this->recordingUrlByUniqueIdCache)) {
            $cached = $this->recordingUrlByUniqueIdCache[$uniqueId];
            return $cached !== '' ? $cached : null;
        }

        $monitorBase = rtrim((string) config('services.pbx.monitor_public_base_url', ''), '/');
        if ($monitorBase === '') {
            $this->recordingUrlByUniqueIdCache[$uniqueId] = '';
            return null;
        }

        try {
            $cdr = DB::connection('vtiger')
                ->table('asteriskcdrdb.cdr')
                ->select('recordingfile', 'calldate')
                ->where('uniqueid', $uniqueId)
                ->whereNotNull('recordingfile')
                ->where('recordingfile', '<>', '')
                ->orderByDesc('calldate')
                ->first();
            $recordingFile = trim((string) ($cdr->recordingfile ?? ''));
            if ($recordingFile !== '') {
                $paths = [];
                if (! empty($cdr->calldate)) {
                    try {
                        $dt = \Carbon\Carbon::parse((string) $cdr->calldate)->timezone(config('app.timezone', 'Africa/Nairobi'));
                        $paths[] = $dt->format('Y/m/d') . '/' . ltrim($recordingFile, '/');
                    } catch (\Throwable) {
                        // ignore calldate parse issues
                    }
                }
                $paths[] = ltrim($recordingFile, '/');

                foreach ($paths as $path) {
                    $url = $monitorBase . '/' . $path;
                    try {
                        $head = Http::timeout(3)->withOptions(['verify' => false])->head($url);
                        if ($head->successful()) {
                            $this->recordingUrlByUniqueIdCache[$uniqueId] = $url;
                            return $url;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        } catch (\Throwable) {
            // Keep call list resilient even if CDR DB is temporarily unreachable.
        }

        $this->recordingUrlByUniqueIdCache[$uniqueId] = '';
        return null;
    }

    protected function resolveRecordingUrlByFilenamePattern(string $uniqueId, string $customerNumber, ?\Carbon\Carbon $startAt): ?string
    {
        $uniqueId = trim($uniqueId);
        if ($uniqueId === '') {
            return null;
        }
        $monitorBase = rtrim((string) config('services.pbx.monitor_public_base_url', ''), '/');
        if ($monitorBase === '') {
            return null;
        }

        $rawNumber = preg_replace('/\D/', '', $customerNumber);
        if ($rawNumber === '') {
            $rawNumber = 'unknown';
        }
        $epochPart = explode('.', $uniqueId)[0] ?? '';
        if (ctype_digit((string) $epochPart)) {
            $ts = \Carbon\Carbon::createFromTimestamp((int) $epochPart)->timezone(config('app.timezone', 'Africa/Nairobi'));
        } else {
            $ts = ($startAt ?? now())->copy()->timezone(config('app.timezone', 'Africa/Nairobi'));
        }
        $datePart = $ts->format('Ymd');
        $timePart = $ts->format('His');
        $dateTimePart = $datePart . '-' . $timePart;

        $candidates = [
            "{$monitorBase}/rg-620-{$rawNumber}-{$dateTimePart}-{$uniqueId}.wav",
            "{$monitorBase}/in-{$dateTimePart}-{$rawNumber}-296.wav",
        ];

        foreach ($candidates as $url) {
            try {
                $head = Http::timeout(3)
                    ->withOptions(['verify' => false])
                    ->head($url);
                if ($head->successful()) {
                    return $url;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function resolveCustomerNameByPhone(string $phone, bool $allowErpFallback = true): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (isset($this->customerNameByPhoneCache[$digits])) {
            return $this->customerNameByPhoneCache[$digits];
        }

        $variants = array_values(array_unique(array_filter([
            $digits,
            ltrim($digits, '0'),
            str_starts_with($digits, '254') ? ('0' . substr($digits, 3)) : null,
            str_starts_with($digits, '0') && strlen($digits) >= 10 ? ('254' . substr($digits, 1)) : null,
            str_starts_with($digits, '00254') ? substr($digits, 2) : null,
        ])));

        foreach ($variants as $candidate) {
            $contact = $this->crm->findContactByPhoneOrEmail($candidate, null);
            if ($contact) {
                $name = trim((string) (($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')));
                if ($name !== '') {
                    $this->customerNameByPhoneCache[$digits] = $name;
                    return $name;
                }
            }
        }

        // ERP fallback can be expensive; use it during webhook ingest (write-time),
        // not during list rendering.
        if ($allowErpFallback) {
            $erpName = $this->resolveErpCustomerNameByPhoneVariants($variants);
            if ($erpName !== '') {
                $this->customerNameByPhoneCache[$digits] = $erpName;
                return $erpName;
            }
        }

        $this->customerNameByPhoneCache[$digits] = '';
        return '';
    }

    /**
     * @param  array<int,string>  $variants
     */
    protected function resolveErpCustomerNameByPhoneVariants(array $variants): string
    {
        $variantDigits = array_values(array_unique(array_filter(
            array_map(fn ($v) => preg_replace('/\D/', '', (string) $v), $variants)
        )));
        if (empty($variantDigits)) {
            return '';
        }

        foreach ($variants as $candidate) {
            try {
                $result = $this->erpClients->searchClients((string) $candidate, 10);
                $rows = $result['data'] ?? [];
                if (! is_array($rows) || empty($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $name = trim((string) (
                        $row['name']
                        ?? $row['client_name']
                        ?? $row['life_assur']
                        ?? $row['life_assured']
                        ?? ''
                    ));
                    if ($name === '') {
                        continue;
                    }

                    $rowPhoneCandidates = [
                        (string) ($row['phone_no'] ?? ''),
                        (string) ($row['mobile'] ?? ''),
                        (string) ($row['phone'] ?? ''),
                        (string) ($row['client_contact'] ?? ''),
                    ];
                    foreach ($rowPhoneCandidates as $rowPhone) {
                        $rowDigits = preg_replace('/\D/', '', $rowPhone);
                        if ($rowDigits === '') {
                            continue;
                        }

                        if (in_array($rowDigits, $variantDigits, true)
                            || in_array(ltrim($rowDigits, '0'), array_map(fn ($v) => ltrim($v, '0'), $variantDigits), true)
                            || in_array(substr($rowDigits, -9), array_map(fn ($v) => substr($v, -9), $variantDigits), true)
                        ) {
                            return $name;
                        }
                    }
                }

                // If there is no strict phone-field match but search returned a clear candidate,
                // prefer first non-empty name so PBX table is still useful for agents.
                $first = $rows[0] ?? null;
                if (is_array($first)) {
                    $firstName = trim((string) (
                        $first['name']
                        ?? $first['client_name']
                        ?? $first['life_assur']
                        ?? $first['life_assured']
                        ?? ''
                    ));
                    if ($firstName !== '') {
                        return $firstName;
                    }
                }
            } catch (\Throwable) {
                // Keep PBX list fast/resilient even when ERP is unavailable.
                continue;
            }
        }

        return '';
    }

    public function fetch(Request $request)
    {
        if ($this->pbxConfig->isConfigured()) {
            return redirect()->route('tools.pbx-manager')
                ->with('success', 'Calls are loaded directly from the old CRM. No fetch needed.');
        }

        $apiUrl = config('services.pbx.api_url');
        $apiKey = config('services.pbx.api_key');

        if (! $apiUrl) {
            return redirect()->route('tools.pbx-manager')
                ->with('error', 'PBX not configured. Set up vtiger_pbxmanager_gateway in the old CRM, or PBX_API_URL in .env');
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => false])
                ->withHeaders($apiKey ? ['Authorization' => 'Bearer ' . $apiKey] : [])
                ->get($apiUrl);

            if (! $response->successful()) {
                return redirect()->route('tools.pbx-manager')
                    ->with('error', 'Failed to fetch calls: ' . $response->status());
            }

            $data = $response->json();
            $calls = $data['calls'] ?? $data['data'] ?? (is_array($data) ? $data : []);

            $count = 0;
            foreach ($calls as $call) {
                $count += $this->upsertCall($call);
            }

            return redirect()->route('tools.pbx-manager')
                ->with('success', "Fetched {$count} call(s).");
        } catch (\Throwable $e) {
            return redirect()->route('tools.pbx-manager')
                ->with('error', 'Fetch failed: ' . $e->getMessage());
        }
    }

    /**
     * Receive incoming call events directly from PBX webapp/connector.
     * This keeps call logs flowing even when the old CRM connector is down.
     */
    public function incomingWebhook(Request $request)
    {
        $secret = trim((string) ($request->input('secret')
            ?? $request->input('vtigersecretkey')
            ?? $request->header('X-Vtiger-Secret')
            ?? ''));
        $expectedSecret = trim((string) ($this->pbxConfig->getSecretKey() ?? ''));
        $isLegacyCallback = $request->is('modules/PBXManager/callbacks/PBXManager.php');
        $trustedIps = array_values(array_unique(array_filter(array_merge(
            ['127.0.0.1', '::1', '10.1.1.86', '10.1.1.65'],
            config('services.pbx.trusted_callback_ips', [])
        ))));
        $requestIp = (string) ($request->ip() ?? '');
        $isTrustedIp = in_array($requestIp, $trustedIps, true);
        if (! $isLegacyCallback && ! $isTrustedIp && $expectedSecret !== '' && ! hash_equals($expectedSecret, $secret)) {
            return $this->pbxWebhookResponse($request, false, 'Invalid PBX secret.', 401);
        }

        $event = strtolower(trim((string) ($request->input('event') ?? $request->input('type') ?? 'IncomingCall')));
        $sourceUuid = trim((string) ($request->input('sourceuuid')
            ?? $request->input('callid')
            ?? $request->input('uniqueid')
            ?? $request->input('uuid')
            ?? ''));
        $status = strtolower(trim((string) ($request->input('callstatus')
            ?? $request->input('status')
            ?? ($event === 'incomingcall' ? 'ringing' : 'completed'))));
        $direction = strtolower(trim((string) ($request->input('direction') ?? 'inbound')));
        $customerNumber = trim((string) ($request->input('customernumber')
            ?? $request->input('caller')
            ?? $request->input('from')
            ?? $request->input('from_number')
            ?? $request->input('number')
            ?? ''));
        $customerName = trim((string) ($request->input('customer')
            ?? $request->input('customer_name')
            ?? $request->input('caller_name')
            ?? ''));
        $recordingUrl = trim((string) ($request->input('recordingurl')
            ?? $request->input('recording_url')
            ?? $request->input('recording')
            ?? ''));
        $recordingFile = trim((string) ($request->input('recordingfile')
            ?? $request->input('recording_file')
            ?? $request->input('callfilename')
            ?? $request->input('cdr_recordingfile')
            ?? ''));
        $user = trim((string) ($request->input('user')
            ?? $request->input('agent')
            ?? $request->input('extension')
            ?? $request->input('to')
            ?? ''));
        $startTime = $this->parsePbxDateTime($request->input('starttime') ?? $request->input('start_time'));
        $endTime = $this->parsePbxDateTime($request->input('endtime') ?? $request->input('end_time'));
        $duration = (int) ($request->input('totalduration')
            ?? $request->input('billduration')
            ?? $request->input('duration')
            ?? 0);

        if ($startTime === null) {
            $startTime = now();
        }
        if ($endTime === null && $duration > 0) {
            $endTime = $startTime->copy()->addSeconds($duration);
        }
        // Do not auto-mark as received from extension/user alone.
        // A call is received only when PBX explicitly reports answered/completed
        // or when user manually claims it in PBX Manager.
        if ($customerName === '' && $customerNumber !== '') {
            $resolvedCustomerName = $this->resolveCustomerNameByPhone($customerNumber, true);
            $customerName = $resolvedCustomerName !== '' ? $resolvedCustomerName : $customerNumber;
        }
        if ($recordingUrl === '' && $recordingFile !== '') {
            $monitorBase = rtrim((string) config('services.pbx.monitor_public_base_url', ''), '/');
            if ($monitorBase !== '') {
                $recordingUrl = $monitorBase . '/' . ltrim($recordingFile, '/');
            }
        }
        if ($recordingUrl === '' && $sourceUuid !== '') {
            // Backward-compatible fallback: old connector usually served recordings by call UUID.
            $recordingUrl = $this->pbxConfig->getRecordingUrl($sourceUuid);
        }

        $payload = [
            'direction' => $direction ?: 'inbound',
            'callstatus' => $status ?: 'unknown',
            'starttime' => $startTime?->format('Y-m-d H:i:s'),
            'endtime' => $endTime?->format('Y-m-d H:i:s'),
            'totalduration' => max(0, $duration),
            'billduration' => max(0, (int) ($request->input('billduration') ?? $duration)),
            'recordingurl' => $recordingUrl ?: null,
            'sourceuuid' => $sourceUuid ?: null,
            'gateway' => 'PBXManager',
            'customer' => $customerName ?: null,
            'user' => $user ?: null,
            'customernumber' => $customerNumber ?: null,
            'customertype' => (string) ($request->input('customertype') ?? 'Contact'),
            'incominglinename' => (string) ($request->input('incominglinename') ?? $request->input('to') ?? ''),
        ];

        $savedToVtiger = false;
        $savedToLocal = false;
        $vtigerError = null;
        $localError = null;

        try {
            if ($sourceUuid !== '') {
                $existing = DB::connection('vtiger')
                    ->table('vtiger_pbxmanager')
                    ->where('sourceuuid', $sourceUuid)
                    ->orderByDesc('pbxmanagerid')
                    ->first();
                if ($existing) {
                    DB::connection('vtiger')
                        ->table('vtiger_pbxmanager')
                        ->where('pbxmanagerid', $existing->pbxmanagerid)
                        ->update($payload);
                } else {
                    DB::connection('vtiger')->table('vtiger_pbxmanager')->insert($payload);
                }
            } else {
                DB::connection('vtiger')->table('vtiger_pbxmanager')->insert($payload);
            }
            $savedToVtiger = true;
        } catch (\Throwable $e) {
            $vtigerError = $e->getMessage();
            Log::warning('PBX incoming webhook vtiger persist failed', [
                'event' => $event,
                'source_uuid' => $sourceUuid,
                'error' => $vtigerError,
            ]);
        }

        try {
            // Keep a local fallback copy for troubleshooting and resilience.
            PbxCall::updateOrCreate(
                ['external_id' => $sourceUuid !== '' ? $sourceUuid : sha1($customerNumber . '|' . ($startTime?->timestamp ?? time()))],
                [
                    'call_status' => $status ?: 'unknown',
                    'direction' => $direction ?: 'inbound',
                    'customer_number' => $customerNumber,
                    'customer_name' => $customerName,
                    'user_name' => $user,
                    'recording_url' => $recordingUrl ?: null,
                    'duration_sec' => max(0, $duration),
                    'start_time' => $startTime,
                ]
            );
            $savedToLocal = true;
        } catch (\Throwable $e) {
            $localError = $e->getMessage();
            Log::warning('PBX incoming webhook local persist failed', [
                'event' => $event,
                'source_uuid' => $sourceUuid,
                'error' => $localError,
            ]);
        }

        if (! $savedToVtiger && ! $savedToLocal) {
            return $this->pbxWebhookResponse($request, false, 'Could not persist PBX event.', 500);
        }

        if (config('services.pbx.debug')) {
            Log::info('PBX incoming webhook processed', [
                'event' => $event,
                'status' => $status,
                'source_uuid' => $sourceUuid,
                'number' => $customerNumber,
                'saved_to_vtiger' => $savedToVtiger,
                'saved_to_local' => $savedToLocal,
                'vtiger_error' => $vtigerError,
                'local_error' => $localError,
            ]);
        }

        return $this->pbxWebhookResponse($request, true, 'PBX event received.');
    }

    public function recordingVtiger(Request $request, int $id)
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_pbxmanager')
            ->where('pbxmanagerid', $id)
            ->first();

        if (! $row) {
            abort(404);
        }

        $recordingUrl = null;
        if (! empty($row->recordingurl)) {
            $recordingUrl = $row->recordingurl;
        } elseif (! empty($row->sourceuuid)) {
            $recordingUrl = $this->pbxConfig->getRecordingUrl($row->sourceuuid);
        }
        if ($recordingUrl && str_contains((string) $recordingUrl, '/recording?id=') && ! empty($row->sourceuuid)) {
            $patternResolvedUrl = $this->resolveRecordingUrlByFilenamePattern(
                (string) $row->sourceuuid,
                trim((string) ($row->customernumber ?? '')),
                ! empty($row->starttime) ? \Carbon\Carbon::parse($row->starttime) : null
            );
            if (! empty($patternResolvedUrl)) {
                $recordingUrl = $patternResolvedUrl;
            }
        }

        if (! $recordingUrl) {
            abort(404);
        }

        return $this->streamRecordingFromUrl($recordingUrl, $request->boolean('download'));
    }

    public function recording(Request $request, PbxCall $pbxCall)
    {
        if (! empty($pbxCall->recording_url)) {
            $recordingUrl = (string) $pbxCall->recording_url;
            if (str_contains($recordingUrl, '/recording?id=')) {
                $patternResolvedUrl = $this->resolveRecordingUrlByFilenamePattern(
                    (string) ($pbxCall->external_id ?? ''),
                    trim((string) ($pbxCall->customer_number ?? '')),
                    $pbxCall->start_time ? \Carbon\Carbon::parse($pbxCall->start_time) : null
                );
                if (! empty($patternResolvedUrl)) {
                    $recordingUrl = $patternResolvedUrl;
                }
            }
            return $this->streamRecordingFromUrl($recordingUrl, $request->boolean('download'));
        }

        // Backward-compatible fallback: many local rows store only external_id/source UUID.
        if (! empty($pbxCall->external_id)) {
            $fallbackUrl = $this->pbxConfig->getRecordingUrl((string) $pbxCall->external_id);
            if ($fallbackUrl !== '') {
                return $this->streamRecordingFromUrl($fallbackUrl, $request->boolean('download'));
            }
        }

        if (! $pbxCall->hasRecording()) {
            abort(404);
        }

        $path = $pbxCall->recording_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, basename($path), [
            'Content-Type' => 'audio/mpeg',
        ]);
    }

    /**
     * Initiate an outbound call via the PBX.
     * Proxies the request to the PBX webapp (makeCall/dial API).
     */
    public function makeCall(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'extension' => 'required|string|max:20',
        ]);

        $number = preg_replace('/\D/', '', $validated['number']);
        $extension = trim($validated['extension']);

        if (strlen($number) < 5) {
            return response()->json(['success' => false, 'message' => 'Invalid phone number.'], 422);
        }

        // Kenya: 0712345678 (9 digits starting with 7) -> 254712345678
        if (config('services.pbx.number_add_prefix', true)) {
            $countryCode = config('services.pbx.number_country_code', '254');
            if (strlen($number) === 9 && $number[0] === '7') {
                $number = $countryCode . $number;
            } elseif (strlen($number) === 10 && $number[0] === '0') {
                $number = $countryCode . substr($number, 1);
            }
        }

        $baseUrl = $this->pbxConfig->getMakeCallBaseUrl();
        $customUrl = $this->pbxConfig->getMakeCallUrl();
        if (! $baseUrl && ! $customUrl) {
            return response()->json(['success' => false, 'message' => 'PBX not configured. Set webappurl in vtiger_pbxmanager_gateway, or PBX_WEBAPP_URL / PBX_MAKE_CALL_URL in .env'], 503);
        }

        $context = $this->pbxConfig->getOutboundContext() ?: 'vtiger_outbound';
        $trunk = $this->pbxConfig->getOutboundTrunk() ?: 'default';
        $secretKey = $this->pbxConfig->getSecretKey();

        // Vtiger Asterisk Connector: callTo, fromExtension, vtigersecretkey
        $payload = [
            'number' => $number,
            'phoneNumber' => $number,
            'to' => $number,
            'destination' => $number,
            'callTo' => $number,
            'user' => $extension,
            'extension' => $extension,
            'from' => $extension,
            'fromExtension' => $extension,
            'from_extension' => $extension,
            'context' => $context,
            'trunk' => $trunk,
        ];
        if ($secretKey) {
            $payload['secret'] = $secretKey;
            $payload['vtigersecretkey'] = $secretKey;
        }

        // Vtiger PBXManager connector uses: /makecall?event=OutgoingCall&secret=&from=&to=&context=&record=
        // Params in query string; POST with empty body (from modules/PBXManager/connectors/PBXManager.php)
        $base = $baseUrl ? rtrim($baseUrl, '/') : (preg_match('#^https?://[^/]+#', $customUrl ?? '', $m) ? $m[0] : '');
        $record = '';
        $vtigerMakecallUrl = $base . '/makecall?' . http_build_query([
            'event' => 'OutgoingCall',
            'secret' => $secretKey ?: '',
            'from' => $extension,
            'to' => $number,
            'context' => $context,
            'record' => $record,
        ]);

        $endpoints = [];
        $endpoints[] = ['method' => 'post', 'url' => $vtigerMakecallUrl, 'form' => false, 'empty_body' => true];
        $endpoints[] = ['method' => 'get', 'url' => $vtigerMakecallUrl, 'form' => false, 'empty_body' => true];
        if ($customUrl) {
            $endpoints[] = ['method' => 'post', 'url' => $customUrl, 'form' => true];
            $endpoints[] = ['method' => 'post', 'url' => $customUrl, 'form' => false];
        }
        if ($baseUrl) {
            foreach (['/makecall', '/makeCall', '/outbound', '/dial'] as $path) {
                $pathUrl = rtrim($baseUrl, '/') . $path;
                $endpoints[] = ['method' => 'post', 'url' => $pathUrl, 'form' => true];
                $endpoints[] = ['method' => 'post', 'url' => $pathUrl, 'form' => false];
            }
        }
        $lastError = null;

        foreach ($endpoints as $ep) {
            $url = $ep['url'];

            try {
                if (($ep['method'] ?? 'post') === 'get') {
                    $response = Http::timeout(10)
                        ->withOptions(['verify' => false])
                        ->withHeaders($secretKey ? ['X-Vtiger-Secret' => $secretKey] : [])
                        ->get($url, ($ep['empty_body'] ?? false) ? [] : $payload);
                } else {
                    $headers = array_filter([
                        'X-Vtiger-Secret' => $secretKey,
                        'Accept' => 'application/json',
                    ]);
                    $requestPayload = ($ep['empty_body'] ?? false) ? [] : $payload;
                    if ($ep['form'] ?? false) {
                        $response = Http::timeout(10)->withOptions(['verify' => false])->withHeaders($headers)->asForm()->post($url, $requestPayload);
                    } else {
                        $headers['Content-Type'] = 'application/json';
                        $response = Http::timeout(10)->withOptions(['verify' => false])->withHeaders($headers)->post($url, $requestPayload);
                    }
                }

                if (config('services.pbx.debug')) {
                    Log::debug('PBX makeCall attempt', ['url' => $url, 'status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
                }
                if ($response->successful()) {
                    $body = $response->json();
                    $rawBody = strtolower(Str::limit($response->body(), 500));
                    $pbxOk = true;
                    $pbxError = null;
                    if ($body && is_array($body)) {
                        $pbxSuccess = $body['success'] ?? $body['status'] ?? $body['result'] ?? true;
                        $pbxError = $body['error'] ?? $body['message'] ?? $body['reason'] ?? null;
                        $pbxOk = ($pbxSuccess === true || $pbxSuccess === 'success' || $pbxSuccess === 'ok' || $pbxSuccess === 1);
                        if ($pbxError && (stripos((string) $pbxError, 'fail') !== false || stripos((string) $pbxError, 'error') !== false)) {
                            $pbxOk = false;
                        }
                    }
                    if (preg_match('/\b(error|exception|connection refused|failed|failure|authentication failure)\b/i', $rawBody)) {
                        $pbxOk = false;
                    }
                    if (! $pbxOk) {
                        $lastError = $pbxError ?: trim($response->body()) ?: Str::limit($response->body(), 200);
                        continue;
                    }
                    if (config('services.pbx.debug')) {
                        Log::info('PBX makeCall success', [
                            'url' => $url,
                            'number' => $number,
                            'extension' => $extension,
                            'context' => $context,
                            'trunk' => $trunk,
                            'response' => $body,
                        ]);
                    }
                    $json = [
                        'success' => true,
                        'message' => $body['message'] ?? 'Call initiated. Your phone should ring shortly.',
                    ];
                    if (config('services.pbx.debug')) {
                        $json['debug'] = [
                            'url' => $url,
                            'number_sent' => $number,
                            'extension_sent' => $extension,
                            'context' => $context,
                            'trunk' => $trunk,
                            'hint' => 'If phone did not ring, check Vtiger Asterisk Connector logs and Asterisk CLI (asterisk -rvvv).',
                        ];
                    }
                    return response()->json($json);
                }
                $lastError = $response->status() . ': ' . Str::limit($response->body(), 200);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = 'Connection failed: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        $amiResult = $this->originateViaAmi($extension, $number, $context);
        if (($amiResult['success'] ?? false) === true) {
            return response()->json([
                'success' => true,
                'message' => 'Call initiated via Asterisk AMI. Your phone should ring shortly.',
            ]);
        }
        if (! empty($amiResult['message'])) {
            $lastError = trim(($lastError ? ($lastError . ' | ') : '') . $amiResult['message']);
        }

        $message = 'Could not reach PBX or call failed.';
        if (stripos($lastError ?? '', 'Error') !== false || stripos($lastError ?? '', 'Authentication') !== false) {
            $message = 'PBX returned error. Check: extension exists in CRM user profile (phone_crm_extension), secret key matches connector, Asterisk is running.';
        } elseif ($lastError) {
            $message .= ' ' . Str::limit($lastError, 150);
        }
        Log::warning('PBX makeCall failed', [
            'number' => $number,
            'extension' => $extension,
            'base_url' => $baseUrl ?? $customUrl,
            'last_error' => $lastError,
        ]);
        return response()->json([
            'success' => false,
            'message' => $message,
            'detail' => $lastError,
        ], 502);
    }

    /**
     * Direct AMI originate fallback for Issabel/Asterisk.
     * Useful when legacy Vtiger makeCall endpoint is unavailable.
     *
     * @return array{success: bool, message?: string}
     */
    protected function originateViaAmi(string $extension, string $number, string $context): array
    {
        $host = trim((string) env('PBX_AMI_HOST', ''));
        $port = (int) env('PBX_AMI_PORT', 5038);
        $username = trim((string) env('PBX_AMI_USERNAME', ''));
        $secret = trim((string) env('PBX_AMI_SECRET', ''));
        $dialContext = trim((string) env('PBX_AMI_CONTEXT', $context !== '' ? $context : 'from-internal'));
        $timeout = max(3, (int) env('PBX_AMI_TIMEOUT', 8));

        if ($host === '' || $username === '' || $secret === '') {
            return ['success' => false, 'message' => 'AMI fallback not configured (PBX_AMI_HOST/USERNAME/SECRET).'];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $socket) {
            return ['success' => false, 'message' => "AMI connect failed: {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = static function ($sock): string {
            $buffer = '';
            while (! feof($sock)) {
                $line = fgets($sock, 4096);
                if ($line === false) {
                    break;
                }
                $buffer .= $line;
                if (rtrim($line) === '') {
                    break;
                }
            }
            return $buffer;
        };

        $writeAction = static function ($sock, array $fields): void {
            $payload = '';
            foreach ($fields as $k => $v) {
                $payload .= $k . ': ' . $v . "\r\n";
            }
            $payload .= "\r\n";
            fwrite($sock, $payload);
        };

        try {
            $readResponse($socket); // banner
            $writeAction($socket, [
                'Action' => 'Login',
                'Username' => $username,
                'Secret' => $secret,
                'Events' => 'off',
            ]);
            $login = $readResponse($socket);
            if (stripos($login, 'Success') === false) {
                fclose($socket);
                return ['success' => false, 'message' => 'AMI login failed.'];
            }

            $channels = [
                "Local/{$extension}@{$dialContext}",
                "PJSIP/{$extension}",
                "SIP/{$extension}",
                "IAX2/{$extension}",
            ];
            $lastOriginate = '';
            foreach ($channels as $channel) {
                $writeAction($socket, [
                    'Action' => 'Originate',
                    'Channel' => $channel,
                    'Exten' => $number,
                    'Context' => $dialContext,
                    'Priority' => '1',
                    'CallerID' => "CRM <{$number}>",
                    'Async' => 'true',
                    'Timeout' => '30000',
                ]);
                $originate = $readResponse($socket);
                $lastOriginate = $originate;
                if (stripos($originate, 'Success') !== false) {
                    Log::info('PBX AMI originate queued', [
                        'channel' => $channel,
                        'context' => $dialContext,
                        'extension' => $extension,
                        'number' => $number,
                        'response' => trim(preg_replace('/\s+/', ' ', $originate) ?? ''),
                    ]);
                    $writeAction($socket, ['Action' => 'Logoff']);
                    fclose($socket);
                    return ['success' => true];
                }
            }

            $writeAction($socket, ['Action' => 'Logoff']);
            fclose($socket);
            Log::warning('PBX AMI originate failed', [
                'context' => $dialContext,
                'extension' => $extension,
                'number' => $number,
                'response' => trim(preg_replace('/\s+/', ' ', $lastOriginate) ?? ''),
            ]);
            return ['success' => false, 'message' => 'AMI originate failed: ' . trim((string) preg_replace('/\s+/', ' ', $lastOriginate))];
        } catch (\Throwable $e) {
            @fclose($socket);
            return ['success' => false, 'message' => 'AMI exception: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch recording from external URL and stream to browser.
     * Proxies through Laravel to avoid CORS and mixed-content issues.
     */
    protected function streamRecordingFromUrl(string $url, bool $download = false)
    {
        $legacyUniqueId = $this->extractUniqueIdFromRecordingUrl($url);
        if ($legacyUniqueId !== '') {
            $cdrResolvedUrl = $this->resolveRecordingUrlFromCdrUniqueId($legacyUniqueId);
            if (! empty($cdrResolvedUrl)) {
                $url = $cdrResolvedUrl;
            }
        }

        $candidates = $this->buildRecordingUrlCandidates($url);
        $secretKey = $this->pbxConfig->getSecretKey();
        $headers = $secretKey ? ['X-Vtiger-Secret' => $secretKey] : [];

        foreach ($candidates as $candidateUrl) {
            try {
                $response = Http::timeout(30)
                    ->withOptions(['verify' => false])
                    ->withHeaders($headers)
                    ->get($candidateUrl);

                if (! $response->successful()) {
                    continue;
                }

                $contentType = $response->header('Content-Type') ?: 'audio/mpeg';
                $filename = basename(parse_url($candidateUrl, PHP_URL_PATH) ?: 'recording.mp3');
                if ($filename === '' || $filename === 'recording') {
                    $filename = 'recording.mp3';
                }
                $contentDisposition = $download
                    ? "attachment; filename=\"{$filename}\""
                    : ($response->header('Content-Disposition') ?: "inline; filename=\"{$filename}\"");

                return response($response->body(), 200, [
                    'Content-Type' => $contentType,
                    'Content-Length' => strlen($response->body()),
                    'Content-Disposition' => $contentDisposition,
                    'Accept-Ranges' => 'bytes',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            } catch (\Throwable) {
                continue;
            }
        }

        // Last chance: derive monitor-file URL from local call metadata by uniqueid.
        if ($legacyUniqueId !== '') {
            $local = PbxCall::query()
                ->where('external_id', $legacyUniqueId)
                ->orderByDesc('id')
                ->first(['external_id', 'customer_number', 'start_time']);
            if ($local) {
                $derived = $this->resolveRecordingUrlByFilenamePattern(
                    (string) $local->external_id,
                    (string) ($local->customer_number ?? ''),
                    $local->start_time ? \Carbon\Carbon::parse($local->start_time) : null
                );
                if (! empty($derived)) {
                    return redirect()->away($derived);
                }
            }
        }

        abort(404, 'Recording not available yet for this call.');
    }

    /**
     * Build a resilient set of PBX recording URL variants.
     * Some PBX setups expose recordings on 8383, others on 80/443.
     *
     * @return array<int,string>
     */
    protected function buildRecordingUrlCandidates(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [];
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return [$url];
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'];
        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';

        $variants = [];
        $variants[] = $url;

        // PBX monitor files are often served under dated folders:
        // /monitor/YYYY/MM/DD/<recordingfile>.wav
        // while some rows store /monitor/<recordingfile>.wav.
        if (preg_match('#^(https?://[^/]+/monitor)/([^/?]+)$#i', $url, $m)) {
            $monitorBase = $m[1];
            $fileName = $m[2];
            if (preg_match('/-(\d{8})-\d{6}-[\d.]+\.wav$/i', $fileName, $dm)) {
                $yyyymmdd = $dm[1];
                $yyyy = substr($yyyymmdd, 0, 4);
                $mm = substr($yyyymmdd, 4, 2);
                $dd = substr($yyyymmdd, 6, 2);
                $variants[] = "{$monitorBase}/{$yyyy}/{$mm}/{$dd}/{$fileName}";
            }
        }

        // Same host/path/query on default ports.
        $variants[] = "{$scheme}://{$host}{$path}{$query}";
        $variants[] = "https://{$host}{$path}{$query}";
        $variants[] = "http://{$host}{$path}{$query}";

        // Keep explicit non-default port variant if present.
        if ($port !== null) {
            $variants[] = "{$scheme}://{$host}:{$port}{$path}{$query}";
            $variants[] = "https://{$host}:{$port}{$path}{$query}";
            $variants[] = "http://{$host}:{$port}{$path}{$query}";
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function extractUniqueIdFromRecordingUrl(string $url): string
    {
        if (! str_contains($url, '/recording?id=')) {
            return '';
        }
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }
        parse_str($query, $params);
        $id = trim((string) ($params['id'] ?? ''));
        return $id;
    }

    protected function upsertCall(array $call): int
    {
        $externalId = $call['id'] ?? $call['call_id'] ?? $call['sid'] ?? null;
        if (! $externalId) {
            return 0;
        }

        $startTime = $call['start_time'] ?? $call['created_at'] ?? $call['date_created'] ?? null;
        if (is_string($startTime)) {
            $startTime = \Carbon\Carbon::parse($startTime);
        }

        PbxCall::updateOrCreate(
            ['external_id' => (string) $externalId],
            [
                'call_status' => $this->normalizeStatus($call['status'] ?? $call['call_status'] ?? ''),
                'direction' => $call['direction'] ?? $call['type'] ?? 'inbound',
                'customer_number' => $call['customer_number'] ?? $call['from'] ?? $call['caller'] ?? '',
                'reason_for_calling' => $call['reason_for_calling'] ?? $call['reason'] ?? '',
                'customer_name' => $call['customer_name'] ?? $call['caller_name'] ?? '',
                'user_name' => $call['user_name'] ?? $call['agent'] ?? $call['assigned_to'] ?? '',
                'recording_url' => $call['recording_url'] ?? $call['recording'] ?? '',
                'recording_path' => $call['recording_path'] ?? null,
                'duration_sec' => (int) ($call['duration_sec'] ?? $call['duration'] ?? 0),
                'start_time' => $startTime,
            ]
        );

        return 1;
    }

    protected function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $map = [
            'completed' => 'completed',
            'received' => 'received',
            'answered' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'no-response' => 'no-response',
            'no_answer' => 'no-answer',
            'failed' => 'failed',
        ];
        return $map[$status] ?? $status ?: 'unknown';
    }

    protected function parsePbxDateTime(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value)) {
            try {
                return \Carbon\Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function pbxWebhookResponse(Request $request, bool $success, string $message, int $status = 200)
    {
        if ($request->is('modules/PBXManager/callbacks/PBXManager.php')) {
            $state = $success ? 'Success' : 'Error';
            $safeMessage = htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><status>{$state}</status><message>{$safeMessage}</message></Response>";

            return response($xml, $status, ['Content-Type' => 'text/xml; charset=UTF-8']);
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
        ], $status);
    }

    /**
     * Merge claimed recipients (logged-in user who received the call) into call list.
     * Overrides user_name when a manual claim exists.
     */
    /**
     * @param  LengthAwarePaginator<\Illuminate\Database\Eloquent\Model|object>  $calls
     */
    protected function mergeClaimedRecipients(LengthAwarePaginator $calls, string $source): void
    {
        $callIds = [];
        foreach ($calls as $call) {
            $id = $call->id ?? ($call->pbxmanagerid ?? null);
            if ($id) {
                $callIds[] = $id;
            }
        }
        if (empty($callIds)) {
            return;
        }

        $claims = PbxCallRecipient::where('call_source', $source)
            ->whereIn('call_id', $callIds)
            ->get()
            ->keyBy(fn ($r) => "{$r->call_source}:{$r->call_id}");

        foreach ($calls as $call) {
            $id = $call->id ?? ($call->pbxmanagerid ?? null);
            if (! $id) {
                continue;
            }
            $key = "{$source}:{$id}";
            $claim = $claims->get($key);
            if ($claim && trim($claim->received_by_user_name ?? '') !== '') {
                $call->user_name = trim($claim->received_by_user_name);
                $call->received_by_user_id = $claim->received_by_user_id;
            }
        }
    }
}
