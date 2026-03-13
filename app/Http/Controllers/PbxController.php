<?php

namespace App\Http\Controllers;

use App\Models\PbxCall;
use App\Models\PbxCallRecipient;
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
    public function __construct(
        protected PbxConfigService $pbxConfig,
        protected PbxExtensionMappingService $extensionMapping
    ) {}

    public function index(Request $request)
    {
        if ($this->pbxConfig->isConfigured()) {
            return $this->indexFromVtiger($request);
        }

        return $this->indexFromLocal($request);
    }

    protected function indexFromVtiger(Request $request)
    {
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
                DB::raw('COALESCE(p.billduration, p.totalduration, 0) as duration_sec'),
                DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as user_name")
            );

        if ($request->filled('list')) {
            if ($request->list === 'Completed Calls') {
                $query->where('p.callstatus', 'completed');
            } elseif ($request->list === 'No Response Calls') {
                $query->whereIn('p.callstatus', ['no-response', 'no-answer', 'busy']);
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

    /**
     * Merge claimed recipients into call list. Overrides user_name when a claim exists.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $calls
     */
    protected function mergeClaimedRecipients($calls, string $source): void
    {
        $items = $calls->items();
        if (empty($items)) {
            return;
        }

        $callIds = [];
        foreach ($items as $call) {
            $id = $call->id ?? (is_object($call) ? ($call->getAttribute('id') ?? null) : null);
            if ($id) {
                $callIds[] = (int) $id;
            }
        }
        if (empty($callIds)) {
            return;
        }

        $claims = PbxCallRecipient::where('call_source', $source)
            ->whereIn('call_id', $callIds)
            ->get()
            ->keyBy('call_id');

        foreach ($items as $call) {
            $id = $call->id ?? (is_object($call) ? ($call->getAttribute('id') ?? null) : null);
            if ($id && $claims->has($id)) {
                $claim = $claims->get($id);
                $call->user_name = $claim->received_by_user_name;
            }
        }
    }

    protected function toCallDto(object $row, bool $fromVtiger): object
    {
        $recordingUrl = $row->recording_url ?? null;
        if (! $recordingUrl && ! empty($row->sourceuuid) && $fromVtiger) {
            $recordingUrl = $this->pbxConfig->getRecordingUrl($row->sourceuuid);
        }

        $userName = trim($row->user_name ?? '') ?: null;
        if (! $userName && $fromVtiger && isset($row->pbx_user)) {
            $userName = $this->extensionMapping->resolveUserName($userName, $row->pbx_user);
        }

        return (object) [
            'id' => $row->pbxmanagerid ?? $row->id ?? null,
            'call_status' => $row->call_status ?? null,
            'direction' => $row->direction ?? null,
            'customer_number' => $row->customer_number ?? null,
            'reason_for_calling' => null,
            'customer_name' => $row->customer_name ?? null,
            'user_name' => $userName,
            'recording_url' => $recordingUrl,
            'recording_path' => null,
            'duration_sec' => (int) ($row->duration_sec ?? 0),
            'start_time' => $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null,
            'from_vtiger' => $fromVtiger,
        ];
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

    public function recordingVtiger(int $id)
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

        if (! $recordingUrl) {
            abort(404);
        }

        return $this->streamRecordingFromUrl($recordingUrl);
    }

    public function recording(PbxCall $pbxCall)
    {
        if (! $pbxCall->hasRecording()) {
            abort(404);
        }

        if (! empty($pbxCall->recording_url)) {
            return $this->streamRecordingFromUrl($pbxCall->recording_url);
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
        $endpoints[] = ['method' => 'post', 'url' => $vtigerMakecallUrl, 'form' => false];
        $endpoints[] = ['method' => 'get', 'url' => $vtigerMakecallUrl, 'form' => false];
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
                        ->withHeaders($secretKey ? ['X-Vtiger-Secret' => $secretKey] : [])
                        ->get($url, $payload);
                } else {
                    $headers = array_filter([
                        'X-Vtiger-Secret' => $secretKey,
                        'Accept' => 'application/json',
                    ]);
                    if ($ep['form'] ?? false) {
                        $response = Http::timeout(10)->withHeaders($headers)->asForm()->post($url, $payload);
                    } else {
                        $headers['Content-Type'] = 'application/json';
                        $response = Http::timeout(10)->withHeaders($headers)->post($url, $payload);
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
     * Fetch recording from external URL and stream to browser.
     * Proxies through Laravel to avoid CORS and mixed-content issues.
     */
    protected function streamRecordingFromUrl(string $url)
    {
        try {
            $secretKey = $this->pbxConfig->getSecretKey();
            $response = Http::timeout(30)
                ->withHeaders($secretKey ? ['X-Vtiger-Secret' => $secretKey] : [])
                ->get($url);

            if (! $response->successful()) {
                abort(404, 'Recording not found');
            }

            $contentType = $response->header('Content-Type') ?: 'audio/mpeg';
            $contentDisposition = $response->header('Content-Disposition');

            return response($response->body(), 200, [
                'Content-Type' => $contentType,
                'Content-Length' => strlen($response->body()),
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            abort(404, 'Could not load recording: ' . $e->getMessage());
        }
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
            'answered' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'no-response' => 'no-response',
            'no_answer' => 'no-answer',
            'failed' => 'failed',
        ];
        return $map[$status] ?? $status ?: 'unknown';
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

    /**
     * Allow the logged-in user to claim they received a call.
     * Shows who actually received the call when PBX/vTiger mapping is wrong or empty.
     */
    public function claim(Request $request)
    {
        $validated = $request->validate([
            'call_id' => 'required|integer|min:1',
            'source' => 'required|in:vtiger,local',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'You must be logged in to claim a call.'], 401);
        }

        $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name ?? 'Agent';

        PbxCallRecipient::updateOrCreate(
            [
                'call_source' => $validated['source'],
                'call_id' => (int) $validated['call_id'],
            ],
            [
                'received_by_user_id' => $user->id,
                'received_by_user_name' => $userName,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Call marked as received by you.',
            'user_name' => $userName,
        ]);
    }
}
