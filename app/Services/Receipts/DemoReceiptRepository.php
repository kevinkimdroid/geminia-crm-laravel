<?php

namespace App\Services\Receipts;

use Illuminate\Support\Str;

/**
 * In-memory sample receipts used when config('receipt.demo') is true.
 * Lets the full search -> preview -> reprint flow be exercised before the
 * Oracle schema is connected. Mirrors the shape returned by the Oracle repo.
 */
class DemoReceiptRepository implements ReceiptDataSource
{
    public function search(string $query, string $type): array
    {
        $term = mb_strtolower(trim($query));

        return collect($this->all())
            ->map(fn ($r) => $r['header'])
            ->filter(function (array $h) use ($term, $type) {
                $field = match ($type) {
                    'policy' => $h['policy_no'],
                    'client' => $h['client_name'],
                    default => $h['receipt_no'],
                };

                return $term === '' || Str::contains(mb_strtolower((string) $field), $term);
            })
            ->values()
            ->all();
    }

    public function find(string $receiptNo, ?string $branch = null): ?array
    {
        return $this->all()[mb_strtoupper($receiptNo)] ?? null;
    }

    /**
     * @return array<string, array{header: array<string,mixed>, lines: array<int, array<string,mixed>>}>
     */
    protected function all(): array
    {
        return [
            'LF-2026-000123' => [
                'header' => [
                    'receipt_no' => 'LF-2026-000123',
                    'receipt_date' => '2026-05-14',
                    'policy_no' => 'POL-558721',
                    'client_name' => 'JANE WANJIKU MWANGI',
                    'received_from' => 'JANE WANJIKU MWANGI',
                    'agent_name' => 'ACME INSURANCE AGENCY',
                    'payment_mode' => 'M-PESA',
                    'reference_no' => 'QGR4H7T2X5',
                    'currency' => 'KES',
                    'amount' => 45000.00,
                    'amount_in_words' => 'Forty Five Thousand Shillings Only',
                    'branch' => 'NAIROBI HEAD OFFICE',
                    'product_name' => 'GEMINIA EDUCATION PLAN',
                    'narration' => 'Annual premium payment',
                    'cashier' => 'P. OTIENO',
                    'status' => 'CONFIRMED',
                ],
                'lines' => [
                    [
                        'line_no' => 1,
                        'description' => 'Annual Premium - Education Plan',
                        'policy_no' => 'POL-558721',
                        'period_from' => '2026-06-01',
                        'period_to' => '2027-05-31',
                        'currency' => 'KES',
                        'amount' => 42000.00,
                    ],
                    [
                        'line_no' => 2,
                        'description' => 'Policy Administration Fee',
                        'policy_no' => 'POL-558721',
                        'period_from' => '2026-06-01',
                        'period_to' => '2027-05-31',
                        'currency' => 'KES',
                        'amount' => 3000.00,
                    ],
                ],
            ],
            'LF-2026-000456' => [
                'header' => [
                    'receipt_no' => 'LF-2026-000456',
                    'receipt_date' => '2026-06-02',
                    'policy_no' => 'POL-771204',
                    'client_name' => 'DAVID KIPROTICH KOECH',
                    'received_from' => 'DAVID KIPROTICH KOECH',
                    'agent_name' => 'BROKER LINK LTD',
                    'payment_mode' => 'BANK TRANSFER',
                    'reference_no' => 'FT26153998001',
                    'currency' => 'KES',
                    'amount' => 120000.00,
                    'amount_in_words' => 'One Hundred and Twenty Thousand Shillings Only',
                    'branch' => 'MOMBASA BRANCH',
                    'product_name' => 'GEMINIA WHOLE LIFE COVER',
                    'narration' => 'Semi-annual premium',
                    'cashier' => 'M. ACHIENG',
                    'status' => 'CONFIRMED',
                ],
                'lines' => [
                    [
                        'line_no' => 1,
                        'description' => 'Semi-Annual Premium - Whole Life',
                        'policy_no' => 'POL-771204',
                        'period_from' => '2026-06-01',
                        'period_to' => '2026-11-30',
                        'currency' => 'KES',
                        'amount' => 120000.00,
                    ],
                ],
            ],
        ];
    }
}
