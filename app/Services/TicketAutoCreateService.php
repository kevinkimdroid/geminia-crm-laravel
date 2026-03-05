<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-create tickets based on business rules (e.g. maturity reminders).
 */
class TicketAutoCreateService
{
    public function __construct(
        protected CrmService $crm,
        protected ?ErpClientService $erp = null
    ) {
        $this->erp = $erp ?? (app()->has(ErpClientService::class) ? app(ErpClientService::class) : null);
    }

    /**
     * Create tickets for policies maturing within configured days.
     * Uses erp_clients_cache when available; falls back to ERP HTTP API if cache is empty.
     *
     * @return array{created: int, skipped: int, errors: array<string>}
     */
    public function createMaturityReminderTickets(): array
    {
        $config = config('tickets.auto_maturity', []);
        if (empty($config['enabled'])) {
            return ['created' => 0, 'skipped' => 0, 'errors' => []];
        }

        $daysBefore = (int) ($config['days_before'] ?? 30);
        $assignTo = (int) ($config['assign_to_user_id'] ?? 1);
        $category = $config['category'] ?? 'Policy Document';
        $source = $config['source'] ?? 'Auto';

        $from = now()->format('Y-m-d');
        $to = now()->addDays($daysBefore)->format('Y-m-d');
        $policies = $this->getPoliciesMaturingBetween($from, $to);

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($policies as $row) {
            $policy = trim($row->policy_number ?? '');
            $maturity = $row->maturity ?? null;
            if (!$policy) {
                $skipped++;
                continue;
            }

            try {
                $contactId = $this->resolveOrCreateContact($row);
                if (!$contactId) {
                    $errors[] = "Could not find/create contact for policy {$policy}";
                    $skipped++;
                    continue;
                }

                if ($this->hasExistingMaturityTicket($contactId, $policy)) {
                    $skipped++;
                    continue;
                }

                $clientName = trim($row->life_assur ?? $row->life_assured ?? '') ?: trim(($row->pol_prepared_by ?? '') . ' ' . ($row->intermediary ?? '')) ?: "Policy {$policy}";
                $title = "Maturity reminder: {$clientName} — Policy {$policy} maturing " . ($maturity ? \Carbon\Carbon::parse($maturity)->format('d M Y') : 'soon');
                $description = "Policy {$policy} is maturing " . ($maturity ? "on {$maturity}" : "within {$daysBefore} days") . ".\n\nRelated policy: {$policy}";

                $this->createTicket([
                    'title' => $title,
                    'description' => $description,
                    'contact_id' => $contactId,
                    'assign_to' => $assignTo,
                    'category' => $category,
                    'source' => $source,
                    'priority' => 'Normal',
                    'status' => 'Open',
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('TicketAutoCreateService: maturity ticket failed', ['policy' => $policy, 'error' => $e->getMessage()]);
                $errors[] = "Policy {$policy}: " . $e->getMessage();
                $skipped++;
            }
        }

        if ($created > 0) {
            $this->forgetTicketCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Get policies maturing within N days (for UI listing). Uses config days_before.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getMaturingPoliciesList(int $daysBefore = null): \Illuminate\Support\Collection
    {
        $config = config('tickets.auto_maturity', []);
        $days = $daysBefore ?? (int) ($config['days_before'] ?? 30);
        $from = now()->format('Y-m-d');
        $to = now()->addDays($days)->format('Y-m-d');
        $rows = $this->getPoliciesMaturingBetween($from, $to);
        return collect($rows)->filter(fn ($r) => ! empty(trim($r->policy_number ?? '')));
    }

    /**
     * Get policies maturing between two dates. Uses erp_clients_cache first.
     */
    protected function getPoliciesMaturingBetween(string $from, string $to): iterable
    {
        try {
            if (Schema::hasTable('erp_clients_cache')) {
                $rows = DB::table('erp_clients_cache')
                    ->whereNotNull('maturity')
                    ->whereNotNull('policy_number')
                    ->where('maturity', '>=', $from)
                    ->where('maturity', '<=', $to)
                    ->get();
                if ($rows->isNotEmpty()) {
                    return $rows;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: erp_clients_cache read failed', ['error' => $e->getMessage()]);
        }

        if ($this->erp && config('erp.clients_view_source') === 'erp_http') {
            return $this->getMaturingPoliciesFromHttpApi($from, $to);
        }

        return [];
    }

    /**
     * Fetch maturing policies from ERP HTTP API (filter client-side).
     */
    protected function getMaturingPoliciesFromHttpApi(string $from, string $to): array
    {
        $result = $this->erp->getClientsFromHttpApi(500, 0, null, 15, false);
        if (!empty($result['error']) || empty($result['data'])) {
            return [];
        }
        $maturing = [];
        $data = $result['data'] ?? [];
        if ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->all();
        }
        foreach ($data as $client) {
            $client = is_array($client) ? (object) $client : $client;
            $m = $client->maturity ?? $client->maturity_date ?? null;
            if (!$m) {
                continue;
            }
            $mStr = is_object($m) && method_exists($m, 'format') ? $m->format('Y-m-d') : substr((string) $m, 0, 10);
            if ($mStr && $mStr >= $from && $mStr <= $to) {
                $maturing[] = (object) [
                    'policy_number' => $client->policy_no ?? $client->policy_number ?? null,
                    'maturity' => $mStr,
                    'life_assur' => $client->life_assur ?? $client->client_name ?? null,
                    'pol_prepared_by' => $client->pol_prepared_by ?? null,
                    'intermediary' => $client->intermediary ?? null,
                    'product' => $client->product ?? null,
                    'phone_no' => $client->phone_no ?? $client->mobile ?? null,
                    'email_adr' => $client->email_adr ?? $client->email ?? null,
                ];
            }
        }
        return $maturing;
    }

    protected function resolveOrCreateContact(object $row): ?int
    {
        $policy = trim($row->policy_number ?? '');
        if (!$policy) {
            return null;
        }

        $contact = $this->crm->findContactByPolicyNumber($policy);
        if ($contact) {
            return (int) $contact->contactid;
        }

        $erpClient = [
            'policy_no' => $policy,
            'policy_number' => $policy,
            'name' => trim($row->life_assur ?? $row->life_assured ?? '') ?: trim(($row->pol_prepared_by ?? '') . ' ' . ($row->intermediary ?? '')) ?: "Policy {$policy}",
            'client_name' => trim($row->life_assur ?? $row->life_assured ?? '') ?: trim(($row->pol_prepared_by ?? '') . ' ' . ($row->intermediary ?? '')),
            'pol_prepared_by' => $row->pol_prepared_by ?? null,
            'intermediary' => $row->intermediary ?? null,
            'phone_no' => $row->phone_no ?? $row->mobile ?? null,
            'mobile' => $row->phone_no ?? $row->mobile ?? null,
            'phone' => $row->phone_no ?? $row->mobile ?? null,
            'email_adr' => $row->email_adr ?? null,
            'email' => $row->email_adr ?? null,
        ];
        $parts = explode(' ', $erpClient['name'], 2);
        $erpClient['first_name'] = $parts[0] ?? $erpClient['name'];
        $erpClient['last_name'] = $parts[1] ?? '';

        return $this->crm->createContactFromErpClient($erpClient);
    }

    protected function hasExistingMaturityTicket(int $contactId, string $policy): bool
    {
        $term = '%' . $policy . '%';
        $cutoff = now()->subDays(35)->format('Y-m-d');
        return DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('t.contact_id', $contactId)
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response'])
            ->where(function ($q) use ($term) {
                $q->where('t.title', 'like', '%Maturity%')
                    ->orWhere('t.title', 'like', '%maturity%');
            })
            ->where(function ($q) use ($term) {
                $q->where('t.title', 'like', $term)
                    ->orWhere('e.description', 'like', $term);
            })
            ->where('e.createdtime', '>=', $cutoff)
            ->exists();
    }

    protected function createTicket(array $data): int
    {
        $userId = 1;
        $ownerId = (int) ($data['assign_to'] ?? 1);
        $description = $data['description'] ?? '';
        $now = now()->format('Y-m-d H:i:s');
        $id = (int) DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

        DB::connection('vtiger')->transaction(function () use ($data, $userId, $ownerId, $description, $now, $id) {
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $id,
                'smcreatorid' => $userId,
                'smownerid' => $ownerId,
                'modifiedby' => $userId,
                'setype' => 'HelpDesk',
                'description' => $description,
                'createdtime' => $now,
                'modifiedtime' => $now,
                'viewedtime' => null,
                'status' => 1,
                'version' => 0,
                'presence' => 1,
                'deleted' => 0,
                'smgroupid' => 0,
                'source' => $data['source'] ?? 'Auto',
                'label' => $data['title'],
            ]);

            DB::connection('vtiger')->table('vtiger_troubletickets')->insert([
                'ticketid' => $id,
                'ticket_no' => 'TT' . $id,
                'title' => $data['title'],
                'status' => $data['status'] ?? 'Open',
                'priority' => $data['priority'] ?? 'Normal',
                'severity' => null,
                'category' => $data['category'] ?? 'Other',
                'contact_id' => $data['contact_id'],
                'product_id' => null,
                'parent_id' => null,
                'hours' => null,
                'days' => null,
            ]);
        });

        return $id;
    }

    private function forgetTicketCaches(): void
    {
        Cache::forget('geminia_ticket_counts_by_status');
        Cache::forget('geminia_tickets_count');
        Cache::forget('tickets_list_default');
        foreach (['Open', 'In_Progress', 'Wait_For_Response', 'Closed', 'Unassigned'] as $slug) {
            Cache::forget('tickets_list_' . $slug);
        }
        Cache::forget('geminia_dashboard_stats');
    }
}
