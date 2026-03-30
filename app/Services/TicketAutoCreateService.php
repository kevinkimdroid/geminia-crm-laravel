<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
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

        $useBands = ! empty($config['use_horizon_bands']);
        $bands = $useBands ? $this->buildMaturityHorizonBands($config) : null;

        $created = 0;
        $skipped = 0;
        $errors = [];

        if ($bands !== null && $bands !== []) {
            foreach ($bands as $band) {
                $policies = $this->getPoliciesInMaturityBand($band['lower_exclusive_ymd'], $band['upper_inclusive_ymd']);
                foreach ($policies as $row) {
                    $result = $this->createOneMaturityTicket(
                        $row,
                        $config,
                        (int) $band['days_before'],
                        (string) $band['label'],
                        (string) ($band['priority'] ?? 'Normal'),
                        true,
                        $band['assign_to_user_id'] ?? null,
                        isset($band['category']) ? (string) $band['category'] : null,
                    );
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    } elseif (is_string($result) && str_starts_with($result, 'err:')) {
                        $errors[] = substr($result, 4);
                        $skipped++;
                    }
                }
            }
        } else {
            $daysBefore = (int) ($config['days_before'] ?? 30);
            $from = now()->format('Y-m-d');
            $to = now()->addDays($daysBefore)->format('Y-m-d');
            $policies = $this->getPoliciesMaturingBetween($from, $to);

            foreach ($policies as $row) {
                $result = $this->createOneMaturityTicket(
                    $row,
                    $config,
                    $daysBefore,
                    'Maturity reminder',
                    'Normal',
                    false,
                    null,
                    null
                );
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } elseif (is_string($result) && str_starts_with($result, 'err:')) {
                    $errors[] = substr($result, 4);
                    $skipped++;
                }
            }
        }

        if ($created > 0) {
            $this->forgetTicketCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return array<int, array{days_before: int, label: string, priority: string, lower_exclusive_ymd: ?string, upper_inclusive_ymd: string, assign_to_user_id: ?int, category: ?string}>
     */
    public function buildMaturityHorizonBands(array $config): array
    {
        $horizons = $config['horizons'] ?? [];
        if ($horizons === []) {
            $d = (int) ($config['days_before'] ?? 30);

            return [[
                'days_before' => $d,
                'label' => 'Maturity reminder',
                'priority' => 'Normal',
                'lower_exclusive_ymd' => null,
                'upper_inclusive_ymd' => now()->addDays($d)->format('Y-m-d'),
                'assign_to_user_id' => null,
                'category' => null,
            ]];
        }

        usort($horizons, fn ($a, $b) => ((int) ($b['days_before'] ?? 0)) <=> ((int) ($a['days_before'] ?? 0)));

        $out = [];
        foreach ($horizons as $i => $h) {
            $upper = (int) ($h['days_before'] ?? 30);
            $next = $horizons[$i + 1] ?? null;
            $lowerDays = $next ? (int) ($next['days_before'] ?? 0) : 0;
            $lowerExclusive = $lowerDays > 0
                ? now()->addDays($lowerDays)->format('Y-m-d')
                : null;

            $out[] = [
                'days_before' => $upper,
                'label' => (string) ($h['label'] ?? "{$upper}-day renewal reminder"),
                'priority' => (string) ($h['priority'] ?? 'Normal'),
                'lower_exclusive_ymd' => $lowerExclusive,
                'upper_inclusive_ymd' => now()->addDays($upper)->format('Y-m-d'),
                'assign_to_user_id' => isset($h['assign_to_user_id']) ? (int) $h['assign_to_user_id'] : null,
                'category' => $h['category'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Policies with maturity in (lowerExclusive, upperInclusive], or [today, upperInclusive] when lower is null.
     *
     * @return list<object>
     */
    protected function getPoliciesInMaturityBand(?string $lowerExclusiveYmd, string $upperInclusiveYmd): array
    {
        $today = now()->format('Y-m-d');

        foreach (['maturities_cache', 'erp_clients_cache'] as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $q = DB::table($table)
                    ->whereNotNull('maturity')
                    ->whereNotNull('policy_number')
                    ->where('maturity', '<=', $upperInclusiveYmd);
                if ($lowerExclusiveYmd !== null) {
                    $q->where('maturity', '>', $lowerExclusiveYmd);
                } else {
                    $q->where('maturity', '>=', $today);
                }
                $rows = $q->get();
                if ($rows->isNotEmpty()) {
                    return $rows->all();
                }
            } catch (\Throwable $e) {
                Log::warning('TicketAutoCreateService: maturity band read failed', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }

        if ($this->erp && config('erp.clients_view_source') === 'erp_http') {
            $all = $this->getMaturingPoliciesFromHttpApi($today, $upperInclusiveYmd, null);
            $filtered = array_values(array_filter($all, function ($r) use ($lowerExclusiveYmd, $upperInclusiveYmd, $today) {
                $mRaw = $r->maturity ?? $r->maturity_date ?? null;
                if (! $mRaw) {
                    return false;
                }
                $m = \Carbon\Carbon::parse($mRaw)->format('Y-m-d');
                if ($m < $today || $m > $upperInclusiveYmd) {
                    return false;
                }
                if ($lowerExclusiveYmd !== null && $m <= $lowerExclusiveYmd) {
                    return false;
                }

                return true;
            }));

            return $filtered;
        }

        return [];
    }

    /**
     * @return 'created'|'skipped'|'err:string'
     */
    protected function createOneMaturityTicket(
        object $row,
        array $config,
        int $daysBefore,
        string $label,
        string $priority,
        bool $horizonMode,
        ?int $bandAssignToUserId = null,
        ?string $bandCategory = null
    ): string {
        $policy = trim($row->policy_number ?? '');
        $maturity = $row->maturity ?? null;
        if (! $policy) {
            return 'skipped';
        }

        $categoryDefault = $bandCategory !== null && $bandCategory !== ''
            ? $bandCategory
            : ($config['category'] ?? 'Policy Document');
        $source = $config['source'] ?? 'Auto';
        $baseAssign = (int) ($config['assign_to_user_id'] ?? 1);
        $assignDefault = ($bandAssignToUserId !== null && $bandAssignToUserId > 0)
            ? $bandAssignToUserId
            : $baseAssign;

        try {
            $contactId = $this->resolveOrCreateContact($row);
            if (! $contactId) {
                return 'err:Could not find/create contact for policy '.$policy;
            }

            if ($horizonMode) {
                if ($this->hasExistingMaturityTicketForHorizon($contactId, $policy, $daysBefore)) {
                    return 'skipped';
                }
            } elseif ($this->hasExistingMaturityTicket($contactId, $policy)) {
                return 'skipped';
            }

            $clientName = trim($row->life_assur ?? $row->life_assured ?? '') ?: trim(($row->pol_prepared_by ?? '').' '.($row->intermediary ?? '')) ?: "Policy {$policy}";
            $product = trim((string) ($row->product ?? ''));

            $assignTo = $this->resolveAssigneeForMaturity($assignDefault, $product);

            if ($horizonMode) {
                $title = "Maturity ({$daysBefore}d): {$clientName} — Policy {$policy} maturing ".($maturity ? \Carbon\Carbon::parse($maturity)->format('d M Y') : 'soon');
                $description = "Policy {$policy} is maturing ".($maturity ? "on {$maturity}" : "within {$daysBefore} days").".\n";
                $description .= "Horizon: {$label} ({$daysBefore} days).\n\nRelated policy: {$policy}";
            } else {
                $d = (int) ($config['days_before'] ?? 30);
                $title = "Maturity reminder: {$clientName} — Policy {$policy} maturing ".($maturity ? \Carbon\Carbon::parse($maturity)->format('d M Y') : 'soon');
                $description = "Policy {$policy} is maturing ".($maturity ? "on {$maturity}" : "within {$d} days").".\n\nRelated policy: {$policy}";
            }

            $ticketId = $this->createTicket([
                'title' => $title,
                'description' => $description,
                'contact_id' => $contactId,
                'assign_to' => $assignTo,
                'category' => $categoryDefault,
                'source' => $source,
                'priority' => $priority,
                'status' => 'Open',
            ]);

            try {
                app(TicketNotificationService::class)->sendTicketCreatedNotification(
                    $ticketId,
                    'TT'.$ticketId,
                    $title,
                    $assignTo,
                    $contactId ?: null,
                    $policy ?: null,
                    config('tickets.notify_on_creation.notify_contact', false)
                );
            } catch (\Throwable $notifyEx) {
                Log::warning('TicketAutoCreateService: creation notification failed', ['ticket_id' => $ticketId, 'error' => $notifyEx->getMessage()]);
            }

            return 'created';
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: maturity ticket failed', ['policy' => $policy, 'error' => $e->getMessage()]);

            return 'err:Policy '.$policy.': '.$e->getMessage();
        }
    }

    protected function resolveAssigneeForMaturity(int $defaultUserId, string $product): int
    {
        $map = config('tickets.auto_maturity.product_assignees', []);
        if ($product === '' || ! is_array($map) || $map === []) {
            return $defaultUserId;
        }

        if (isset($map[$product])) {
            return (int) $map[$product];
        }

        foreach ($map as $key => $userId) {
            if (is_string($key) && strcasecmp($key, $product) === 0) {
                return (int) $userId;
            }
        }

        $productLower = strtolower($product);
        foreach ($map as $key => $userId) {
            if (! is_string($key)) {
                continue;
            }
            $k = strtolower($key);
            if ($k !== '' && str_contains($productLower, $k)) {
                return (int) $userId;
            }
        }

        return $defaultUserId;
    }

    protected function hasExistingMaturityTicketForHorizon(int $contactId, string $policy, int $daysBefore): bool
    {
        $marker = '('.$daysBefore.'d)';
        $term = '%'.$policy.'%';
        $cutoff = now()->subDays(120)->format('Y-m-d');

        return DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('t.contact_id', $contactId)
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response'])
            ->where('t.title', 'like', '%'.$marker.'%')
            ->where(function ($q) use ($term) {
                $q->where('t.title', 'like', $term)
                    ->orWhere('e.description', 'like', $term);
            })
            ->where('e.createdtime', '>=', $cutoff)
            ->exists();
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
     * Get paginated maturing policies with search and sort. For UI listing.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getMaturingPoliciesPaginated(
        int $daysBefore,
        ?string $search,
        ?string $sortBy,
        ?string $sortDir,
        int $perPage,
        ?string $product = null,
        ?string $renewalStatus = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $from = now()->format('Y-m-d');
        $to = now()->addDays($daysBefore)->format('Y-m-d');
        $renewalStatus = trim((string) ($renewalStatus ?? ''));

        try {
            if (Schema::hasTable('maturities_cache')) {
                $hasTracking = $this->maturityRenewalTrackingTableExists();
                $query = DB::table('maturities_cache as mc')
                    ->whereNotNull('mc.maturity')
                    ->whereNotNull('mc.policy_number')
                    ->where('mc.maturity', '>=', $from)
                    ->where('mc.maturity', '<=', $to);

                if ($hasTracking) {
                    $query->leftJoin('maturity_renewal_tracking as mrt', function ($j) {
                        $j->on('mrt.policy_number', '=', 'mc.policy_number')
                            ->on('mrt.maturity', '=', 'mc.maturity');
                    })->selectRaw('mc.*, mrt.renewal_status, mrt.renewal_date, mrt.notes as renewal_notes');
                } else {
                    $query->select('mc.*');
                }

                $this->applyRenewalStatusFilter($query, $renewalStatus, $hasTracking);

                $productTrim = trim($product ?? '');
                if ($productTrim !== '') {
                    $query->where('mc.product', $productTrim);
                }

                $search = trim($search ?? '');
                if ($search !== '') {
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('mc.policy_number', 'like', $term)
                            ->orWhere('mc.life_assured', 'like', $term)
                            ->orWhere('mc.product', 'like', $term);
                    });
                }

                $allowedSort = ['maturity', 'policy_number', 'product', 'life_assured'];
                if ($hasTracking) {
                    $allowedSort[] = 'renewal_status';
                }
                $sortCol = in_array($sortBy ?? '', $allowedSort) ? $sortBy : 'maturity';
                if ($sortCol === 'renewal_status' && ! $hasTracking) {
                    $sortCol = 'maturity';
                }
                $dir = strtolower($sortDir ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $sortPrefix = ($hasTracking && $sortCol === 'renewal_status') ? 'mrt.' : 'mc.';
                $query->orderBy($sortPrefix.$sortCol, $dir);

                $paginated = $query->paginate($perPage)->withQueryString();
                if ($paginated->total() > 0) {
                    return $paginated;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: maturities_cache read failed', ['error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('erp_clients_cache')) {
                $hasTracking = $this->maturityRenewalTrackingTableExists();
                $query = DB::table('erp_clients_cache as ec')
                    ->whereNotNull('ec.maturity')
                    ->whereNotNull('ec.policy_number')
                    ->where('ec.maturity', '>=', $from)
                    ->where('ec.maturity', '<=', $to);

                if ($hasTracking) {
                    $query->leftJoin('maturity_renewal_tracking as mrt', function ($j) {
                        $j->on('mrt.policy_number', '=', 'ec.policy_number')
                            ->on('mrt.maturity', '=', 'ec.maturity');
                    })->selectRaw('ec.*, mrt.renewal_status, mrt.renewal_date, mrt.notes as renewal_notes');
                } else {
                    $query->select('ec.*');
                }

                $this->applyRenewalStatusFilter($query, $renewalStatus, $hasTracking);

                $productTrim = trim($product ?? '');
                if ($productTrim !== '') {
                    $query->where('ec.product', $productTrim);
                }

                $search = trim($search ?? '');
                if ($search !== '') {
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('ec.policy_number', 'like', $term)
                            ->orWhere('ec.product', 'like', $term)
                            ->orWhere('ec.pol_prepared_by', 'like', $term)
                            ->orWhere('ec.intermediary', 'like', $term);
                        if (Schema::hasColumn('erp_clients_cache', 'life_assured')) {
                            $q->orWhere('ec.life_assured', 'like', $term);
                        }
                    });
                }

                $allowedSort = ['maturity', 'policy_number', 'product', 'pol_prepared_by'];
                if (Schema::hasColumn('erp_clients_cache', 'life_assured')) {
                    $allowedSort[] = 'life_assured';
                }
                if ($hasTracking) {
                    $allowedSort[] = 'renewal_status';
                }
                $sortCol = in_array($sortBy ?? '', $allowedSort) ? $sortBy : 'maturity';
                if ($sortCol === 'renewal_status' && ! $hasTracking) {
                    $sortCol = 'maturity';
                }
                $dir = strtolower($sortDir ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $sortPrefix = ($hasTracking && $sortCol === 'renewal_status') ? 'mrt.' : 'ec.';
                $query->orderBy($sortPrefix.$sortCol, $dir);

                return $query->paginate($perPage)->withQueryString();
            }
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: erp_clients_cache paginated read failed', ['error' => $e->getMessage()]);
        }

        if ($this->erp && config('erp.clients_view_source') === 'erp_http') {
            $all = $this->getMaturingPoliciesFromHttpApi($from, $to, $product);
            $all = array_values(array_filter($all, fn ($r) => ! empty(trim($r->policy_number ?? ''))));

            $search = trim($search ?? '');
            if ($search !== '') {
                $term = strtolower($search);
                $all = array_values(array_filter($all, function ($r) use ($term) {
                    $policy = strtolower($r->policy_number ?? '');
                    $life = strtolower($r->life_assur ?? $r->life_assured ?? '');
                    $productName = strtolower($r->product ?? '');

                    return str_contains($policy, $term) || str_contains($life, $term) || str_contains($productName, $term);
                }));
            }

            $this->attachRenewalTrackingToPolicyRows($all);
            $all = $this->filterPolicyRowsByRenewalStatus($all, $renewalStatus);

            $hasTracking = $this->maturityRenewalTrackingTableExists();
            $allowedSort = ['maturity', 'policy_number', 'product', 'life_assured'];
            if ($hasTracking) {
                $allowedSort[] = 'renewal_status';
            }
            $sortCol = in_array($sortBy ?? '', $allowedSort) ? $sortBy : 'maturity';
            if ($sortCol === 'renewal_status' && ! $hasTracking) {
                $sortCol = 'maturity';
            }
            $dir = strtolower($sortDir ?? 'asc') === 'desc' ? 'desc' : 'asc';

            usort($all, function ($a, $b) use ($sortCol, $dir, $hasTracking) {
                $va = ($hasTracking && $sortCol === 'renewal_status')
                    ? ($a->renewal_status ?? '')
                    : ($a->{$sortCol} ?? $a->life_assured ?? $a->life_assur ?? '');
                $vb = ($hasTracking && $sortCol === 'renewal_status')
                    ? ($b->renewal_status ?? '')
                    : ($b->{$sortCol} ?? $b->life_assured ?? $b->life_assur ?? '');
                $cmp = strcmp((string) $va, (string) $vb);

                return $dir === 'desc' ? -$cmp : $cmp;
            });

            $page = (int) request()->get('page', 1);
            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $items = array_slice($all, $offset, $perPage);

            return new LengthAwarePaginator(
                $items,
                count($all),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        return new LengthAwarePaginator([], 0, $perPage, 1);
    }

    protected function maturityRenewalTrackingTableExists(): bool
    {
        try {
            return Schema::connection((string) config('database.default'))->hasTable('maturity_renewal_tracking');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function applyRenewalStatusFilter($query, string $renewalStatus, bool $hasTracking): void
    {
        if ($renewalStatus === '' || ! $hasTracking) {
            return;
        }
        if ($renewalStatus === 'pending') {
            $query->where(function ($q) {
                $q->whereNull('mrt.renewal_status')->orWhere('mrt.renewal_status', 'pending');
            });

            return;
        }
        $query->where('mrt.renewal_status', $renewalStatus);
    }

    /**
     * @param  array<int, object>  $rows
     */
    protected function attachRenewalTrackingToPolicyRows(array &$rows): void
    {
        if ($rows === [] || ! $this->maturityRenewalTrackingTableExists()) {
            return;
        }
        $policies = [];
        foreach ($rows as $r) {
            $p = trim($r->policy_number ?? '');
            if ($p !== '') {
                $policies[] = $p;
            }
        }
        $policies = array_values(array_unique($policies));
        if ($policies === []) {
            return;
        }
        $byKey = [];
        foreach (array_chunk($policies, 500) as $chunk) {
            $found = DB::connection((string) config('database.default'))->table('maturity_renewal_tracking')->whereIn('policy_number', $chunk)->get();
            foreach ($found as $t) {
                $k = trim($t->policy_number).'|'.\Carbon\Carbon::parse($t->maturity)->format('Y-m-d');
                $byKey[$k] = $t;
            }
        }
        foreach ($rows as $r) {
            $p = trim($r->policy_number ?? '');
            $m = $r->maturity ?? $r->maturity_date ?? null;
            if ($p === '' || ! $m) {
                continue;
            }
            $k = $p.'|'.\Carbon\Carbon::parse($m)->format('Y-m-d');
            if (isset($byKey[$k])) {
                $t = $byKey[$k];
                $r->renewal_status = $t->renewal_status;
                $r->renewal_date = $t->renewal_date;
                $r->renewal_notes = $t->notes;
            }
        }
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    protected function filterPolicyRowsByRenewalStatus(array $rows, string $renewalStatus): array
    {
        $renewalStatus = trim($renewalStatus);
        if ($renewalStatus === '' || ! $this->maturityRenewalTrackingTableExists()) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($r) use ($renewalStatus) {
            $st = $r->renewal_status ?? null;
            if ($renewalStatus === 'pending') {
                return $st === null || $st === '' || $st === 'pending';
            }

            return $st === $renewalStatus;
        }));
    }

    /**
     * Get all maturing policies for Excel export. Same filters as getMaturingPoliciesPaginated.
     * Returns collection (max 10,000 rows).
     */
    public function getMaturingPoliciesForExport(
        int $daysBefore,
        ?string $search,
        ?string $sortBy,
        ?string $sortDir,
        ?string $product = null,
        ?string $renewalStatus = null
    ): \Illuminate\Support\Collection {
        $from = now()->format('Y-m-d');
        $to = now()->addDays($daysBefore)->format('Y-m-d');
        $renewalStatus = trim((string) ($renewalStatus ?? ''));
        $hasTracking = $this->maturityRenewalTrackingTableExists();
        $allowedSort = ['maturity', 'policy_number', 'product', 'life_assured'];
        if ($hasTracking) {
            $allowedSort[] = 'renewal_status';
        }
        $sortCol = in_array($sortBy ?? '', $allowedSort) ? $sortBy : 'maturity';
        if ($sortCol === 'renewal_status' && ! $hasTracking) {
            $sortCol = 'maturity';
        }
        $dir = strtolower($sortDir ?? 'asc') === 'desc' ? 'desc' : 'asc';

        try {
            if (Schema::hasTable('maturities_cache')) {
                $query = DB::table('maturities_cache as mc')
                    ->whereNotNull('mc.maturity')
                    ->whereNotNull('mc.policy_number')
                    ->where('mc.maturity', '>=', $from)
                    ->where('mc.maturity', '<=', $to);

                if ($hasTracking) {
                    $query->leftJoin('maturity_renewal_tracking as mrt', function ($j) {
                        $j->on('mrt.policy_number', '=', 'mc.policy_number')
                            ->on('mrt.maturity', '=', 'mc.maturity');
                    })->selectRaw('mc.*, mrt.renewal_status, mrt.renewal_date, mrt.notes as renewal_notes');
                } else {
                    $query->select('mc.*');
                }

                $this->applyRenewalStatusFilter($query, $renewalStatus, $hasTracking);

                $productTrim = trim($product ?? '');
                if ($productTrim !== '') {
                    $query->where('mc.product', $productTrim);
                }

                $search = trim($search ?? '');
                if ($search !== '') {
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('mc.policy_number', 'like', $term)
                            ->orWhere('mc.life_assured', 'like', $term)
                            ->orWhere('mc.product', 'like', $term);
                    });
                }

                $sortPrefix = ($hasTracking && $sortCol === 'renewal_status') ? 'mrt.' : 'mc.';
                $query->orderBy($sortPrefix.$sortCol, $dir)->limit(10000);

                return $query->get();
            }
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: maturities_cache export read failed', ['error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('erp_clients_cache')) {
                $query = DB::table('erp_clients_cache as ec')
                    ->whereNotNull('ec.maturity')
                    ->whereNotNull('ec.policy_number')
                    ->where('ec.maturity', '>=', $from)
                    ->where('ec.maturity', '<=', $to);

                if ($hasTracking) {
                    $query->leftJoin('maturity_renewal_tracking as mrt', function ($j) {
                        $j->on('mrt.policy_number', '=', 'ec.policy_number')
                            ->on('mrt.maturity', '=', 'ec.maturity');
                    })->selectRaw('ec.*, mrt.renewal_status, mrt.renewal_date, mrt.notes as renewal_notes');
                } else {
                    $query->select('ec.*');
                }

                $this->applyRenewalStatusFilter($query, $renewalStatus, $hasTracking);

                $productTrim = trim($product ?? '');
                if ($productTrim !== '') {
                    $query->where('ec.product', $productTrim);
                }

                $search = trim($search ?? '');
                if ($search !== '') {
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('ec.policy_number', 'like', $term)
                            ->orWhere('ec.product', 'like', $term)
                            ->orWhere('ec.pol_prepared_by', 'like', $term)
                            ->orWhere('ec.intermediary', 'like', $term);
                        if (Schema::hasColumn('erp_clients_cache', 'life_assured')) {
                            $q->orWhere('ec.life_assured', 'like', $term);
                        }
                    });
                }

                $sortPrefix = ($hasTracking && $sortCol === 'renewal_status') ? 'mrt.' : 'ec.';
                $query->orderBy($sortPrefix.$sortCol, $dir)->limit(10000);

                return $query->get();
            }
        } catch (\Throwable $e) {
            Log::warning('TicketAutoCreateService: erp_clients_cache export read failed', ['error' => $e->getMessage()]);
        }

        if ($this->erp && config('erp.clients_view_source') === 'erp_http') {
            $all = $this->getMaturingPoliciesFromHttpApi($from, $to, $product);
            $all = array_values(array_filter($all, fn ($r) => ! empty(trim($r->policy_number ?? ''))));

            $search = trim($search ?? '');
            if ($search !== '') {
                $term = strtolower($search);
                $all = array_values(array_filter($all, function ($r) use ($term) {
                    $policy = strtolower($r->policy_number ?? '');
                    $life = strtolower($r->life_assur ?? $r->life_assured ?? '');
                    $productName = strtolower($r->product ?? '');

                    return str_contains($policy, $term) || str_contains($life, $term) || str_contains($productName, $term);
                }));
            }

            $this->attachRenewalTrackingToPolicyRows($all);
            $all = $this->filterPolicyRowsByRenewalStatus($all, $renewalStatus);

            $exportSortCol = $sortCol;
            if ($exportSortCol === 'renewal_status' && ! $hasTracking) {
                $exportSortCol = 'maturity';
            }

            usort($all, function ($a, $b) use ($exportSortCol, $dir, $hasTracking) {
                $va = ($hasTracking && $exportSortCol === 'renewal_status')
                    ? ($a->renewal_status ?? '')
                    : ($a->{$exportSortCol} ?? $a->life_assured ?? $a->life_assur ?? '');
                $vb = ($hasTracking && $exportSortCol === 'renewal_status')
                    ? ($b->renewal_status ?? '')
                    : ($b->{$exportSortCol} ?? $b->life_assured ?? $b->life_assur ?? '');
                $cmp = strcmp((string) $va, (string) $vb);

                return $dir === 'desc' ? -$cmp : $cmp;
            });

            return collect(array_slice($all, 0, 10000));
        }

        return collect([]);
    }

    /**
     * Get policies maturing between two dates. Prefers maturities_cache (same as UI), then erp_clients_cache, then HTTP API.
     */
    protected function getPoliciesMaturingBetween(string $from, string $to): iterable
    {
        foreach (['maturities_cache', 'erp_clients_cache'] as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $rows = DB::table($table)
                    ->whereNotNull('maturity')
                    ->whereNotNull('policy_number')
                    ->where('maturity', '>=', $from)
                    ->where('maturity', '<=', $to)
                    ->get();
                if ($rows->isNotEmpty()) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                Log::warning('TicketAutoCreateService: policies maturing between read failed', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }

        if ($this->erp && config('erp.clients_view_source') === 'erp_http') {
            return $this->getMaturingPoliciesFromHttpApi($from, $to);
        }

        return [];
    }

    /**
     * Fetch maturing policies from ERP HTTP API (/clients/maturities endpoint).
     */
    protected function getMaturingPoliciesFromHttpApi(string $from, string $to, ?string $product = null): array
    {
        $result = $this->erp->getMaturingPoliciesFromHttpApi($from, $to, $product);
        if (! empty($result['error']) || empty($result['data'])) {
            return [];
        }
        $data = $result['data'] ?? [];
        if ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->all();
        }
        return array_values(array_filter($data, fn ($r) => ! empty(trim($r->policy_number ?? ''))));
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
