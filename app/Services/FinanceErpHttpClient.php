<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use stdClass;

/**
 * Loads Finance (Oracle FMS cheques) via erp-clients-api when PHP OCI8 is not installed on the CRM server.
 */
class FinanceErpHttpClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('erp.finance_http_base')) !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('erp.finance_http_base'), '/');
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function http()
    {
        $token = trim((string) config('erp.finance_http_token'));
        $req = Http::withOptions(['connect_timeout' => 3])->timeout(90)->acceptJson();
        if ($token !== '') {
            $req = $req->withToken($token);
        }

        return $req;
    }

    /**
     * @return array{payments: LengthAwarePaginator, stats: array<string, float|int>, sourceOptions: Collection<int, stdClass>, erpError: ?string}
     */
    public function fetchPaymentsIndex(Request $request): array
    {
        $params = [
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => 20,
            'stats_date' => now()->toDateString(),
        ];
        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $params['search'] = $search;
        }
        $dateFrom = trim((string) $request->get('date_from', ''));
        if ($dateFrom !== '') {
            $params['date_from'] = $dateFrom;
        }
        $dateTo = trim((string) $request->get('date_to', ''));
        if ($dateTo !== '') {
            $params['date_to'] = $dateTo;
        }
        if ($request->filled('source')) {
            $params['source'] = (int) $request->get('source');
        }

        $response = $this->http()->get($this->baseUrl() . '/finance/cheques', $params);
        if (! $response->successful()) {
            $msg = $response->json('error') ?? ('ERP API HTTP ' . $response->status());

            return [
                'payments' => $this->emptyPaginator($request, 20),
                'stats' => [
                    'total_count' => 0,
                    'total_amount' => 0.0,
                    'today_count' => 0,
                    'distinct_payees' => 0,
                ],
                'sourceOptions' => collect(),
                'erpError' => is_string($msg) ? $msg : 'Finance ERP API request failed.',
            ];
        }

        $body = $response->json();
        $rows = $body['data'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }
        $items = collect($rows)->map(fn ($row) => (object) (is_array($row) ? $row : (array) $row));
        $total = (int) ($body['total'] ?? 0);
        $perPage = (int) ($body['per_page'] ?? 20);
        $currentPage = (int) ($body['current_page'] ?? 1);
        $stats = $body['stats'] ?? [];
        $payments = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            max(1, $currentPage),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $sourceOpts = collect($body['source_options'] ?? [])
            ->map(function ($opt) {
                $o = is_array($opt) ? $opt : (array) $opt;
                $x = new stdClass;
                $x->source_code = $o['source_code'] ?? null;
                $x->source_name = $o['source_name'] ?? null;

                return $x;
            });

        return [
            'payments' => $payments,
            'stats' => [
                'total_count' => (int) ($stats['total_count'] ?? $total),
                'total_amount' => (float) ($stats['total_amount'] ?? 0),
                'today_count' => (int) ($stats['today_count'] ?? 0),
                'distinct_payees' => (int) ($stats['distinct_payees'] ?? 0),
            ],
            'sourceOptions' => $sourceOpts,
            'erpError' => null,
        ];
    }

    /**
     * @return array{record: ?stdClass, error: ?string}
     */
    public function fetchChequeForTicket(string $ref, int $source): array
    {
        $response = $this->http()->get($this->baseUrl() . '/finance/cheques/lookup', [
            'ref' => $ref,
            'source' => $source,
        ]);
        if (! $response->successful()) {
            $msg = $response->json('error');

            return [
                'record' => null,
                'error' => is_string($msg) ? $msg : ('ERP API HTTP ' . $response->status()),
            ];
        }
        $data = $response->json('data');
        if ($data === null) {
            return ['record' => null, 'error' => null];
        }
        if (! is_array($data)) {
            return ['record' => null, 'error' => null];
        }

        return ['record' => (object) $data, 'error' => null];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, loadError: ?string}
     */
    public function fetchAgencyAdvances(int $year): array
    {
        $response = $this->http()->get($this->baseUrl() . '/finance/agency-advances', [
            'year' => $year,
        ]);
        if (! $response->successful()) {
            $msg = $response->json('error') ?? ('ERP API HTTP ' . $response->status());

            return ['rows' => [], 'loadError' => is_string($msg) ? $msg : 'Agency advances request failed.'];
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            return ['rows' => [], 'loadError' => null];
        }
        $rows = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $a = array_change_key_case($row, CASE_LOWER);
            $rows[] = [
                'cqr_no' => isset($a['cqr_no']) ? trim((string) $a['cqr_no']) : '',
                'cqr_payee' => isset($a['cqr_payee']) ? trim((string) $a['cqr_payee']) : '',
                'cqr_ref_date' => $a['cqr_ref_date'] ?? null,
                'cqr_bbr_code' => isset($a['cqr_bbr_code']) ? trim((string) $a['cqr_bbr_code']) : '',
                'cqr_cpy_acc_no' => isset($a['cqr_cpy_acc_no']) ? trim((string) $a['cqr_cpy_acc_no']) : '',
                'agn_bank_acc_no' => isset($a['agn_bank_acc_no']) ? trim((string) $a['agn_bank_acc_no']) : '',
                'agn_bbr_code' => isset($a['agn_bbr_code']) ? trim((string) $a['agn_bbr_code']) : '',
            ];
        }

        return ['rows' => $rows, 'loadError' => null];
    }

    /**
     * @return array{rows: Collection<int, object>, count: int, error: ?string}
     */
    public function fetchAgencyAdvancesForNotify(int $year): array
    {
        $response = $this->http()->get($this->baseUrl() . '/finance/agency-advances', [
            'year' => $year,
        ]);
        if (! $response->successful()) {
            $msg = $response->json('error');

            return [
                'rows' => collect(),
                'count' => 0,
                'error' => is_string($msg) ? $msg : ('HTTP ' . $response->status()),
            ];
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            return ['rows' => collect(), 'count' => 0, 'error' => null];
        }
        $objs = collect($data)->map(fn ($row) => (object) (is_array($row) ? $row : (array) $row));

        return ['rows' => $objs, 'count' => $objs->count(), 'error' => null];
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            max(1, (int) $request->input('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
