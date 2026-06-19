<?php

namespace App\Services\Receipts;

interface ReceiptDataSource
{
    /**
     * Search for receipt headers.
     *
     * @param  string  $query  The search term (receipt no, policy no, or client name).
     * @param  string  $type   One of: receipt, policy, client.
     * @return array<int, array<string, mixed>>  List of header rows (lowercase keys).
     */
    public function search(string $query, string $type): array;

    /**
     * Fetch a single receipt (header + line items) by receipt number.
     *
     * Receipts in the source system are keyed by a composite of receipt number
     * (rct_no) and branch code (rct_brh_code), so an optional branch may be
     * supplied to disambiguate. Implementations that do not need it may ignore it.
     *
     * @return array{header: array<string,mixed>, lines: array<int, array<string,mixed>>}|null
     */
    public function find(string $receiptNo, ?string $branch = null): ?array;
}
