<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErpSmsMessageService
{
    public function __construct(
        protected AdvantaSmsService $sms,
        protected ErpMessagingHttpClient $messagingHttp
    ) {}

    /**
     * True when Advanta SMS is configured and CRM can load pending rows (HTTP API or Oracle).
     */
    public function isReady(): bool
    {
        if (! $this->sms->isConfigured()) {
            return false;
        }

        return $this->canLoadPendingMessages();
    }

    /**
     * Whether pending rows can be read (erp-clients-api or direct Oracle).
     */
    public function canLoadPendingMessages(): bool
    {
        if ($this->transportUsesHttp()) {
            return true;
        }

        return (bool) config('erp.enabled', true) && extension_loaded('oci8');
    }

    /**
     * @return 'http'|'oracle'
     */
    public function activeTransport(): string
    {
        if ($this->transportUsesHttp()) {
            return 'http';
        }

        return 'oracle';
    }

    public function pendingCount(): int
    {
        if ($this->transportUsesHttp()) {
            $res = $this->messagingHttp->fetchPendingCount();
            if ($res['error'] !== null) {
                $fallback = $this->messagingHttp->fetchPendingSms(500);
                if ($fallback['error'] !== null) {
                    throw new \RuntimeException('ERP SMS API error: ' . $res['error']);
                }

                return count($fallback['rows']);
            }

            return (int) $res['count'];
        }

        $row = $this->withErpReconnect(function () {
            return DB::connection('erp')->selectOne(
                "SELECT COUNT(*) AS aggregate FROM {$this->table()} WHERE {$this->statusColumn()} = ?",
                [$this->pendingStatus()]
            );
        });

        $data = array_change_key_case((array) $row, CASE_LOWER);

        return (int) ($data['aggregate'] ?? 0);
    }

    /**
     * @return Collection<int, object>
     */
    public function pendingMessages(int $limit = 50): Collection
    {
        if ($this->transportUsesHttp()) {
            $res = $this->messagingHttp->fetchPendingSms($limit);
            if ($res['error'] !== null) {
                throw new \RuntimeException('ERP SMS API error: ' . $res['error']);
            }

            return collect($res['rows'])->map(fn ($row) => (object) $this->normalizeRow((object) $row));
        }

        return $this->pendingMessagesOracle($limit);
    }

    /**
     * @return Collection<int, object>
     */
    public function sentMessages(int $limit = 200): Collection
    {
        if ($this->transportUsesHttp()) {
            $res = $this->messagingHttp->fetchSentSms($limit);
            if ($res['error'] !== null) {
                throw new \RuntimeException('ERP SMS API error: ' . $res['error']);
            }

            return collect($res['rows'])->map(fn ($row) => (object) $this->normalizeSentRow((object) $row));
        }

        return $this->sentMessagesOracle($limit);
    }

    /**
     * @return Collection<int, object>
     */
    private function pendingMessagesOracle(int $limit = 50): Collection
    {
        if (! extension_loaded('oci8')) {
            throw new \RuntimeException(
                'Direct Oracle is not available (OCI8). Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE to your erp-clients-api URL '
                . 'and restart the API so /messages/sms/pending is available, or install OCI8 on the CRM server.'
            );
        }

        $limit = max(1, min(500, $limit));
        try {
            $rows = $this->withErpReconnect(function () use ($limit) {
                return DB::connection('erp')->select("
                SELECT *
                FROM (
                    SELECT
                        sms_code AS message_id,
                        sms_pol_no AS policy_no,
                        sms_tel_no AS phone,
                        sms_msg AS message_body,
                        DECODE(sms_sys_module, 'U', 'UNDERWRITING', 'R', 'RECEIPTING', sms_sys_module) AS sys_module,
                        sms_prepared_date AS created_date
                    FROM {$this->table()}
                    WHERE {$this->statusColumn()} = ?
                    ORDER BY sms_prepared_date
                )
                WHERE ROWNUM <= {$limit}
            ", [$this->pendingStatus()]);
            });

            return collect($rows)->map(fn ($row) => (object) $this->normalizeRow($row));
        } catch (\Throwable $e) {
            if ($this->isOracleConnectionDrop($e)) {
                throw new \RuntimeException(
                    'Oracle dropped the connection (ORA-03113) while loading SMS rows from the CRM web server. '
                    . 'Use erp-clients-api instead: set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE to the API root '
                    . '(same host as Finance), deploy the latest erp-clients-api with GET /messages/sms/pending, '
                    . 'and set ERP_MESSAGES_HTTP=auto (default). '
                    . 'Original error: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
            throw $e;
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function sentMessagesOracle(int $limit = 200): Collection
    {
        if (! extension_loaded('oci8')) {
            throw new \RuntimeException(
                'Direct Oracle is not available (OCI8). Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE to your erp-clients-api URL.'
            );
        }

        $limit = max(1, min(500, $limit));
        $sentDateColumn = trim((string) config('erp.messages_sent_date_column', ''));
        $sentDateSelect = $sentDateColumn !== ''
            ? "{$sentDateColumn} AS sent_date,"
            : 'CAST(NULL AS DATE) AS sent_date,';

        $rows = $this->withErpReconnect(function () use ($limit, $sentDateSelect) {
            return DB::connection('erp')->select("
                SELECT *
                FROM (
                    SELECT
                        sms_code AS message_id,
                        sms_pol_no AS policy_no,
                        sms_tel_no AS phone,
                        sms_msg AS message_body,
                        DECODE(sms_sys_module, 'U', 'UNDERWRITING', 'R', 'RECEIPTING', sms_sys_module) AS sys_module,
                        sms_prepared_date AS created_date,
                        {$sentDateSelect}
                        {$this->statusColumn()} AS erp_status
                    FROM {$this->table()}
                    WHERE {$this->statusColumn()} = ?
                    ORDER BY sms_prepared_date DESC
                )
                WHERE ROWNUM <= {$limit}
            ", [$this->sentStatus()]);
        });

        return collect($rows)->map(fn ($row) => (object) $this->normalizeSentRow($row));
    }

    /**
     * @return array{rows: Collection<int, object>, counts: array<string, int>}
     */
    public function sentMessagesWithTracking(int $limit = 200, ?string $filter = null): array
    {
        $rawRows = $this->sentMessages($limit);
        $normalized = $rawRows->map(fn ($row) => $this->normalizeSentRow($row))->all();
        $logLookup = $this->buildSmsLogLookup($normalized);
        $rows = collect($normalized)->map(function (array $row) use ($logLookup) {
            return (object) $this->applySmsLogToRow($row, $logLookup);
        });
        $rows = $this->mergeDeliveredRowsFromLogs($rows, $limit);

        $counts = [
            'total' => $rows->count(),
            'sent' => $rows->count(),
            'delivered' => $rows->where('delivery_state', 'delivered')->count(),
            'read' => $rows->where('read_state', 'read')->count(),
            'not_read' => $rows->where('read_state', 'not_read')->count(),
            'pending_delivery' => $rows->where('delivery_state', 'pending')->count(),
        ];

        if ($filter !== null && $filter !== '' && $filter !== 'all') {
            $rows = $rows->filter(function ($row) use ($filter) {
                return match ($filter) {
                    'sent' => true,
                    'delivered' => ($row->delivery_state ?? '') === 'delivered',
                    'read' => ($row->read_state ?? '') === 'read',
                    'not_read' => ($row->read_state ?? '') === 'not_read',
                    'pending_delivery' => ($row->delivery_state ?? '') === 'pending',
                    default => true,
                };
            })->values();
        }

        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * Merge delivered rows from sms_logs so delivered filters are not limited
     * only to the latest ERP sent snapshot.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function mergeDeliveredRowsFromLogs(Collection $rows, int $limit): Collection
    {
        $logs = SmsLog::query()
            ->whereNotNull('delivered_at')
            ->orWhereIn('delivery_status', ['delivered', 'delivery_confirmed'])
            ->orderByDesc('delivered_at')
            ->orderByDesc('sent_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        if ($logs->isEmpty()) {
            return $rows;
        }

        $existingKeys = [];
        foreach ($rows as $row) {
            $existingKeys[$this->sentRowUniqueKey((array) $row)] = true;
        }

        $merged = $rows->values();
        foreach ($logs as $log) {
            $mapped = [
                'message_id' => $log->erp_message_id,
                'policy_no' => $log->erp_policy_no,
                'phone' => $log->phone,
                'message_body' => $log->message,
                'sys_module' => null,
                'created_date' => null,
                'sent_date' => null,
                'erp_status' => $this->sentStatus(),
                'crm_sent_at' => $log->sent_at,
                'delivery_status' => strtolower(trim((string) ($log->delivery_status ?? 'delivered'))),
                'delivery_state' => 'delivered',
                'delivered_at' => $log->delivered_at ?? $log->updated_at,
                'read_at' => $log->read_at,
                'read_state' => $log->read_at ? 'read' : 'not_read',
            ];

            $key = $this->sentRowUniqueKey($mapped);
            if (isset($existingKeys[$key])) {
                continue;
            }

            $existingKeys[$key] = true;
            $merged->push((object) $mapped);
        }

        return $merged
            ->sortByDesc(function ($row) {
                $candidate = $row->delivered_at ?? $row->crm_sent_at ?? $row->sent_date ?? $row->created_date;

                return $candidate ? strtotime((string) $candidate) : 0;
            })
            ->take(max(1, min(500, $limit)))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sentRowUniqueKey(array $row): string
    {
        $id = trim((string) ($row['message_id'] ?? ''));
        if ($id !== '') {
            return 'id:' . $id;
        }

        $phone = $this->sms->normalizePhone((string) ($row['phone'] ?? ''));
        $body = Str::lower(trim((string) ($row['message_body'] ?? '')));

        return 'pb:' . sha1($phone . '|' . $body);
    }

    /**
     * @return array{processed:int,sent:int,failed:int,skipped:int,results:array<int,array<string,mixed>>}
     */
    public function sendPending(int $limit = 50, bool $dryRun = false, ?int $userId = null): array
    {
        if (! $dryRun) {
            @ini_set('max_execution_time', '0');
            @set_time_limit(0);
        }

        $messages = $this->pendingMessages($limit);
        $summary = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'results' => [],
        ];
        /** @var array<int, string> $smsOkIds */
        $smsOkIds = [];

        foreach ($messages as $message) {
            $summary['processed']++;

            $messageId = trim((string) ($message->message_id ?? ''));
            $phone = trim((string) ($message->phone ?? ''));
            $body = trim((string) ($message->message_body ?? ''));

            if ($messageId === '' || $phone === '' || $body === '') {
                $summary['skipped']++;
                $summary['results'][] = [
                    'message_id' => $messageId,
                    'phone' => $phone,
                    'success' => false,
                    'skipped' => true,
                    'error' => 'Missing message ID, phone, or message body.',
                ];
                continue;
            }

            if ($this->alreadyLoggedAsSent($messageId)) {
                $markResult = $this->markManyAsSent([$messageId]);
                $marked = in_array($messageId, $markResult['marked'], true);
                if ($marked) {
                    $summary['sent']++;
                } else {
                    $summary['failed']++;
                }
                $summary['results'][] = [
                    'message_id' => $messageId,
                    'phone' => $this->sms->normalizePhone($phone),
                    'success' => $marked,
                    'skipped' => true,
                    'error' => $marked ? null : ('Already sent in CRM before, but ERP status update still failed: ' . ($markResult['error'] ?? 'unknown ERP update error')),
                    'erp_marked' => $marked,
                ];

                continue;
            }

            $normalizedPhone = $this->sms->normalizePhone($phone);
            if (! $this->isValidKenyanMobile($normalizedPhone)) {
                $error = 'Invalid phone number: ' . $normalizedPhone;
                $this->logSms(
                    $normalizedPhone,
                    $body,
                    false,
                    $error,
                    $userId,
                    $messageId,
                    (string) ($message->policy_no ?? ''),
                    null,
                    null
                );

                $failedMark = $this->markAsFailed($messageId);
                $summary['failed']++;
                $summary['results'][] = [
                    'message_id' => $messageId,
                    'phone' => $normalizedPhone,
                    'success' => false,
                    'skipped' => false,
                    'error' => $failedMark
                        ? $error
                        : ($error . ' (and ERP row could not be moved to failed status)'),
                    'erp_marked' => $failedMark,
                ];
                Log::warning('ERP SMS message has invalid phone and was quarantined', [
                    'message_id' => $messageId,
                    'phone' => $normalizedPhone,
                    'failed_status' => $this->failedStatus(),
                    'erp_marked' => $failedMark,
                ]);

                continue;
            }

            if ($dryRun) {
                $summary['skipped']++;
                $summary['results'][] = [
                    'message_id' => $messageId,
                    'phone' => $normalizedPhone,
                    'success' => true,
                    'skipped' => true,
                    'error' => null,
                ];
                continue;
            }

            $result = $this->sms->send($normalizedPhone, $body);
            $success = (bool) ($result['success'] ?? false);
            $error = $success ? null : (string) ($result['error'] ?? 'SMS send failed.');
            $advantaMessageId = $result['advanta_message_id'] ?? null;

            $this->logSms(
                $normalizedPhone,
                $body,
                $success,
                $error,
                $userId,
                $messageId,
                (string) ($message->policy_no ?? ''),
                $result['response'] ?? null,
                $advantaMessageId
            );

            if ($success) {
                $smsOkIds[] = $messageId;
            } else {
                $summary['failed']++;
                Log::warning('ERP SMS message send failed', [
                    'message_id' => $messageId,
                    'phone' => $normalizedPhone,
                    'error' => $error,
                ]);
            }

            $summary['results'][] = [
                'message_id' => $messageId,
                'phone' => $normalizedPhone,
                'success' => $success,
                'skipped' => false,
                'error' => $error,
                'erp_marked' => false,
            ];
        }

        if ($smsOkIds !== [] && ! $dryRun) {
            $markResult = $this->markManyAsSent($smsOkIds);
            $markedSet = array_flip($markResult['marked']);
            foreach ($summary['results'] as $idx => $result) {
                if (empty($result['success'])) {
                    continue;
                }
                $mid = (string) ($result['message_id'] ?? '');
                if (isset($markedSet[$mid])) {
                    $summary['results'][$idx]['erp_marked'] = true;
                    $summary['sent']++;
                    continue;
                }
                $summary['results'][$idx]['success'] = false;
                $summary['results'][$idx]['error'] = 'SMS sent, but ERP status update failed: '
                    . ($markResult['error'] ?? 'batch mark did not include this message');
                $summary['failed']++;
                Log::warning('ERP SMS message sent but batch mark-sent missed row', [
                    'message_id' => $mid,
                    'batch_error' => $markResult['error'],
                ]);
            }
        }

        return $summary;
    }

    private function alreadyLoggedAsSent(string $erpMessageId): bool
    {
        $erpMessageId = trim($erpMessageId);
        if ($erpMessageId === '') {
            return false;
        }

        return SmsLog::query()
            ->where('erp_message_id', $erpMessageId)
            ->whereIn('status', ['sent', 'delivered'])
            ->exists();
    }

    private function logSms(
        string $phone,
        string $message,
        bool $success,
        ?string $error,
        ?int $userId,
        ?string $erpMessageId = null,
        ?string $erpPolicyNo = null,
        mixed $providerResponse = null,
        ?string $advantaMessageId = null
    ): void {
        try {
            SmsLog::create([
                'contact_id' => null,
                'erp_message_id' => $erpMessageId,
                'erp_policy_no' => $erpPolicyNo !== '' ? $erpPolicyNo : null,
                'advanta_message_id' => $advantaMessageId,
                'phone' => $phone,
                'message' => $message,
                'status' => $success ? 'sent' : 'failed',
                'delivery_status' => $success ? 'submitted' : 'failed',
                'error_message' => $success ? null : $error,
                'provider_response' => is_array($providerResponse) ? $providerResponse : null,
                'user_id' => $userId,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ERP SMS message log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, string>  $messageIds
     * @return array{marked: array<int, string>, error: ?string}
     */
    private function markManyAsSent(array $messageIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $messageIds
        ))));

        if ($ids === []) {
            return ['marked' => [], 'error' => null];
        }

        $batchSize = max(10, min(200, (int) config('erp.messages_mark_batch_size', 100)));
        $marked = [];
        $lastError = null;

        foreach (array_chunk($ids, $batchSize) as $chunk) {
            if ($this->transportUsesHttp()) {
                $res = $this->messagingHttp->markSmsSentBatch(
                    $chunk,
                    $this->pendingStatus(),
                    $this->sentStatus()
                );
                if ($res['error'] !== null) {
                    $lastError = $res['error'];
                    $marked = array_merge($marked, $this->markManyAsSentFallback($chunk));

                    continue;
                }
                if ((int) $res['updated'] >= count($chunk)) {
                    foreach ($chunk as $id) {
                        $marked[] = $id;
                    }
                } else {
                    $lastError = 'ERP batch updated ' . $res['updated'] . ' of ' . count($chunk) . ' row(s)';
                    $marked = array_merge($marked, $this->markManyAsSentFallback($chunk));
                }

                continue;
            }

            $setSql = ["{$this->statusColumn()} = ?"];
            $bindings = [$this->sentStatus()];
            $sentDateColumn = trim((string) config('erp.messages_sent_date_column', ''));
            if ($sentDateColumn !== '') {
                $setSql[] = "{$sentDateColumn} = SYSDATE";
            }
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($bindings, $chunk, [$this->pendingStatus()]);

            $updated = $this->withErpReconnect(function () use ($setSql, $placeholders, $bindings): int {
                return DB::connection('erp')->update(
                    "UPDATE {$this->table()} SET " . implode(', ', $setSql)
                    . " WHERE sms_code IN ({$placeholders}) AND {$this->statusColumn()} = ?",
                    $bindings
                );
            });

            if ((int) $updated >= count($chunk)) {
                foreach ($chunk as $id) {
                    $marked[] = $id;
                }
            } else {
                $lastError = 'Oracle batch updated ' . $updated . ' of ' . count($chunk) . ' row(s)';
                $marked = array_merge($marked, $this->markManyAsSentFallback($chunk));
            }
        }

        return ['marked' => array_values(array_unique($marked)), 'error' => $lastError];
    }

    /**
     * Per-row mark when batch endpoint is missing or partial.
     *
     * @param  array<int, string>  $messageIds
     * @return array<int, string>
     */
    private function markManyAsSentFallback(array $messageIds): array
    {
        $marked = [];
        foreach ($messageIds as $messageId) {
            if ($this->transportUsesHttp()) {
                $res = $this->messagingHttp->markSmsSent($messageId, $this->pendingStatus(), $this->sentStatus());
                if ($res['error'] === null && (int) $res['updated'] >= 1) {
                    $marked[] = $messageId;
                }

                continue;
            }

            $setSql = ["{$this->statusColumn()} = ?"];
            $bindings = [$this->sentStatus()];
            $sentDateColumn = trim((string) config('erp.messages_sent_date_column', ''));
            if ($sentDateColumn !== '') {
                $setSql[] = "{$sentDateColumn} = SYSDATE";
            }
            $bindings[] = $messageId;
            $bindings[] = $this->pendingStatus();

            $updated = $this->withErpReconnect(function () use ($setSql, $bindings): int {
                return DB::connection('erp')->update(
                    "UPDATE {$this->table()} SET " . implode(', ', $setSql) . " WHERE sms_code = ? AND {$this->statusColumn()} = ?",
                    $bindings
                );
            });

            if ((int) $updated >= 1) {
                $marked[] = $messageId;
            }
        }

        return $marked;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRow(object $row): array
    {
        $data = array_change_key_case((array) $row, CASE_LOWER);

        return [
            'message_id' => $data['message_id'] ?? null,
            'policy_no' => $data['policy_no'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message_body' => $data['message_body'] ?? null,
            'sys_module' => $data['sys_module'] ?? null,
            'created_date' => $data['created_date'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSentRow(object $row): array
    {
        $data = array_change_key_case((array) $row, CASE_LOWER);

        return [
            'message_id' => $data['message_id'] ?? null,
            'policy_no' => $data['policy_no'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message_body' => $data['message_body'] ?? null,
            'sys_module' => $data['sys_module'] ?? null,
            'created_date' => $data['created_date'] ?? null,
            'sent_date' => $data['sent_date'] ?? null,
            'erp_status' => $data['erp_status'] ?? $this->sentStatus(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{by_id: array<string, SmsLog>, by_phone_body: array<string, SmsLog>}
     */
    private function buildSmsLogLookup(array $rows): array
    {
        $ids = [];
        $phones = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['message_id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
            $phone = $this->sms->normalizePhone((string) ($row['phone'] ?? ''));
            if ($phone !== '') {
                $phones[$phone] = true;
            }
        }

        $query = SmsLog::query()->orderByDesc('sent_at');
        if ($ids !== [] || $phones !== []) {
            $query->where(function ($q) use ($ids, $phones) {
                if ($ids !== []) {
                    $q->whereIn('erp_message_id', array_keys($ids));
                }
                if ($phones !== []) {
                    $q->orWhereIn('phone', array_keys($phones));
                }
            });
        } else {
            return ['by_id' => [], 'by_phone_body' => []];
        }

        $byId = [];
        $byPhoneBody = [];
        foreach ($query->limit(600)->get() as $log) {
            $erpId = trim((string) ($log->erp_message_id ?? ''));
            if ($erpId !== '' && ! isset($byId[$erpId])) {
                $byId[$erpId] = $log;
            }
            $pbKey = 'pb:' . sha1($log->phone . '|' . Str::lower(trim((string) $log->message)));
            if (! isset($byPhoneBody[$pbKey])) {
                $byPhoneBody[$pbKey] = $log;
            }
        }

        return ['by_id' => $byId, 'by_phone_body' => $byPhoneBody];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{by_id: array<string, SmsLog>, by_phone_body: array<string, SmsLog>}  $lookup
     * @return array<string, mixed>
     */
    private function applySmsLogToRow(array $row, array $lookup): array
    {
        $messageId = trim((string) ($row['message_id'] ?? ''));
        $phone = $this->sms->normalizePhone((string) ($row['phone'] ?? ''));
        $body = trim((string) ($row['message_body'] ?? ''));

        $log = null;
        if ($messageId !== '' && isset($lookup['by_id'][$messageId])) {
            $log = $lookup['by_id'][$messageId];
        } else {
            $pbKey = 'pb:' . sha1($phone . '|' . Str::lower($body));
            $log = $lookup['by_phone_body'][$pbKey] ?? null;
        }

        $deliveryStatus = strtolower(trim((string) ($log?->delivery_status ?? 'unknown')));
        $advantaLabel = trim((string) ($log?->advanta_status ?? ''));
        $deliveryState = match (true) {
            in_array($deliveryStatus, ['delivered', 'delivery_confirmed'], true) => 'delivered',
            in_array($deliveryStatus, ['blacklisted', 'failed'], true) => 'failed',
            in_array($deliveryStatus, ['submitted', 'sent', 'accepted'], true) => 'pending',
            default => 'unknown',
        };
        $readState = $log?->read_at ? 'read' : ($deliveryState === 'delivered' ? 'not_read' : 'unknown');

        return array_merge($row, [
            'crm_sent_at' => $log?->sent_at,
            'advanta_message_id' => $log?->advanta_message_id,
            'advanta_status' => $advantaLabel !== '' ? $advantaLabel : null,
            'advanta_delivery_tat' => $log?->advanta_delivery_tat,
            'delivery_status' => $deliveryStatus !== '' ? $deliveryStatus : 'unknown',
            'delivery_state' => $deliveryState,
            'delivered_at' => $log?->delivered_at,
            'read_at' => $log?->read_at,
            'read_state' => $readState,
        ]);
    }

    private function table(): string
    {
        return (string) config('erp.messages_table', 'tq_crm.tqc_smslife_messages');
    }

    private function statusColumn(): string
    {
        return (string) config('erp.messages_status_column', 'sms_status');
    }

    private function pendingStatus(): string
    {
        return (string) config('erp.messages_pending_status', 'D');
    }

    private function sentStatus(): string
    {
        return (string) config('erp.messages_sent_status', 'OK');
    }

    private function failedStatus(): string
    {
        return (string) config('erp.messages_failed_status', 'E');
    }

    private function isValidKenyanMobile(string $phone): bool
    {
        return preg_match('/^2547\d{8}$/', $phone) === 1;
    }

    private function markAsFailed(string $messageId): bool
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            return false;
        }

        if ($this->transportUsesHttp()) {
            $res = $this->messagingHttp->markSmsSent($messageId, $this->pendingStatus(), $this->failedStatus());

            return $res['error'] === null && (int) $res['updated'] >= 1;
        }

        $updated = $this->withErpReconnect(function () use ($messageId): int {
            return DB::connection('erp')->update(
                "UPDATE {$this->table()} SET {$this->statusColumn()} = ? WHERE sms_code = ? AND {$this->statusColumn()} = ?",
                [$this->failedStatus(), $messageId, $this->pendingStatus()]
            );
        });

        return (int) $updated >= 1;
    }

    private function transportUsesHttp(): bool
    {
        $mode = strtolower(trim((string) config('erp.messages_http', 'auto')));
        if (! in_array($mode, ['auto', 'http', 'oracle'], true)) {
            $mode = 'auto';
        }
        if ($mode === 'oracle') {
            return false;
        }
        if ($mode === 'http') {
            return $this->messagingHttp->isConfigured();
        }

        return $this->messagingHttp->isConfigured();
    }

    private function withErpReconnect(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if (! $this->isOracleConnectionDrop($e)) {
                throw $e;
            }

            DB::purge('erp');
            DB::reconnect('erp');

            return $callback();
        }
    }

    private function isOracleConnectionDrop(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ORA-03113')
            || str_contains($message, 'ORA-03114')
            || str_contains($message, 'ORA-03135')
            || str_contains($message, 'end-of-file on communication channel')
            || str_contains($message, 'not connected to ORACLE');
    }
}
