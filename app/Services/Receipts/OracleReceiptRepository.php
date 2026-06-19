<?php

namespace App\Services\Receipts;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads receipt data from the ERP Oracle schema (table fms_receipts), using the
 * same PL/SQL helper packages as the official receipt report:
 *   - fms_rcts_pkg      (customer / client / policy resolution)
 *   - fms_gen_pkg       (currency names)
 *   - tqc_interfaces_pkg(branch name, capturing user name)
 *
 * Two database quirks are handled here:
 *
 *  1) NAME RESOLUTION: unqualified object names (e.g. "fms_receipts") resolve
 *     through a broken synonym path on this DB and drop the session (ORA-03113).
 *     The TABLE must therefore be SCHEMA-QUALIFIED (config: TQ_FMS.FMS_RECEIPTS),
 *     while the PL/SQL packages must stay UNqualified (they resolve via synonyms;
 *     qualifying them fails).
 *
 *  2) UNSTABLE LINK: the Easy-Connect link over port 18032 intermittently drops
 *     a session mid-statement (ORA-03113). Every read is retried on a fresh
 *     connection (see runWithRetry()).
 *
 * Receipts are keyed by the COMPOSITE (rct_no, rct_brh_code). rct_no (e.g.
 * 604195) is the global PK; rct_brh_rct_code (e.g. "1/HO/111640") is the printed
 * receipt number. All access is strictly read-only.
 */
class OracleReceiptRepository implements ReceiptDataSource
{
    public function __construct(
        protected string $connection,
        protected string $table,
    ) {
    }

    /** Whether a stable session has been established this request. */
    protected bool $warmed = false;

    /**
     * Establish a stable session before issuing real queries. The link tends to
     * drop the FIRST statement on a freshly-dialled session (ORA-03113); paying
     * that cost once with a trivial query lets the subsequent receipt query
     * succeed on the first try (a warmed session runs the full receipt query in
     * well under a second; a cold one drops it every time).
     */
    protected function warmConnection(): void
    {
        if ($this->warmed) {
            return;
        }

        if ($this->dialStableSession()) {
            $this->warmed = true;
        }
    }

