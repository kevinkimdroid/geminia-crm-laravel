<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpClientsImportController extends Controller
{
    /**
     * Import clients into erp_clients_cache.
     * POST /api/admin/erp-clients-import
     * Body: { "replace": true, "clients": [ { "policy_number", "product", ... }, ... ] }
     * - replace=true: truncate and insert all (full sync)
     * - replace=false: upsert by policy_number
     * Auth: X-API-Key or Authorization: Bearer <ERP_SYNC_TOKEN>
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'replace' => 'sometimes|boolean',
            'clients' => 'required|array',
            'clients.*' => 'array',
            'clients.*.policy_number' => 'nullable|string|max:64',
            'clients.*.email_adr' => 'nullable|string|max:255',
            'clients.*.product' => 'nullable|string|max:255',
            'clients.*.pol_prepared_by' => 'nullable|string|max:255',
            'clients.*.intermediary' => 'nullable|string|max:255',
            'clients.*.status' => 'nullable|string|max:64',
            'clients.*.kra_pin' => 'nullable|string|max:64',
            'clients.*.prp_dob' => 'nullable|string|max:20',
            'clients.*.maturity' => 'nullable|string|max:20',
            'clients.*.paid_mat_amt' => 'nullable|numeric',
            'clients.*.checkoff' => 'nullable|string|max:64',
            'clients.*.effective_date' => 'nullable|string|max:20',
        ]);

        $replace = $payload['replace'] ?? false;
        $clients = $payload['clients'];
        $syncedAt = now();

        if ($replace && count($clients) > 0) {
            DB::table('erp_clients_cache')->truncate();
            \Illuminate\Support\Facades\Cache::forget('erp_clients_cache_total');
        }

        $inserted = 0;
        $updated = 0;
        $hasEmailColumn = Schema::hasColumn('erp_clients_cache', 'email_adr');

        if ($replace) {
            // Bulk insert: 10–50x faster than row-by-row
            $toInsert = [];
            foreach ($clients as $row) {
                $record = [
                    'policy_number' => $row['policy_number'] ?? null,
                    'product' => $row['product'] ?? null,
                    'pol_prepared_by' => $row['pol_prepared_by'] ?? null,
                    'intermediary' => $row['intermediary'] ?? null,
                    'status' => $row['status'] ?? null,
                    'kra_pin' => $row['kra_pin'] ?? null,
                    'prp_dob' => $this->parseDate($row['prp_dob'] ?? null),
                    'maturity' => $this->parseDate($row['maturity'] ?? null),
                    'paid_mat_amt' => isset($row['paid_mat_amt']) ? (float) $row['paid_mat_amt'] : null,
                    'checkoff' => $row['checkoff'] ?? null,
                    'effective_date' => $this->parseDate($row['effective_date'] ?? null),
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
                if ($hasEmailColumn) {
                    $record['email_adr'] = ! empty($row['email_adr']) ? (string) $row['email_adr'] : null;
                }
                $toInsert[] = $record;
            }
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table('erp_clients_cache')->insert($chunk);
                $inserted += count($chunk);
            }
        } else {
            foreach ($clients as $row) {
                $policyNumber = $row['policy_number'] ?? null;
                $record = [
                    'policy_number' => $policyNumber,
                    'product' => $row['product'] ?? null,
                    'pol_prepared_by' => $row['pol_prepared_by'] ?? null,
                    'intermediary' => $row['intermediary'] ?? null,
                    'status' => $row['status'] ?? null,
                    'kra_pin' => $row['kra_pin'] ?? null,
                    'prp_dob' => $this->parseDate($row['prp_dob'] ?? null),
                    'maturity' => $this->parseDate($row['maturity'] ?? null),
                    'paid_mat_amt' => isset($row['paid_mat_amt']) ? (float) $row['paid_mat_amt'] : null,
                    'checkoff' => $row['checkoff'] ?? null,
                    'effective_date' => $this->parseDate($row['effective_date'] ?? null),
                    'synced_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
                if ($hasEmailColumn) {
                    $record['email_adr'] = ! empty($row['email_adr']) ? (string) $row['email_adr'] : null;
                }
                $existing = $policyNumber
                    ? DB::table('erp_clients_cache')->where('policy_number', $policyNumber)->first()
                    : null;
                if ($existing) {
                    DB::table('erp_clients_cache')->where('id', $existing->id)->update($record);
                    $updated++;
                } else {
                    $record['created_at'] = $syncedAt;
                    DB::table('erp_clients_cache')->insert($record);
                    $inserted++;
                }
            }
            \Illuminate\Support\Facades\Cache::forget('erp_clients_cache_total');
        }

        return response()->json([
            'success' => true,
            'message' => $replace
                ? "Replaced cache with {$inserted} clients."
                : "Imported {$inserted} new, updated {$updated}.",
            'imported' => $inserted,
            'updated' => $updated,
        ]);
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            $dt = \Carbon\Carbon::parse($value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