    /**
     * Dial a fresh session and confirm it survives a trivial statement,
     * retrying on transient drops. Returns true once a stable session is held.
     */
    protected function dialStableSession(): bool
    {
        $attempts = max(1, (int) config('receipt.retry_attempts', 12));

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                DB::connection($this->connection)->select('SELECT 1 FROM DUAL');

                return true;
            } catch (Throwable $e) {
                if (! $this->isTransient($e)) {
                    return false;
                }
                DB::purge($this->connection);
                usleep(250_000);
            }
        }

        return false;
    }

    public function search(string $query, string $type): array
    {
        $this->warmConnection();

        $raw = trim($query);
        $term = '%'.mb_strtoupper($raw).'%';
        $numeric = ctype_digit($raw) ? $raw : '-1';

        [$where, $binds] = $this->searchCondition($type, $term, $numeric, $raw);

        // Filter on INDEXED predicates in the innermost query (a full-table scan
        // of this 377k-row table over the fragile link reliably drops the
        // session), cap rows with ROWNUM, THEN resolve the PL/SQL name functions
        // only for the <=50 rows actually returned.
        $sql = "
            SELECT
                base.RCT_NO       AS RCT_NO,
                base.BRANCH_CODE  AS BRANCH_CODE,
                base.RECEIPT_NO   AS RECEIPT_NO,
                base.RECEIPT_DATE AS RECEIPT_DATE,
                base.AMOUNT       AS AMOUNT,
                NVL(base.RECEIVED_FROM, fms_rcts_pkg.getclientname(base.RCT_NO)) AS CLIENT_NAME,
                UPPER(fms_gen_pkg.currencyname(base.CUR_CODE, 'S'))              AS CURRENCY,
                fms_rcts_pkg.get_policy_details(base.RCT_NO)                     AS POLICY_NO
            FROM (
                SELECT * FROM (
                    SELECT
                        rct_no                              AS RCT_NO,
                        rct_brh_code                        AS BRANCH_CODE,
                        rct_brh_rct_code                    AS RECEIPT_NO,
                        TO_CHAR(rct_date, 'DD/MM/YYYY')     AS RECEIPT_DATE,
                        NVL(rct_amount, rct_gross_amount)   AS AMOUNT,
                        rct_received_from                   AS RECEIVED_FROM,
                        rct_cur_code                        AS CUR_CODE
                    FROM {$this->table}
                    WHERE {$where}
                    ORDER BY rct_date DESC
                ) WHERE ROWNUM <= 50
            ) base";

        $rows = $this->runWithRetry(fn ($conn) => $conn->select($sql, $binds));

        return array_map([$this, 'normalize'], $rows);
    }

    public function find(string $receiptNo, ?string $branch = null): ?array
    {
        $this->warmConnection();

        $where = 'rct_no = :rct';
        $binds = ['rct' => $receiptNo];

        if ($branch !== null && $branch !== '') {
            $where .= ' AND rct_brh_code = :brh';
            $binds['brh'] = $branch;
        }

        // Mirrors the official "PREMIUM PAYMENT RECEIPT" (duplicate receipt)
        // report. The effective amount honours multi-org receipts the same way
        // the report does: DECODE(multi_org,'Y',multi_org_amt, amount/gross).
        //
        // This single statement chains several cross-schema PL/SQL helper calls.
        // On a freshly-dialled session the link drops it (ORA-03113); on a
        // warmed session (see warmConnection / runWithRetry) it completes in
        // well under a second. NUMBER_TO_WORDS and the agent lookup are resolved
        // separately because they can fail per-receipt without warranting a
        // whole-receipt failure.
        $effective = "NVL(DECODE(rct_multi_org, 'Y', rct_multi_org_amt, rct_amount),
                          DECODE(rct_multi_org, 'Y', rct_multi_org_amt, rct_gross_amount))";

        $sql = "
            SELECT * FROM (
                SELECT
                    rct_no                                     AS RCT_NO,
                    rct_brh_code                               AS BRANCH_CODE,
                    rct_brh_rct_code                           AS RECEIPT_NO,
                    TO_CHAR(rct_date, 'DD/MM/YYYY')            AS RECEIPT_DATE,
                    NVL(rct_received_from, fms_rcts_pkg.getcustomername(rct_no)) AS RECEIVED_FROM,
                    fms_rcts_pkg.getclientname(rct_no)         AS CLIENT_NAME,
                    fms_rcts_pkg.getpolicyno(rct_no)           AS POLICY_NUMBER,
                    fms_rcts_pkg.get_policy_details(rct_no)    AS POLICY_DETAILS,
                    rct_paymt_mode                             AS PAYMENT_MODE,
                    rct_paymt_memo                             AS CHEQUE_NO,
                    rct_drawers_bank                           AS DRAWERS_BANK,
                    rct_desc                                   AS BEING_PAYMENT_FOR,
                    tqc_interfaces_pkg.branchname(rct_brh_code)        AS BRANCH,
                    tqc_interfaces_pkg.username(rct_captured_by, 'Y')  AS USER_NAME,
                    UPPER(fms_gen_pkg.currencyname(rct_cur_code, 'S')) AS CURRENCY,
                    rct_gross_amount                           AS GROSS_AMOUNT,
                    rct_amount                                 AS NET_AMOUNT,
                    (NVL(rct_bank_charge_amount, 0) + NVL(rct_client_charge_amount, 0)) AS OTHER_AMOUNT,
                    {$effective}                               AS TOTAL_AMOUNT,
                    rct_internal_remarks                       AS INTERNAL_REMARKS,
                    rct_app                                    AS RCT_APP,
                    rct_acct_type_id                           AS ACCT_TYPE_ID
                FROM {$this->table}
                WHERE {$where}
                ORDER BY rct_date DESC
            ) WHERE ROWNUM <= 1";

        $row = $this->runWithRetry(fn ($conn) => $conn->selectOne($sql, $binds));

        if ($row === null) {
            return null;
        }

        $header = $this->normalize($row);

        // Cast money columns to float so they format consistently (RTF/XML/HTML).
        foreach (['gross_amount', 'net_amount', 'other_amount', 'total_amount'] as $moneyKey) {
            if (isset($header[$moneyKey]) && $header[$moneyKey] !== '') {
                $header[$moneyKey] = (float) $header[$moneyKey];
            }
        }

        // Derived / back-compat fields.
        $header['title'] = 'PREMIUM PAYMENT RECEIPT';
        $header['amount'] = $header['total_amount'] ?? $header['gross_amount'] ?? 0;
        // "Geog Ext. Amount" has no dedicated header column; the net allocated
        // amount is the closest faithful value (equals gross when no charges).
        $header['geog_ext_amount'] = $header['net_amount'] ?? $header['gross_amount'] ?? null;
        // Show "Other Amount" only when there are actual bank/client charges.
        if (($header['other_amount'] ?? 0) <= 0) {
            $header['other_amount'] = null;
        }
        $header['policy_no'] = $header['policy_number'] ?? $header['policy_details'] ?? null;
        $header['reference_no'] = $header['cheque_no'] ?? null;
        $header['cashier'] = $header['user_name'] ?? null;
        $header['narration'] = $header['being_payment_for'] ?? null;

        // Intermediary (agent) details come from a PL/SQL function that raises
        // ORA-06502 on some receipts, so fetch them in an isolated query that
        // can never break the main receipt.
        $agent = $this->optionalAgent($binds);
        $header['intermediary_code'] = $agent['code'] ?? null;
        $header['intermediary_name'] = $agent['name'] ?? null;
        $header['intermediary'] = $this->formatIntermediary($agent['code'] ?? null, $agent['name'] ?? null);

        // Amount in words. The ERP NUMBER_TO_WORDS function lives in the
        // receipts schema and may not be callable by the connecting user, so
        // resolve it in an isolated, schema-qualified query and fall back to a
        // PHP conversion when it is not available (keeps the receipt rendering).
        $header['amount_in_words'] = $this->optionalAmountInWords($binds)
            ?? $this->amountInWords((float) ($header['total_amount'] ?? $header['amount'] ?? 0));

        return [
            'header' => $header,
            'lines' => $this->buildLines($header),
        ];
    }

    /**
     * Resolve the intermediary (agent) code + name. Uses getagentname() (the
     * working function; get_agents_name() raises ORA-06502 on this DB). Any
     * failure is swallowed so the receipt still renders without the agent.
     *
     * @param  array<string,mixed>  $binds
     * @return array{code?: ?string, name?: ?string}
     */
    protected function optionalAgent(array $binds): array
    {
        $where = isset($binds['brh']) ? 'rct_no = :rct AND rct_brh_code = :brh' : 'rct_no = :rct';

        $sql = "
            SELECT * FROM (
                SELECT
                    fms_rcts_pkg.getagentcode(rct_no) AS CODE,
                    fms_rcts_pkg.getagentname(rct_no) AS NAME
                FROM {$this->table}
                WHERE {$where}
            ) WHERE ROWNUM <= 1";

        try {
            $row = $this->runWithRetry(fn ($conn) => $conn->selectOne($sql, $binds));

            if ($row === null) {
                return [];
            }

            $r = $this->normalize($row);

            return ['code' => $r['code'] ?? null, 'name' => $r['name'] ?? null];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve the official "amount in words" via the ERP NUMBER_TO_WORDS
     * function, schema-qualified to the receipts table's owner (e.g. TQ_FMS).
     * Returns null on any failure so the caller can fall back to PHP.
     *
     * @param  array<string,mixed>  $binds
     */
    protected function optionalAmountInWords(array $binds): ?string
    {
        $schema = str_contains($this->table, '.') ? trim(explode('.', $this->table, 2)[0]) : '';
        if ($schema === '') {
            return null;
        }

        $where = isset($binds['brh']) ? 'rct_no = :rct AND rct_brh_code = :brh' : 'rct_no = :rct';

        $effective = "NVL(DECODE(rct_multi_org, 'Y', rct_multi_org_amt, rct_amount),
                          DECODE(rct_multi_org, 'Y', rct_multi_org_amt, rct_gross_amount))";

        $sql = "
            SELECT * FROM (
                SELECT ({$schema}.NUMBER_TO_WORDS({$effective}, rct_cur_code) || ' ONLY') AS WORDS
                FROM {$this->table}
                WHERE {$where}
            ) WHERE ROWNUM <= 1";

        try {
            $row = $this->runWithRetry(fn ($conn) => $conn->selectOne($sql, $binds));

            if ($row === null) {
                return null;
            }

            $r = $this->normalize($row);
            $words = trim((string) ($r['words'] ?? ''));

            return $words !== '' ? $words : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Convert a monetary amount to an uppercase words string, e.g.
     * 45000.50 -> "FORTY FIVE THOUSAND AND FIFTY CENTS ONLY".
     */
    protected function amountInWords(float $amount): string
    {
        $whole = (int) floor(abs($amount));
        $cents = (int) round((abs($amount) - $whole) * 100);

        $words = $this->intToWords($whole);
        if ($cents > 0) {
            $words .= ' AND ' . $this->intToWords($cents) . ' CENTS';
        }

        return strtoupper(trim($words) . ' ONLY');
    }

    protected function intToWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
            'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $scales = ['', ' thousand', ' million', ' billion', ' trillion'];

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $g = $groups[$i];
            if ($g === 0) {
                continue;
            }

            $chunk = '';
            $hundreds = intdiv($g, 100);
            $rest = $g % 100;

            if ($hundreds > 0) {
                $chunk .= $ones[$hundreds] . ' hundred';
                if ($rest > 0) {
                    $chunk .= ' ';
                }
            }

            if ($rest > 0) {
                if ($rest < 20) {
                    $chunk .= $ones[$rest];
                } else {
                    $chunk .= $tens[intdiv($rest, 10)];
                    if ($rest % 10 > 0) {
                        $chunk .= ' ' . $ones[$rest % 10];
                    }
                }
            }

            $parts[] = $chunk . $scales[$i];
        }

        return implode(' ', $parts);
    }

    /**
     * Format the intermediary as "[CODE] NAME" (matching the official receipt),
     * returning null when there is effectively no intermediary.
     */
    protected function formatIntermediary(?string $code, ?string $name): ?string
    {
        $code = trim((string) $code);
        $name = trim((string) $name);

        if ($name === '' && ($code === '' || strtoupper($code) === 'N/A')) {
            return null;
        }

        $prefix = ($code !== '' && strtoupper($code) !== 'N/A') ? "[{$code}] " : '';

        return trim($prefix.$name) ?: null;
    }

    /**
     * Run a read against the receipts connection, retrying on transient
     * connection drops (ORA-03113 etc.) with a fresh connection each time.
     *
     * @template T
     * @param  callable(\Illuminate\Database\Connection): T  $callback
     * @return T
     */
    protected function runWithRetry(callable $callback): mixed
    {
        $attempts = max(1, (int) config('receipt.retry_attempts', 12));
        $lastError = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $callback(DB::connection($this->connection));
            } catch (Throwable $e) {
                $lastError = $e;

                if (! $this->isTransient($e)) {
                    throw $e;
                }

                // Drop the (now-dead) pooled connection and re-establish a
                // stable session BEFORE the next attempt. Retrying straight onto
                // a freshly-dialled (cold) session reliably drops again, so we
                // warm it with a trivial statement first.
                DB::purge($this->connection);
                $this->warmed = false;
                usleep(250_000);
                $this->warmConnection();
            }
        }

        throw $lastError;
    }

    protected function isTransient(Throwable $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, 'ORA-03113')
            || str_contains($m, 'ORA-03114')
            || str_contains($m, 'ORA-12537')
            || str_contains($m, 'ORA-12571')
            || str_contains($m, 'ORA-25408')
            || stripos($m, 'end-of-file') !== false
            || stripos($m, 'Lost connection') !== false
            || stripos($m, 'no reconnector') !== false;
    }

    /**
     * The ERP table has no separate line-items table exposed here, so the
     * breakdown is synthesised from the header (description + policy + total).
     *
     * @param  array<string,mixed>  $header
     * @return array<int, array<string,mixed>>
     */
    protected function buildLines(array $header): array
    {
        $description = $header['being_payment_for'] ?? $header['narration'] ?? null;
        if ($description === null || trim((string) $description) === '') {
            $description = $header['policy_details'] ?? $header['policy_no'] ?? 'Premium received';
        }

        return [[
            'line_no' => 1,
            'description' => $description,
            'policy_no' => $header['policy_number'] ?? $header['policy_no'] ?? null,
            'period_from' => null,
            'period_to' => null,
            'currency' => $header['currency'] ?? null,
            'amount' => $header['total_amount'] ?? $header['amount'] ?? 0,
        ]];
    }

    /**
     * Build the WHERE clause + binds for a search type. Uses indexed, exact
     * matches for receipt numbers (the table has ~377k rows and the link is
     * fragile, so full-table function scans are avoided where possible).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function searchCondition(string $type, string $term, string $numeric, string $raw): array
    {
        return match ($type) {
            // NOTE: client/policy searches scan the table (no usable index on a
            // free-text LIKE), which is fragile over this link; they are
            // best-effort and may surface a transient error on large scans.
            'policy' => [
                "UPPER(fms_rcts_pkg.get_policy_details(rct_no)) LIKE :t1",
                ['t1' => $term],
            ],
            'client' => [
                "UPPER(rct_received_from) LIKE :t1",
                ['t1' => $term],
            ],
            // receipt: exact match on a SINGLE indexed column. An OR across two
            // columns makes the optimizer fall back to a full-table scan + sort
            // over the fragile link, which reliably drops the session
            // (ORA-03113). So we pick ONE column based on the input shape:
            //   - all digits  -> the internal rct_no (e.g. 604195)
            //   - otherwise   -> the printed branch receipt no (e.g. 1/HO/111640)
            default => $numeric !== '-1'
                ? ["rct_no = :n", ['n' => $numeric]]
                : ["rct_brh_rct_code = :s", ['s' => $raw]],
        };
    }

    /**
     * Normalize an stdClass row to an array with lowercase keys.
     *
     * @return array<string, mixed>
     */
    protected function normalize(object $row): array
    {
        $out = [];
        foreach ((array) $row as $key => $value) {
            $out[mb_strtolower($key)] = $value;
        }

        return $out;
    }
}
