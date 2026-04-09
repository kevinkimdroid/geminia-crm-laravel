<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetches CRM data from Vtiger database.
 * Gracefully handles missing tables or connection errors.
 */
class CrmService
{
    public function getTicketCountsByStatus(?int $ownerId = null): array
    {
        if ($ownerId !== null) {
            return $this->fetchTicketCountsByStatus($ownerId);
        }
        return Cache::remember('geminia_ticket_counts_by_status', 300, function () {
            return $this->fetchTicketCountsByStatus(null);
        });
    }

    protected function fetchTicketCountsByStatus(?int $ownerId = null): array
    {
        try {
            $driver = DB::connection('vtiger')->getDriverName();
            $setypeIn = "'HelpDesk','Ticket'";
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $ownerClause = ($ownerId !== null && $ownerId > 0) ? "AND e.smownerid = {$ownerId} " : '';
                $rows = DB::connection('vtiger')->select(
                    "(SELECT t.status, COUNT(*) as cnt FROM vtiger_troubletickets t " .
                    "INNER JOIN vtiger_crmentity e ON t.ticketid = e.crmid " .
                    "WHERE e.deleted = 0 AND e.setype IN ({$setypeIn}) " .
                    "AND t.contact_id IS NOT NULL AND t.contact_id > 0 {$ownerClause}" .
                    "GROUP BY t.status) " .
                    "UNION ALL " .
                    "(SELECT 'Unassigned' as status, COUNT(*) as cnt FROM vtiger_troubletickets t " .
                    "INNER JOIN vtiger_crmentity e ON t.ticketid = e.crmid " .
                    "WHERE e.deleted = 0 AND e.setype IN ({$setypeIn}) " .
                    "AND (t.contact_id IS NULL OR t.contact_id <= 0) {$ownerClause})"
                );
            } else {
                $queryAssigned = DB::connection('vtiger')
                    ->table('vtiger_troubletickets as t')
                    ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                    ->where('e.deleted', 0)
                    ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                    ->whereNotNull('t.contact_id')
                    ->where('t.contact_id', '>', 0);
                if ($ownerId !== null && $ownerId > 0) {
                    $queryAssigned->where('e.smownerid', $ownerId);
                }
                $counts = $queryAssigned->selectRaw('t.status, count(*) as cnt')
                    ->groupBy('t.status')
                    ->pluck('cnt', 'status')
                    ->toArray();
                $queryUnassigned = DB::connection('vtiger')
                    ->table('vtiger_troubletickets as t')
                    ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                    ->where('e.deleted', 0)
                    ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                    ->where(function ($q) {
                        $q->whereNull('t.contact_id')->orWhere('t.contact_id', '<=', 0);
                    });
                if ($ownerId !== null && $ownerId > 0) {
                    $queryUnassigned->where('e.smownerid', $ownerId);
                }
                $unassigned = $queryUnassigned->count();
                $rows = collect($counts)->map(fn ($cnt, $status) => (object) ['status' => $status, 'cnt' => $cnt])->values()->all();
                if ($unassigned > 0) {
                    $rows[] = (object) ['status' => 'Unassigned', 'cnt' => $unassigned];
                }
            }
            $counts = [];
            foreach ($rows as $r) {
                $counts[$r->status] = (int) $r->cnt;
            }
            return $counts;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketCountsByStatus: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Global search across contacts, leads, tickets, and deals.
     * Returns grouped results for autocomplete.
     */
    public function globalSearch(string $term, int $limitPerType = 5, ?int $ownerId = null): array
    {
        $results = [];
        $t = '%' . $term . '%';

        try {
            // Contacts
            $contactsQuery = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);
            if ($ownerId !== null && $ownerId > 0) {
                $contactsQuery->where('e.smownerid', $ownerId);
            }
            $contacts = $contactsQuery
                ->where(function ($q) use ($t) {
                    $q->where('c.firstname', 'like', $t)
                        ->orWhere('c.lastname', 'like', $t)
                        ->orWhere('c.email', 'like', $t)
                        ->orWhere('c.mobile', 'like', $t);
                })
                ->select('c.contactid as id', DB::raw("CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,'')) as title"), DB::raw("'contact' as type"))
                ->limit($limitPerType)
                ->get();
            foreach ($contacts as $r) {
                $results[] = [
                    'type' => 'contact',
                    'label' => 'Contact',
                    'title' => trim($r->title) ?: 'Contact #' . $r->id,
                    'url' => route('contacts.show', $r->id),
                    'icon' => 'bi-person',
                ];
            }

            // Leads
            $leadsQuery = DB::connection('vtiger')
                ->table('vtiger_leaddetails as l')
                ->join('vtiger_crmentity as e', 'l.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead']);
            if ($ownerId !== null && $ownerId > 0) {
                $leadsQuery->where('e.smownerid', $ownerId);
            }
            $leads = $leadsQuery
                ->where(function ($q) use ($t) {
                    $q->where('l.firstname', 'like', $t)
                        ->orWhere('l.lastname', 'like', $t)
                        ->orWhere('l.company', 'like', $t)
                        ->orWhere('l.email', 'like', $t);
                })
                ->select('l.leadid as id', 'l.firstname', 'l.lastname', 'l.company')
                ->limit($limitPerType)
                ->get();
            foreach ($leads as $r) {
                $name = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                $title = $name ?: 'Lead #' . $r->id;
                if (!empty($r->company)) {
                    $title .= ' (' . $r->company . ')';
                }
                $results[] = [
                    'type' => 'lead',
                    'label' => 'Lead',
                    'title' => $title,
                    'url' => route('leads.show', $r->id),
                    'icon' => 'bi-person-badge',
                ];
            }

            // Tickets (only those with contact)
            $ticketsQuery = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_contactscf as cf', 't.contact_id', '=', 'cf.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereNotNull('t.contact_id')
                ->where('t.contact_id', '>', 0);
            if ($ownerId !== null && $ownerId > 0) {
                $ticketsQuery->where('e.smownerid', $ownerId);
            }
            $tickets = $ticketsQuery
                ->where(function ($q) use ($t) {
                    $q->where('t.title', 'like', $t)
                        ->orWhere('t.ticket_no', 'like', $t)
                        ->orWhere('c.firstname', 'like', $t)
                        ->orWhere('c.lastname', 'like', $t)
                        ->orWhere('cf.cf_860', 'like', $t)
                        ->orWhere('cf.cf_856', 'like', $t)
                        ->orWhere('cf.cf_872', 'like', $t);
                })
                ->select('t.ticketid as id', 't.title')
                ->limit($limitPerType)
                ->get();
            foreach ($tickets as $r) {
                $results[] = [
                    'type' => 'ticket',
                    'label' => 'Ticket',
                    'title' => $r->title ?: 'Ticket #' . $r->id,
                    'url' => route('tickets.show', $r->id),
                    'icon' => 'bi-ticket-perforated',
                ];
            }

            // Deals
            $dealsQuery = DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity']);
            if ($ownerId !== null && $ownerId > 0) {
                $dealsQuery->where('e.smownerid', $ownerId);
            }
            $deals = $dealsQuery
                ->where(function ($q) use ($t) {
                    $q->where('p.potentialname', 'like', $t);
                })
                ->select('p.potentialid as id', 'p.potentialname as title')
                ->limit($limitPerType)
                ->get();
            foreach ($deals as $r) {
                $results[] = [
                    'type' => 'deal',
                    'label' => 'Deal',
                    'title' => $r->title ?: 'Deal #' . $r->id,
                    'url' => route('deals.show', $r->id),
                    'icon' => 'bi-briefcase',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('CrmService::globalSearch: ' . $e->getMessage());
        }

        return $results;
    }

    public function getAccounts(int $limit = 200): \Illuminate\Support\Collection
    {
        foreach (['vtiger_account', 'vtiger_accounts'] as $table) {
            try {
                $rows = DB::connection('vtiger')
                    ->table($table . ' as a')
                    ->join('vtiger_crmentity as e', 'a.accountid', '=', 'e.crmid')
                    ->where('e.deleted', 0)
                    ->whereIn('e.setype', ['Accounts', 'Account'])
                    ->select('a.accountid', 'a.accountname')
                    ->orderBy('a.accountname')
                    ->limit($limit)
                    ->get();
                if ($rows->isNotEmpty()) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                Log::debug('CrmService::getAccounts ' . $table . ': ' . $e->getMessage());
            }
        }
        return collect();
    }

    public function getProducts(int $limit = 200, ?string $search = null): \Illuminate\Support\Collection
    {
        // 1. Try direct select first (most reliable; vtiger_products has productid, productname)
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_products')
                ->select('productid', 'productname')
                ->orderBy('productname')
                ->limit($limit);
            if ($search && strlen($search) >= 1) {
                $term = '%' . $search . '%';
                $query->where('productname', 'like', $term);
            }
            $rows = $query->get();
            if ($rows->isNotEmpty()) {
                return $rows;
            }
        } catch (\Throwable $e) {
            Log::debug('CrmService::getProducts direct: ' . $e->getMessage());
        }

        // 2. Try with crmentity join (exclude deleted)
        foreach (['vtiger_products', 'vtiger_product'] as $table) {
            try {
                $pk = $table === 'vtiger_product' ? 'productid' : 'productid';
                $query = DB::connection('vtiger')
                    ->table($table . ' as p')
                    ->join('vtiger_crmentity as e', 'p.' . $pk, '=', 'e.crmid')
                    ->where('e.deleted', 0)
                    ->select('p.productid', 'p.productname')
                    ->orderBy('p.productname')
                    ->limit($limit);
                if ($search && strlen($search) >= 1) {
                    $term = '%' . $search . '%';
                    $query->where('p.productname', 'like', $term);
                }
                $rows = $query->get();
                if ($rows->isNotEmpty()) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                Log::debug('CrmService::getProducts join ' . $table . ': ' . $e->getMessage());
            }
        }
        return collect();
    }

    /**
     * Get active users for assignee dropdown (tickets, etc.).
     */
    public function getActiveUsers(): \Illuminate\Support\Collection
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('status', 'Active')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->select('id', 'user_name', 'first_name', 'last_name')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getActiveUsers: ' . $e->getMessage());
            return collect();
        }
    }

    public function getGroups(int $limit = 50, int $offset = 0): \Illuminate\Support\Collection
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_groups')
                ->orderBy('groupname')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getGroups: ' . $e->getMessage());
            return collect();
        }
    }

    public function getGroupsCount(): int
    {
        try {
            return DB::connection('vtiger')->table('vtiger_groups')->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getGroupsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getContactsCount(?int $ownerId = null): int
    {
        if ($ownerId === null) {
            return (int) Cache::remember('geminia_contacts_count', 60, fn () => $this->fetchContactsCount(null));
        }
        return $this->fetchContactsCount($ownerId);
    }

    protected function fetchContactsCount(?int $ownerId = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->join('vtiger_crmentity as e', 'vtiger_contactdetails.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchContactsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getLeadsCount(?string $search = null, ?int $ownerId = null): int
    {
        if ((!$search || trim($search) === '') && $ownerId === null) {
            return (int) Cache::remember('geminia_leads_count', 60, fn () => $this->fetchLeadsCount(null, null));
        }
        return $this->fetchLeadsCount($search, $ownerId);
    }

    protected function fetchLeadsCount(?string $search, ?int $ownerId = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_leaddetails')
                ->join('vtiger_crmentity as e', 'vtiger_leaddetails.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead']);

            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('vtiger_leaddetails.firstname', 'like', $term)
                        ->orWhere('vtiger_leaddetails.lastname', 'like', $term)
                        ->orWhere('vtiger_leaddetails.company', 'like', $term)
                        ->orWhere('vtiger_leaddetails.email', 'like', $term);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchLeadsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getDealsCount(?int $ownerId = null): int
    {
        if ($ownerId === null) {
            return (int) Cache::remember('geminia_deals_count', 60, fn () => $this->fetchDealsCount(null));
        }
        return $this->fetchDealsCount($ownerId);
    }

    protected function fetchDealsCount(?int $ownerId = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->join('vtiger_crmentity as e', 'vtiger_potential.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity']);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchDealsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPipelineValue(?int $ownerId = null): float
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->join('vtiger_crmentity as e', 'vtiger_potential.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity']);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            $sum = $query->sum('vtiger_potential.amount');
            return (float) $sum;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getPipelineValue: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all dashboard stats in one cached call (avoids 12+ sequential DB queries).
     */
    public function getDashboardStats(int $cacheSeconds = 120, ?int $ownerId = null): array
    {
        $cacheKey = 'geminia_dashboard_stats_' . ($ownerId ?? 'all');
        return Cache::remember($cacheKey, $cacheSeconds, function () use ($ownerId) {
            return [
                'ticketCounts' => $this->getTicketCountsByStatus($ownerId),
                'contactsCount' => $this->getContactsCount($ownerId),
                'leadsCount' => $this->getLeadsCount(null, $ownerId),
                'dealsCount' => $this->getDealsCount($ownerId),
                'pipelineValue' => $this->getPipelineValue($ownerId),
                'leadsTodayCount' => $this->getLeadsTodayCount($ownerId),
                'openTicketsByAssignee' => $this->getOpenTicketsByAssignee($ownerId),
                'overdueActivities' => $this->getOverdueActivities(5, $ownerId),
                'upcomingTasks' => $this->getUpcomingTasks(7, 5, $ownerId),
                'leadsBySource' => $this->getLeadsBySource($ownerId),
                'dealsClosingSoon' => $this->getDealsClosingSoon(30, 8, $ownerId),
            ];
        });
    }

    public function getLeadsTodayCount(?int $ownerId = null): int
    {
        try {
            $today = now()->format('Y-m-d');
            $query = DB::connection('vtiger')
                ->table('vtiger_leaddetails')
                ->join('vtiger_crmentity as e', 'vtiger_leaddetails.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead'])
                ->whereRaw('DATE(e.createdtime) = ?', [$today]);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getLeadsTodayCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getOpenTicketsByAssignee(?int $ownerId = null): array
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response']);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            $rows = $query->select('e.smownerid', 'u.first_name', 'u.last_name', 'u.user_name')
                ->get();
            $byAssignee = [];
            foreach ($rows as $r) {
                $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ($r->user_name ?? 'Unassigned');
                $byAssignee[$name] = ($byAssignee[$name] ?? 0) + 1;
            }
            arsort($byAssignee);
            return $byAssignee;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getOpenTicketsByAssignee: ' . $e->getMessage());
            return [];
        }
    }

    public function getOverdueActivities(int $limit = 10, ?int $ownerId = null): array
    {
        try {
            $today = now()->format('Y-m-d');
            $query = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity as e', 'vtiger_activity.activityid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where('vtiger_activity.status', '!=', 'Completed')
                ->whereRaw('vtiger_activity.date_start < ?', [$today]);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            $rows = $query->select('vtiger_activity.activityid', 'vtiger_activity.subject', 'vtiger_activity.date_start', 'vtiger_activity.due_date')
                ->orderBy('vtiger_activity.due_date')
                ->limit($limit)
                ->get();
            return $rows->map(fn ($r) => [
                'id' => $r->activityid,
                'subject' => $r->subject ?? 'Untitled',
                'due_date' => $r->due_date ?? $r->date_start,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getOverdueActivities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get upcoming tasks due in the next N days (for reminders).
     */
    public function getUpcomingTasks(int $days = 7, int $limit = 5, ?int $ownerId = null): array
    {
        try {
            $today = now()->format('Y-m-d');
            $end = now()->addDays($days)->format('Y-m-d');
            $query = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity as e', 'vtiger_activity.activityid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where('vtiger_activity.status', '!=', 'Completed')
                ->whereNotNull('vtiger_activity.due_date')
                ->whereBetween('vtiger_activity.due_date', [$today, $end]);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            $rows = $query->select('vtiger_activity.activityid', 'vtiger_activity.subject', 'vtiger_activity.due_date')
                ->orderBy('vtiger_activity.due_date')
                ->limit($limit)
                ->get();
            return $rows->map(fn ($r) => [
                'id' => $r->activityid,
                'subject' => $r->subject ?? 'Untitled',
                'due_date' => $r->due_date,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getUpcomingTasks: ' . $e->getMessage());
            return [];
        }
    }

    public function getContacts(int $limit = 50, int $offset = 0, ?int $ownerId = null)
    {
        try {
            $query = Contact::listQuery();
            if ($ownerId !== null) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContacts: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get customers (contacts) with owner info for Support > Customers view.
     *
     * @param  string  $orderBy  'created' (default) or 'name' for alphabetical browse
     * @param  bool  $includePolicy  When true, join contactscf and add policy_number from cf_860, cf_856, cf_872
     */
    public function getCustomers(int $limit = 50, int $offset = 0, ?string $search = null, ?int $ownerId = null, string $orderBy = 'created', bool $includePolicy = false)
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);
            if ($includePolicy) {
                $query->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid');
            }
            if ($ownerId !== null) {
                $query->where('e.smownerid', $ownerId);
            }
            $selects = [
                'c.contactid',
                'c.firstname',
                'c.lastname',
                'c.email',
                'c.mobile',
                'c.phone',
                'e.smownerid',
                'u.first_name as owner_first',
                'u.last_name as owner_last',
                'u.user_name as owner_username',
            ];
            if ($includePolicy) {
                $selects = array_merge($selects, ['cf.cf_860', 'cf.cf_856', 'cf.cf_872']);
            }
            $query->select($selects);

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $searchLower = strtolower(trim($search));
                $exactTerm = $searchLower;
                $words = array_filter(preg_split('/\s+/', $searchLower, -1, PREG_SPLIT_NO_EMPTY));

                $query->where(function ($q) use ($term, $words, $includePolicy, $exactTerm) {
                    // Original: match firstname, lastname, email, mobile
                    $q->where('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('c.email', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);

                    // Full name: "First Last" and "Last First" (matches when user types exact full name)
                    $conn = \DB::connection('vtiger');
                    $concatFirstLast = $conn->getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(c.firstname,'') || ' ' || COALESCE(c.lastname,''))"
                        : "CONCAT(TRIM(COALESCE(c.firstname,'')), ' ', TRIM(COALESCE(c.lastname,'')))";
                    $concatLastFirst = $conn->getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(c.lastname,'') || ' ' || COALESCE(c.firstname,''))"
                        : "CONCAT(TRIM(COALESCE(c.lastname,'')), ' ', TRIM(COALESCE(c.firstname,'')))";
                    $likeTerm = '%' . $exactTerm . '%';
                    $q->orWhereRaw("(LOWER({$concatFirstLast}) LIKE ? OR LOWER({$concatLastFirst}) LIKE ?)", [$likeTerm, $likeTerm]);
                    // Exact full name match (when user types complete name - takes precedence over partial)
                    $q->orWhereRaw("(LOWER({$concatFirstLast}) = ? OR LOWER({$concatLastFirst}) = ?)", [$exactTerm, $exactTerm]);

                    // Policy number search when cf is joined
                    if ($includePolicy) {
                        $q->orWhere('cf.cf_860', 'like', $term)
                            ->orWhere('cf.cf_856', 'like', $term)
                            ->orWhere('cf.cf_872', 'like', $term);
                    }
                });

                // Multi-word: ALL words must appear somewhere in firstname or lastname (exact-name style)
                if (count($words) > 1) {
                    foreach ($words as $word) {
                        $wordTerm = '%' . $word . '%';
                        $query->where(function ($q) use ($wordTerm) {
                            $q->where('c.firstname', 'like', $wordTerm)
                                ->orWhere('c.lastname', 'like', $wordTerm);
                        });
                    }
                }

                // Prioritize exact full-name matches so they appear first (user types "KIPKOSGEI KELVIN KIMUTAI" -> exact match on top)
                $conn = \DB::connection('vtiger');
                $concatFirstLast = $conn->getDriverName() === 'sqlite'
                    ? "TRIM(COALESCE(c.firstname,'') || ' ' || COALESCE(c.lastname,''))"
                    : "CONCAT(TRIM(COALESCE(c.firstname,'')), ' ', TRIM(COALESCE(c.lastname,'')))";
                $concatLastFirst = $conn->getDriverName() === 'sqlite'
                    ? "TRIM(COALESCE(c.lastname,'') || ' ' || COALESCE(c.firstname,''))"
                    : "CONCAT(TRIM(COALESCE(c.lastname,'')), ' ', TRIM(COALESCE(c.firstname,'')))";
                $exactOrderBy = "(CASE WHEN (LOWER({$concatFirstLast}) = ? OR LOWER({$concatLastFirst}) = ?) THEN 0 ELSE 1 END)";
                $query->orderByRaw($exactOrderBy . ' ASC', [$exactTerm, $exactTerm]);
            }

            if ($orderBy === 'name') {
                $query->orderBy('c.firstname')->orderBy('c.lastname');
            } elseif (!$search || trim($search) === '') {
                $query->orderByDesc('e.createdtime');
            }
            $rows = $query->offset($offset)->limit($limit)->get();
            if ($includePolicy && $rows->isNotEmpty()) {
                $rows = $rows->map(function ($r) {
                    $r->policy_number = $this->pickPolicyExcludingPin($r->cf_860 ?? null, $r->cf_856 ?? null, $r->cf_872 ?? null);
                    return $r;
                });
            }
            return $rows;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCustomers: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Contacts for mass broadcast UI: same as getCustomers with optional CRM source / client-type filters.
     *
     * @param  string|null  $clientType  "all", "s|{record_source}", or "t|{cf value}" when BROADCAST_CONTACT_TYPE_CF is set
     * @param  list<int>|null  $excludeContactIds  Explicit contact ids to omit from the list (e.g. recent broadcast recipients)
     */
    public function getCustomersForBroadcast(
        int $limit = 250,
        int $offset = 0,
        ?string $search = null,
        ?int $ownerId = null,
        string $orderBy = 'name',
        ?string $clientType = null,
        ?array $excludeContactIds = null,
    ) {
        $cfCol = $this->broadcastContactTypeColumn();
        [$sourceFilter, $typeVal, $lifeSystem] = $this->parseBroadcastClientType((string) ($clientType ?? 'all'), $cfCol);

        if ($lifeSystem !== null) {
            $erp = app(ErpClientService::class);
            if (! $erp->isClientsViewBackedByErp()) {
                return collect();
            }

            try {
                $fetchCap = min(max($limit * 4, $limit + 40), 400);
                $result = $erp->getClientsForListView($fetchCap, $offset, $search !== '' && $search !== null ? $search : null, $lifeSystem);
                $out = collect();
                $seen = [];
                foreach ($result['data'] as $row) {
                    if ($out->count() >= $limit) {
                        break;
                    }
                    $policy = trim((string) ($row->policy_no ?? $row->policy_number ?? ''));
                    if ($policy === '') {
                        continue;
                    }
                    $contact = $this->findContactByPolicyNumber($policy);
                    if (! $contact) {
                        continue;
                    }
                    $cid = (int) $contact->contactid;
                    if (isset($seen[$cid])) {
                        continue;
                    }
                    if ($ownerId !== null && (int) ($contact->smownerid ?? 0) !== $ownerId) {
                        continue;
                    }
                    if ($excludeContactIds !== null && $excludeContactIds !== []
                        && in_array($cid, $excludeContactIds, true)) {
                        continue;
                    }
                    $seen[$cid] = true;
                    $out->push($contact);
                }

                return $out;
            } catch (\Throwable $e) {
                Log::warning('CrmService::getCustomersForBroadcast (life system): ' . $e->getMessage());

                return collect();
            }
        }

        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id');

            if ($cfCol !== null && $typeVal !== null) {
                $query->leftJoin('vtiger_contactscf as cfseg', 'c.contactid', '=', 'cfseg.contactid');
            }

            $query->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);

            if ($ownerId !== null) {
                $query->where('e.smownerid', $ownerId);
            }
            if ($sourceFilter !== null) {
                $query->where('e.source', $sourceFilter);
            }
            if ($cfCol !== null && $typeVal !== null) {
                $query->where("cfseg.{$cfCol}", $typeVal);
            }

            if ($excludeContactIds !== null && $excludeContactIds !== []) {
                $excludeContactIds = array_values(array_unique(array_filter(array_map('intval', $excludeContactIds))));
                if ($excludeContactIds !== []) {
                    $query->whereNotIn('c.contactid', $excludeContactIds);
                }
            }

            $query->select([
                'c.contactid',
                'c.firstname',
                'c.lastname',
                'c.email',
                'c.mobile',
                'c.phone',
                'e.smownerid',
                'e.source',
                'u.first_name as owner_first',
                'u.last_name as owner_last',
                'u.user_name as owner_username',
            ]);

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $searchLower = strtolower(trim($search));
                $exactTerm = $searchLower;
                $words = array_filter(preg_split('/\s+/', $searchLower, -1, PREG_SPLIT_NO_EMPTY));

                $query->where(function ($q) use ($term, $exactTerm) {
                    $q->where('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('c.email', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);

                    $conn = \DB::connection('vtiger');
                    $concatFirstLast = $conn->getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(c.firstname,'') || ' ' || COALESCE(c.lastname,''))"
                        : "CONCAT(TRIM(COALESCE(c.firstname,'')), ' ', TRIM(COALESCE(c.lastname,'')))";
                    $concatLastFirst = $conn->getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(c.lastname,'') || ' ' || COALESCE(c.firstname,''))"
                        : "CONCAT(TRIM(COALESCE(c.lastname,'')), ' ', TRIM(COALESCE(c.firstname,'')))";
                    $likeTerm = '%' . $exactTerm . '%';
                    $q->orWhereRaw("(LOWER({$concatFirstLast}) LIKE ? OR LOWER({$concatLastFirst}) LIKE ?)", [$likeTerm, $likeTerm]);
                    $q->orWhereRaw("(LOWER({$concatFirstLast}) = ? OR LOWER({$concatLastFirst}) = ?)", [$exactTerm, $exactTerm]);
                });

                if (count($words) > 1) {
                    foreach ($words as $word) {
                        $wordTerm = '%' . $word . '%';
                        $query->where(function ($q) use ($wordTerm) {
                            $q->where('c.firstname', 'like', $wordTerm)
                                ->orWhere('c.lastname', 'like', $wordTerm);
                        });
                    }
                }

                $conn = \DB::connection('vtiger');
                $concatFirstLast = $conn->getDriverName() === 'sqlite'
                    ? "TRIM(COALESCE(c.firstname,'') || ' ' || COALESCE(c.lastname,''))"
                    : "CONCAT(TRIM(COALESCE(c.firstname,'')), ' ', TRIM(COALESCE(c.lastname,'')))";
                $concatLastFirst = $conn->getDriverName() === 'sqlite'
                    ? "TRIM(COALESCE(c.lastname,'') || ' ' || COALESCE(c.firstname,''))"
                    : "CONCAT(TRIM(COALESCE(c.lastname,'')), ' ', TRIM(COALESCE(c.firstname,'')))";
                $exactOrderBy = "(CASE WHEN (LOWER({$concatFirstLast}) = ? OR LOWER({$concatLastFirst}) = ?) THEN 0 ELSE 1 END)";
                $query->orderByRaw($exactOrderBy . ' ASC', [$exactTerm, $exactTerm]);
            }

            if ($orderBy === 'name') {
                $query->orderBy('c.firstname')->orderBy('c.lastname');
            } elseif (! $search || trim($search) === '') {
                $query->orderByDesc('e.createdtime');
            }

            return $query->offset($offset)->limit($limit)->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCustomersForBroadcast: ' . $e->getMessage());

            return collect();
        }
    }

    /**
     * Keep only contact IDs that match the broadcast segment (after Excel / checkbox merge).
     *
     * @param  array<int>  $contactIds
     * @return array<int>
     */
    public function filterContactIdsByBroadcastClientType(array $contactIds, ?string $clientType): array
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        if ($contactIds === []) {
            return [];
        }

        $cfCol = $this->broadcastContactTypeColumn();
        [$sourceFilter, $typeVal, $lifeSystem] = $this->parseBroadcastClientType((string) ($clientType ?? 'all'), $cfCol);

        if ($lifeSystem !== null) {
            $erp = app(ErpClientService::class);
            if (! $erp->isClientsViewBackedByErp()) {
                return [];
            }
            $policyByContact = $this->getContactPolicyNumbersByIds($contactIds);
            $policies = array_values(array_filter($policyByContact));
            $allowedList = $erp->filterPoliciesMatchingLifeSystemSegment($policies, $lifeSystem);
            $allowedNorm = [];
            foreach ($allowedList as $p) {
                $allowedNorm[trim(preg_replace('/\s+/', '', (string) $p))] = true;
            }
            $kept = [];
            foreach ($contactIds as $id) {
                $pol = $policyByContact[$id] ?? null;
                if ($pol === null || $pol === '') {
                    continue;
                }
                $key = trim(preg_replace('/\s+/', '', $pol));
                if (isset($allowedNorm[$key])) {
                    $kept[] = $id;
                }
            }

            return $kept;
        }

        if ($sourceFilter === null && $typeVal === null) {
            return $contactIds;
        }

        try {
            $q = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereIn('c.contactid', $contactIds);

            if ($sourceFilter !== null) {
                $q->where('e.source', $sourceFilter);
            }
            if ($cfCol !== null && $typeVal !== null) {
                $q->join('vtiger_contactscf as cfseg', 'c.contactid', '=', 'cfseg.contactid')
                    ->where("cfseg.{$cfCol}", $typeVal);
            }

            return $q->pluck('c.contactid')->map(fn ($id) => (int) $id)->unique()->values()->all();
        } catch (\Throwable $e) {
            Log::warning('CrmService::filterContactIdsByBroadcastClientType: ' . $e->getMessage());

            return $contactIds;
        }
    }

    /**
     * Distinct non-empty vtiger_crmentity.source values for Contacts.
     *
     * @return list<string>
     */
    public function getDistinctContactRecordSources(): array
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_crmentity as e')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereNotNull('e.source')
                ->where('e.source', '!=', '')
                ->distinct()
                ->orderBy('e.source')
                ->pluck('e.source')
                ->map(fn ($s) => trim((string) $s))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getDistinctContactRecordSources: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Distinct values for configured client-type custom field.
     *
     * @return list<string>
     */
    public function getDistinctBroadcastContactTypeValues(): array
    {
        $col = $this->broadcastContactTypeColumn();
        if ($col === null) {
            return [];
        }

        try {
            return DB::connection('vtiger')
                ->table('vtiger_contactscf as cf')
                ->join('vtiger_contactdetails as c', 'cf.contactid', '=', 'c.contactid')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereNotNull("cf.{$col}")
                ->where("cf.{$col}", '!=', '')
                ->distinct()
                ->orderBy("cf.{$col}")
                ->pluck("cf.{$col}")
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getDistinctBroadcastContactTypeValues: ' . $e->getMessage());

            return [];
        }
    }

    protected function broadcastContactTypeColumn(): ?string
    {
        $raw = config('mass_broadcast.contact_type_cf');
        $raw = $raw ? trim((string) $raw) : '';
        if ($raw === '' || ! preg_match('/^cf_\d+$/i', $raw)) {
            return null;
        }

        return strtolower($raw);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string} [crm source, cf value, life system: group|individual|mortgage|group_pension]
     */
    protected function parseBroadcastClientType(string $clientType, ?string $cfCol): array
    {
        if ($clientType === '' || $clientType === 'all') {
            return [null, null, null];
        }
        if (str_starts_with($clientType, 'l|')) {
            $v = trim(substr($clientType, 2));
            if (in_array($v, ['group', 'individual', 'mortgage', 'group_pension'], true)) {
                return [null, null, $v];
            }

            return [null, null, null];
        }
        if (str_starts_with($clientType, 's|')) {
            $v = trim(substr($clientType, 2));

            return [$v !== '' ? $v : null, null, null];
        }
        if (str_starts_with($clientType, 't|')) {
            $v = trim(substr($clientType, 2));
            if ($v === '' || $cfCol === null) {
                return [null, null, null];
            }

            return [null, $v, null];
        }

        return [null, null, null];
    }

    /**
     * Get a single contact by ID (for ticket create when contact may not be in paginated list).
     */
    public function getContactById(int $contactId): ?object
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where('c.contactid', $contactId)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->select('c.contactid', 'c.firstname', 'c.lastname', 'c.email', 'c.mobile', 'c.phone')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Load multiple contacts by ID (for mass email/SMS). Omits deleted or missing rows.
     *
     * @param  array<int>  $contactIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getContactsByIds(array $contactIds)
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        if ($contactIds === []) {
            return collect();
        }

        try {
            return DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereIn('c.contactid', $contactIds)
                ->select('c.contactid', 'c.firstname', 'c.lastname', 'c.email', 'c.mobile', 'c.phone')
                ->orderBy('c.lastname')
                ->orderBy('c.firstname')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactsByIds: ' . $e->getMessage());

            return collect();
        }
    }

    /**
     * Get display name for a contact by ID.
     */
    public function getContactDisplayName(int $contactId): string
    {
        $c = $this->getContactById($contactId);
        return $c ? trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) : '';
    }

    /**
     * Policy number for a contact from vtiger_contactscf (cf_860, cf_856, cf_872), excluding KRA PIN.
     */
    public function getContactPolicyNumber(int $contactId): ?string
    {
        if ($contactId <= 0) {
            return null;
        }
        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->where('c.contactid', $contactId)
                ->select('cf.cf_860', 'cf.cf_856', 'cf.cf_872')
                ->first();
            if (! $row) {
                return null;
            }
            $policy = $this->pickPolicyExcludingPin(
                $row->cf_860 ?? null,
                $row->cf_856 ?? null,
                $row->cf_872 ?? null
            );
            $policy = $policy !== null ? trim((string) $policy) : '';

            return $policy !== '' ? $policy : null;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactPolicyNumber: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param  list<int>  $contactIds
     * @return array<int, ?string> contactid => policy or null
     */
    public function getContactPolicyNumbersByIds(array $contactIds): array
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        if ($contactIds === []) {
            return [];
        }

        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereIn('c.contactid', $contactIds)
                ->select('c.contactid', 'cf.cf_860', 'cf.cf_856', 'cf.cf_872')
                ->get();

            $map = [];
            foreach ($contactIds as $id) {
                $map[$id] = null;
            }
            foreach ($rows as $row) {
                $policy = $this->pickPolicyExcludingPin(
                    $row->cf_860 ?? null,
                    $row->cf_856 ?? null,
                    $row->cf_872 ?? null
                );
                $policy = $policy !== null ? trim((string) $policy) : '';
                $map[(int) $row->contactid] = $policy !== '' ? $policy : null;
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactPolicyNumbersByIds: ' . $e->getMessage());

            return array_fill_keys($contactIds, null);
        }
    }

    public function getCustomersCount(?string $search = null, ?int $ownerId = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);
            if ($ownerId !== null) {
                $query->where('e.smownerid', $ownerId);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('c.email', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCustomersCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getLeads(int $limit = 50, int $offset = 0, ?string $search = null, ?int $ownerId = null)
    {
        try {
            $query = Lead::listQuery();
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('vtiger_leaddetails.firstname', 'like', $term)
                        ->orWhere('vtiger_leaddetails.lastname', 'like', $term)
                        ->orWhere('vtiger_leaddetails.company', 'like', $term)
                        ->orWhere('vtiger_leaddetails.email', 'like', $term);
                });
            }
            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getLeads: ' . $e->getMessage());
            return collect();
        }
    }

    public function getTickets(int $limit = 50, int $offset = 0, ?string $status = null, ?string $search = null, bool $fullDescription = false, ?int $assignedTo = null, ?int $ownerId = null)
    {
        try {
            $driver = DB::connection('vtiger')->getDriverName();
            $descExpr = $fullDescription
                ? 'e.description'
                : (in_array($driver, ['mysql', 'mariadb'], true)
                    ? 'LEFT(e.description, 500)'
                    : 'SUBSTR(COALESCE(e.description, \'\'), 1, 500)');

            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_contactscf as cf', 't.contact_id', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->leftJoin('vtiger_users as creator', 'e.smcreatorid', '=', 'creator.id')
                ->when($fullDescription, fn ($q) => $q
                    ->leftJoin('vtiger_users as modifier', 'e.modifiedby', '=', 'modifier.id'))
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->select(
                    't.ticketid',
                    't.ticket_no',
                    't.title',
                    't.status',
                    't.priority',
                    't.contact_id',
                    'e.createdtime',
                    'e.modifiedtime',
                    'e.smownerid',
                    'e.source',
                    'c.firstname as contact_first',
                    'c.lastname as contact_last',
                    'cf.cf_860',
                    'cf.cf_856',
                    'cf.cf_872',
                    DB::raw("{$descExpr} as description"),
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username',
                    'creator.first_name as assigned_by_first',
                    'creator.last_name as assigned_by_last',
                    'creator.user_name as assigned_by_username',
                    ...($fullDescription ? [
                        DB::raw("TRIM(CONCAT(COALESCE(creator.first_name,''), ' ', COALESCE(creator.last_name,''))) as creator_name"),
                        DB::raw("creator.user_name as creator_username"),
                        DB::raw("TRIM(CONCAT(COALESCE(modifier.first_name,''), ' ', COALESCE(modifier.last_name,''))) as modifier_name"),
                        DB::raw("modifier.user_name as modifier_username"),
                    ] : [])
                );

            if ($status === 'Unassigned') {
                $query->where(function ($q) {
                    $q->whereNull('t.contact_id')->orWhere('t.contact_id', '<=', 0);
                });
            } else {
                $query->whereNotNull('t.contact_id')->where('t.contact_id', '>', 0);
            }

            if ($status && trim($status) !== '' && $status !== 'Unassigned') {
                $query->where('t.status', $status);
            }

            $effectiveAssignee = $ownerId ?? $assignedTo;
            if ($effectiveAssignee !== null && $effectiveAssignee > 0) {
                $query->where('e.smownerid', $effectiveAssignee);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('cf.cf_860', 'like', $term)
                        ->orWhere('cf.cf_856', 'like', $term)
                        ->orWhere('cf.cf_872', 'like', $term)
                        ->orWhere('u.first_name', 'like', $term)
                        ->orWhere('u.last_name', 'like', $term)
                        ->orWhere('u.user_name', 'like', $term)
                        ->orWhere('creator.first_name', 'like', $term)
                        ->orWhere('creator.last_name', 'like', $term)
                        ->orWhere('creator.user_name', 'like', $term);
                });
            }

            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTickets: ' . $e->getMessage());
            return Ticket::listQuery()->orderByDesc('e.createdtime')->offset($offset)->limit($limit)->get();
        }
    }

    /**
     * Check if a ticket would appear in the user's ticket list (same logic as getTickets with assignedTo).
     * Use this for permission checks – if the ticket shows in their list, they can access it.
     */
    public function ticketVisibleToUser(int $ticketId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            return DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('t.ticketid', $ticketId)
                ->where('e.deleted', 0)
                ->where('e.smownerid', $userId)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('CrmService::ticketVisibleToUser: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all tickets for Excel export. Same filters as getTickets but full description and high limit.
     */
    public function getTicketsForExport(?string $status = null, ?string $search = null, int $limit = 50000, ?int $assignedTo = null, ?int $ownerId = null)
    {
        return $this->getTickets($limit, 0, $status, $search, true, $assignedTo, $ownerId);
    }

    public function getTicketsCount(?string $status = null, ?string $search = null, ?int $assignedTo = null): int
    {
        if ((!$status || trim($status) === '') && (!$search || trim($search) === '') && ($assignedTo === null || $assignedTo <= 0)) {
            return (int) Cache::remember('geminia_tickets_count', 300, fn () => $this->fetchTicketsCount(null, null, null));
        }
        return $this->fetchTicketsCount($status, $search, $assignedTo);
    }

    protected function fetchTicketsCount(?string $status, ?string $search, ?int $assignedTo = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_contactscf as cf', 't.contact_id', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->leftJoin('vtiger_users as creator', 'e.smcreatorid', '=', 'creator.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket']);

            if ($status === 'Unassigned') {
                $query->where(function ($q) {
                    $q->whereNull('t.contact_id')->orWhere('t.contact_id', '<=', 0);
                });
            } else {
                $query->whereNotNull('t.contact_id')->where('t.contact_id', '>', 0);
            }

            if ($status && trim($status) !== '' && $status !== 'Unassigned') {
                $query->where('t.status', $status);
            }

            if ($assignedTo !== null && $assignedTo > 0) {
                $query->where('e.smownerid', $assignedTo);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('cf.cf_860', 'like', $term)
                        ->orWhere('cf.cf_856', 'like', $term)
                        ->orWhere('cf.cf_872', 'like', $term)
                        ->orWhere('u.first_name', 'like', $term)
                        ->orWhere('u.last_name', 'like', $term)
                        ->orWhere('u.user_name', 'like', $term)
                        ->orWhere('creator.first_name', 'like', $term)
                        ->orWhere('creator.last_name', 'like', $term)
                        ->orWhere('creator.user_name', 'like', $term);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchTicketsCount: ' . $e->getMessage());
            return DB::connection('vtiger')->table('vtiger_troubletickets')->join('vtiger_crmentity as e', 'vtiger_troubletickets.ticketid', '=', 'e.crmid')->where('e.deleted', 0)->count();
        }
    }

    /**
     * Base query: HelpDesk tickets whose created date (calendar day) falls in [dateFrom, dateTo] inclusive.
     */
    protected function ticketsByDateRangeBaseQuery(
        string $dateFrom,
        string $dateTo,
        ?string $status = null,
        ?string $search = null,
        ?int $assignedTo = null,
        ?int $ownerId = null,
        bool $onlyWithContact = false,
    ) {
        $query = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
            ->leftJoin('vtiger_contactscf as cf', 't.contact_id', '=', 'cf.contactid')
            ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
            ->leftJoin('vtiger_users as creator', 'e.smcreatorid', '=', 'creator.id')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereRaw('DATE(e.createdtime) >= ?', [$dateFrom])
            ->whereRaw('DATE(e.createdtime) <= ?', [$dateTo]);

        if ($onlyWithContact) {
            $query->whereNotNull('t.contact_id')->where('t.contact_id', '>', 0);
        }

        if ($status === 'Unassigned') {
            $query->where(function ($q) {
                $q->whereNull('t.contact_id')->orWhere('t.contact_id', '<=', 0);
            });
        } elseif ($status && trim($status) !== '') {
            $query->where('t.status', $status);
        }

        $effectiveAssignee = $ownerId ?? $assignedTo;
        if ($effectiveAssignee !== null && $effectiveAssignee > 0) {
            $query->where('e.smownerid', $effectiveAssignee);
        }

        if ($search && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('t.title', 'like', $term)
                    ->orWhere('t.ticket_no', 'like', $term)
                    ->orWhere('c.firstname', 'like', $term)
                    ->orWhere('c.lastname', 'like', $term)
                    ->orWhere('cf.cf_860', 'like', $term)
                    ->orWhere('cf.cf_856', 'like', $term)
                    ->orWhere('cf.cf_872', 'like', $term)
                    ->orWhere('u.first_name', 'like', $term)
                    ->orWhere('u.last_name', 'like', $term)
                    ->orWhere('u.user_name', 'like', $term)
                    ->orWhere('creator.first_name', 'like', $term)
                    ->orWhere('creator.last_name', 'like', $term)
                    ->orWhere('creator.user_name', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getTicketsByDateRange(
        string $dateFrom,
        string $dateTo,
        int $limit = 200,
        int $offset = 0,
        ?string $status = null,
        ?string $search = null,
        ?int $assignedTo = null,
        ?int $ownerId = null,
        bool $onlyWithContact = false,
    ) {
        try {
            $query = $this->ticketsByDateRangeBaseQuery(
                $dateFrom,
                $dateTo,
                $status,
                $search,
                $assignedTo,
                $ownerId,
                $onlyWithContact
            )->select(
                't.ticketid',
                't.ticket_no',
                't.title',
                't.status',
                't.priority',
                't.category',
                't.contact_id',
                'e.createdtime',
                'e.modifiedtime',
                'e.smownerid',
                'e.source',
                'c.firstname as contact_first',
                'c.lastname as contact_last',
                'cf.cf_860',
                'cf.cf_856',
                'cf.cf_872',
                'u.first_name as owner_first',
                'u.last_name as owner_last',
                'u.user_name as owner_username',
                'creator.first_name as assigned_by_first',
                'creator.last_name as assigned_by_last',
                'creator.user_name as assigned_by_username',
            );

            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketsByDateRange: ' . $e->getMessage());

            return collect();
        }
    }

    public function countTicketsByDateRange(
        string $dateFrom,
        string $dateTo,
        ?string $status = null,
        ?string $search = null,
        ?int $assignedTo = null,
        ?int $ownerId = null,
        bool $onlyWithContact = false,
    ): int {
        try {
            return (int) $this->ticketsByDateRangeBaseQuery(
                $dateFrom,
                $dateTo,
                $status,
                $search,
                $assignedTo,
                $ownerId,
                $onlyWithContact
            )->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::countTicketsByDateRange: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get tickets for a specific contact (client).
     */
    public function getTicketsForContact(int $contactId, int $limit = 200, ?int $ownerId = null)
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId);
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query
                ->select('t.ticketid', 't.title', 't.ticket_no', 't.status')
                ->orderByDesc('e.createdtime')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketsForContact: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get paginated tickets for a specific contact with search and status filter.
     */
    public function getTicketsForContactPaginated(int $contactId, int $limit = 20, int $offset = 0, ?string $status = null, ?string $search = null, ?int $ownerId = null)
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->leftJoin('vtiger_users as creator', 'e.smcreatorid', '=', 'creator.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId)
                ->select(
                    't.*',
                    'e.description',
                    'e.createdtime',
                    'e.modifiedtime',
                    'e.smownerid',
                    'e.smcreatorid',
                    'c.firstname as contact_first',
                    'c.lastname as contact_last',
                    'cf.cf_860',
                    'cf.cf_856',
                    'cf.cf_852',
                    'cf.cf_872',
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username',
                    'creator.first_name as assigned_by_first',
                    'creator.last_name as assigned_by_last',
                    'creator.user_name as assigned_by_username'
                );

            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }

            if ($status && trim($status) !== '') {
                $query->where('t.status', $status);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('e.description', 'like', $term);
                });
            }

            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketsForContactPaginated: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get count of tickets for a specific contact with optional filters.
     */
    public function getTicketsForContactCount(int $contactId, ?string $status = null, ?string $search = null, ?int $ownerId = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId);

            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }

            if ($status && trim($status) !== '') {
                $query->where('t.status', $status);
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('e.description', 'like', $term);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketsForContactCount: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get previous and next contact IDs for navigation (ordered by contactid).
     */
    public function getAdjacentContactIds(int $contactId, ?int $ownerId = null): array
    {
        try {
            $base = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);

            if ($ownerId !== null && $ownerId > 0) {
                $base->where('e.smownerid', $ownerId);
            }

            $prev = (clone $base)->where('c.contactid', '<', $contactId)->orderByDesc('c.contactid')->value('c.contactid');
            $next = (clone $base)->where('c.contactid', '>', $contactId)->orderBy('c.contactid')->value('c.contactid');

            return [
                'prev' => $prev,
                'next' => $next,
            ];
        } catch (\Throwable $e) {
            Log::warning('CrmService::getAdjacentContactIds: ' . $e->getMessage());
            return ['prev' => null, 'next' => null];
        }
    }

    public function getDeals(int $limit = 50, int $offset = 0, ?int $ownerId = null)
    {
        try {
            $query = Deal::listQuery();
            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }
            return $query
                ->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getDeals: ' . $e->getMessage());
            return collect();
        }
    }

    public function getContact(int $id)
    {
        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->where('c.contactid', $id)
                ->select(
                    'c.*',
                    'e.createdtime',
                    'e.modifiedtime',
                    'e.smownerid',
                    'e.source',
                    'cf.idNumber',
                    'cf.cf_856',
                    'cf.cf_852',
                    'cf.cf_860',
                    'cf.cf_872',
                    DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as assigned_to_name")
                )
                ->first();

            if (!$row) {
                return null;
            }

            $contact = new Contact((array) $row);
            $contact->contactid = $row->contactid;
            $contact->idNumber = $row->idNumber ?? null;
            // cf_852 = KRA PIN; cf_860, cf_856, cf_872 = policy fields. Exclude cf_852 and reject any value that looks like PIN (e.g. A006533554X)
            $contact->policy_number = $this->pickPolicyExcludingPin(
                $row->cf_860 ?? null,
                $row->cf_856 ?? null,
                $row->cf_872 ?? null
            );
            $contact->pin = $row->cf_852 ?? null;
            $contact->assigned_to_name = trim($row->assigned_to_name ?? '') ?: null;
            $contact->source = $row->source ?? null;
            $contact->donotcall = $row->donotcall ?? null;
            $contact->emailoptout = $row->emailoptout ?? null;
            $contact->reference = $row->reference ?? null;
            $contact->notify_owner = $row->notify_owner ?? null;
            $contact->isconvertedfromlead = $row->isconvertedfromlead ?? null;

            return $contact;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContact: ' . $e->getMessage());
            try {
                return Contact::listQuery()->find($id);
            } catch (\Throwable $_) {
                return null;
            }
        }
    }

    /**
     * Get campaigns this contact is part of.
     */
    public function getCampaignsForContact(int $contactId)
    {
        try {
            if (!DB::connection('vtiger')->getSchemaBuilder()->hasTable('campaign_contact')) {
                return collect();
            }
            $campaignIds = DB::connection('vtiger')->table('campaign_contact')->where('contact_id', $contactId)->pluck('campaign_id');
            if ($campaignIds->isEmpty()) {
                return collect();
            }
            return DB::connection('vtiger')
                ->table('campaigns')
                ->whereIn('id', $campaignIds)
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCampaignsForContact: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Add contact to a campaign.
     */
    public function addContactToCampaign(int $contactId, int $campaignId): bool
    {
        try {
            DB::connection('vtiger')->table('campaign_contact')->insertOrIgnore([
                'campaign_id' => $campaignId,
                'contact_id' => $contactId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('CrmService::addContactToCampaign: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove contact from a campaign.
     */
    public function removeContactFromCampaign(int $contactId, int $campaignId): bool
    {
        try {
            DB::connection('vtiger')->table('campaign_contact')->where('contact_id', $contactId)->where('campaign_id', $campaignId)->delete();
            return true;
        } catch (\Throwable $e) {
            Log::warning('CrmService::removeContactFromCampaign: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get deals/opportunities related to a contact.
     */
    public function getContactDeals(int $contactId, int $limit = 10)
    {
        try {
            $relIds = DB::connection('vtiger')->table('vtiger_contpotentialrel')->where('contactid', $contactId)->pluck('potentialid');
            if ($relIds->isEmpty()) {
                return collect();
            }
            return DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('p.potentialid', $relIds)
                ->select('p.potentialid', 'p.potentialname', 'p.amount', 'p.sales_stage', 'p.closingdate')
                ->orderByDesc('e.modifiedtime')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactDeals: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get activities (tasks/events) related to a contact.
     */
    public function getContactActivities(int $contactId, int $limit = 10)
    {
        try {
            $activityIds = DB::connection('vtiger')
                ->table('vtiger_seactivityrel')
                ->where('crmid', $contactId)
                ->pluck('activityid');

            if ($activityIds->isEmpty()) {
                return collect();
            }

            return DB::connection('vtiger')
                ->table('vtiger_activity as a')
                ->join('vtiger_crmentity as e', 'a.activityid', '=', 'e.crmid')
                ->whereIn('a.activityid', $activityIds)
                ->where('e.deleted', 0)
                ->select('a.activityid', 'a.subject', 'a.activitytype', 'a.date_start', 'a.due_date', 'a.status')
                ->orderByDesc('a.date_start')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactActivities: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get activities filtered by client (contact) and/or ticket.
     * When contactId is set, only returns activities related to that contact.
     * When ticketId is set, only returns activities related to that ticket.
     * When both are set, returns activities related to both.
     */
    public function getActivities(int $limit = 50, int $offset = 0, ?string $activityType = null, ?string $status = null, ?string $search = null, ?int $contactId = null, ?int $ticketId = null, ?int $ownerId = null)
    {
        try {
            $activityIds = null;
            if ($contactId || $ticketId) {
                if ($contactId && $ticketId) {
                    $contactIds = DB::connection('vtiger')->table('vtiger_seactivityrel')->where('crmid', $contactId)->pluck('activityid');
                    $ticketIds = DB::connection('vtiger')->table('vtiger_seactivityrel')->where('crmid', $ticketId)->pluck('activityid');
                    $activityIds = $contactIds->intersect($ticketIds)->values();
                } elseif ($contactId) {
                    $activityIds = DB::connection('vtiger')->table('vtiger_seactivityrel')->where('crmid', $contactId)->pluck('activityid');
                } else {
                    $activityIds = DB::connection('vtiger')->table('vtiger_seactivityrel')->where('crmid', $ticketId)->pluck('activityid');
                }
                if ($activityIds->isEmpty()) {
                    return collect();
                }
            }

            $query = DB::connection('vtiger')
                ->table('vtiger_activity as a')
                ->join('vtiger_crmentity as e', 'a.activityid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->leftJoin('vtiger_seactivityrel as rel', 'a.activityid', '=', 'rel.activityid')
                ->leftJoin('vtiger_contactdetails as c', 'rel.crmid', '=', 'c.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Task', 'Tasks', 'Events', 'Event', 'Calendar', 'cbCalendar'])
                ->select(
                    'a.activityid',
                    'a.subject',
                    'a.activitytype',
                    'a.date_start',
                    'a.due_date',
                    'a.time_start',
                    'a.time_end',
                    'a.status',
                    'a.eventstatus',
                    'a.recurringtype',
                    'e.smownerid',
                    'rel.crmid as related_to_id',
                    DB::raw("TRIM(CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,''))) as related_to_name"),
                    DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as assigned_to_name")
                );

            if ($activityIds !== null) {
                $query->whereIn('a.activityid', $activityIds);
            }

            if ($ownerId !== null && $ownerId > 0) {
                $query->where('e.smownerid', $ownerId);
            }

            if ($activityType && in_array($activityType, ['Task', 'Event', 'Meeting', 'Call'])) {
                $query->where('a.activitytype', $activityType);
            }

            if ($status && trim($status) !== '') {
                $query->where('a.status', 'like', '%' . trim($status) . '%');
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where('a.subject', 'like', $term);
            }

            return $query->orderByDesc('a.date_start')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getActivities: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Create an activity (Event or Task).
     * Uses vtiger_crmentity_seq for ID (vtiger_activity has no auto_increment) and inserts
     * crmentity first, then activity, then relationship tables.
     */
    public function createActivity(array $data, int $ownerId): ?int
    {
        $conn = DB::connection('vtiger');
        try {
            $conn->beginTransaction();
            $activityType = $data['activitytype'] ?? 'Task';
            $setype = in_array($activityType, ['Event', 'Meeting', 'Call']) ? 'Events' : 'Task';
            $subject = $data['subject'] ?? 'Untitled';
            $dateStart = $data['date_start'] ?? now()->format('Y-m-d');
            $dueDate = $data['due_date'] ?? $dateStart;

            // vtiger_activity.activityid has no auto_increment; use crmentity_seq
            $seq = $conn->table('vtiger_crmentity_seq')->lockForUpdate()->first();
            $activityId = ((int) ($seq->id ?? 0)) + 1;
            $conn->table('vtiger_crmentity_seq')->update(['id' => $activityId]);

            // Insert crmentity first (required by vtiger)
            $conn->table('vtiger_crmentity')->insert([
                'crmid' => $activityId,
                'smcreatorid' => $ownerId,
                'smownerid' => $ownerId,
                'modifiedby' => $ownerId,
                'setype' => $setype,
                'description' => '',
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'viewedtime' => null,
                'status' => 1,
                'version' => 0,
                'presence' => 1,
                'deleted' => 0,
                'smgroupid' => 0,
                'source' => 'CRM',
                'label' => $subject,
            ]);

            // Insert activity with required columns (sendnotification, notime, visibility are NOT NULL)
            $conn->table('vtiger_activity')->insert([
                'activityid' => $activityId,
                'subject' => $subject,
                'activitytype' => $activityType,
                'date_start' => $dateStart,
                'due_date' => $dueDate,
                'time_start' => $data['time_start'] ?? null,
                'time_end' => $data['time_end'] ?? null,
                'sendnotification' => '0',
                'notime' => '0',
                'visibility' => 'all',
                'status' => $data['status'] ?? ($activityType === 'Task' ? 'Not Started' : 'Planned'),
                'eventstatus' => $data['eventstatus'] ?? ($activityType === 'Event' ? 'Planned' : null),
                'priority' => $data['priority'] ?? 'Medium',
            ]);

            // vtiger_activitycf (custom fields - add activityid if table exists)
            try {
                $conn->table('vtiger_activitycf')->insert(['activityid' => $activityId]);
            } catch (\Throwable $e) {
                // Table may not exist or has different structure; skip
            }

            if (!empty($data['related_to'])) {
                $relatedId = (int) $data['related_to'];
                $conn->table('vtiger_seactivityrel')->insert([
                    'crmid' => $relatedId,
                    'activityid' => $activityId,
                ]);
                // vtiger_cntactivityrel for contact-activity link (some vtiger versions)
                try {
                    $conn->table('vtiger_cntactivityrel')->insert([
                        'contactid' => $relatedId,
                        'activityid' => $activityId,
                    ]);
                } catch (\Throwable $e) {
                    // Table may not exist; seactivityrel is enough for getActivities
                }
            }
            if (!empty($data['ticket_id'])) {
                $conn->table('vtiger_seactivityrel')->insert([
                    'crmid' => (int) $data['ticket_id'],
                    'activityid' => $activityId,
                ]);
            }

            $conn->commit();
            return $activityId;
        } catch (\Throwable $e) {
            $conn->rollBack();
            Log::error('CrmService::createActivity failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Get SMS logs sent to a contact.
     */
    public function getSmsForContact(int $contactId, int $limit = 50, int $offset = 0)
    {
        try {
            return \App\Models\SmsLog::where('contact_id', $contactId)
                ->orderByDesc('sent_at')
                ->skip($offset)
                ->take($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getSmsForContact: ' . $e->getMessage());

            return collect();
        }
    }

    public function getSmsForContactCount(int $contactId): int
    {
        try {
            return \App\Models\SmsLog::where('contact_id', $contactId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get comments for a contact.
     */
    public function getContactComments(int $contactId, int $limit = 10)
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->where('related_to', $contactId)
                ->orderByDesc('modcommentsid')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactComments: ' . $e->getMessage());
            return collect();
        }
    }

    public function getLead(int $id)
    {
        try {
            return Lead::listQuery()->find($id);
        } catch (\Throwable $e) {
            Log::warning('CrmService::getLead: ' . $e->getMessage());
            return null;
        }
    }

    public function getTicket(int $id)
    {
        try {
            return Ticket::listQuery()->find($id);
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicket: ' . $e->getMessage());
            return null;
        }
    }

    public function getDeal(int $id)
    {
        try {
            return Deal::listQuery()->find($id);
        } catch (\Throwable $e) {
            Log::warning('CrmService::getDeal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get lead counts grouped by source for analytics.
     */
    public function getLeadsBySource(): array
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_leaddetails as l')
                ->join('vtiger_crmentity as e', 'l.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead'])
                ->selectRaw('COALESCE(NULLIF(TRIM(l.leadsource), ""), "Not Specified") as source, count(*) as cnt')
                ->groupBy('source')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'source')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getLeadsBySource: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get deals closing in the next N days (renewal/retention alerts).
     */
    public function getDealsClosingSoon(int $days = 30, int $limit = 10): \Illuminate\Support\Collection
    {
        try {
            $from = now()->format('Y-m-d');
            $to = now()->addDays($days)->format('Y-m-d');
            return DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 'p.contact_id', '=', 'c.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
                ->whereNotNull('p.closingdate')
                ->whereBetween('p.closingdate', [$from, $to])
                ->whereNotIn('p.sales_stage', ['Closed Won', 'Closed Lost', 'Dead'])
                ->select('p.potentialid', 'p.potentialname', 'p.amount', 'p.sales_stage', 'p.closingdate', 'c.firstname', 'c.lastname')
                ->orderBy('p.closingdate')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getDealsClosingSoon: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get all reports index data in one cached call (avoids 7+ sequential DB queries).
     */
    public function getReportsIndexData(int $cacheSeconds = 120): array
    {
        return Cache::remember('geminia_reports_index', $cacheSeconds, function () {
            return [
                'wonRevenue' => $this->getWonRevenue(),
                'pipelineValue' => $this->getPipelineValue(),
                'salesByPerson' => $this->getSalesByPerson(10),
                'leadsBySource' => $this->getLeadsBySource(),
                'pipelineByStage' => $this->getPipelineByStage(),
                'ticketsByStatus' => $this->getTicketsByStatusReport(),
                'ticketsByCategory' => $this->getTicketsByCategory(),
            ];
        });
    }

    /**
     * Get pipeline value by sales stage for funnel analytics.
     */
    public function getPipelineByStage(): array
    {
        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
                ->whereNotIn('p.sales_stage', ['Closed Won', 'Closed Lost', 'Dead'])
                ->selectRaw('COALESCE(NULLIF(TRIM(p.sales_stage), ""), "Unknown") as stage, count(*) as cnt, COALESCE(SUM(p.amount), 0) as total')
                ->groupByRaw('COALESCE(NULLIF(TRIM(p.sales_stage), ""), "Unknown")')
                ->get();
            return $rows->mapWithKeys(fn ($r) => [$r->stage => ['count' => $r->cnt, 'amount' => (float) $r->total]])->toArray();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getPipelineByStage: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales/revenue by owner (salesperson) for reports.
     */
    public function getSalesByPerson(int $limit = 10): \Illuminate\Support\Collection
    {
        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
                ->whereIn('p.sales_stage', ['Closed Won', 'Closed'])
                ->selectRaw('e.smownerid, u.first_name, u.last_name, u.user_name, COALESCE(SUM(p.amount), 0) as total')
                ->groupBy('e.smownerid', 'u.first_name', 'u.last_name', 'u.user_name')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();
            return $rows->map(function ($r) {
                $r->name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ($r->user_name ?? 'Unassigned');
                return $r;
            });
        } catch (\Throwable $e) {
            Log::warning('CrmService::getSalesByPerson: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get total won revenue (closed deals).
     */
    public function getWonRevenue(): float
    {
        try {
            $sum = DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('p.sales_stage', ['Closed Won', 'Closed'])
                ->sum('p.amount');
            return (float) $sum;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getWonRevenue: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all ticket categories from the CRM (vtiger).
     * Fetches distinct category values from vtiger_troubletickets.
     * Merged with config defaults so configured categories not yet used in tickets are included.
     */
    public function getTicketCategoriesFromCrm(): array
    {
        try {
            $fromTickets = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereNotNull('t.category')
                ->whereRaw('TRIM(t.category) != ?', [''])
                ->distinct()
                ->pluck('t.category')
                ->map(fn ($c) => trim((string) $c))
                ->filter()
                ->values()
                ->toArray();

            // Try vtiger picklist if it exists (category picklist for HelpDesk)
            $fromPicklist = [];
            try {
                $picklistTables = ['vtiger_picklistdetails', 'vtiger_helpdesk_category'];
                foreach ($picklistTables as $tbl) {
                    if (! DB::connection('vtiger')->getSchemaBuilder()->hasTable($tbl)) {
                        continue;
                    }
                    if ($tbl === 'vtiger_picklistdetails') {
                        $fieldRow = DB::connection('vtiger')->table('vtiger_field')
                            ->where('tablename', 'vtiger_troubletickets')
                            ->where('columnname', 'category')
                            ->first();
                        if ($fieldRow && ! empty($fieldRow->picklistid ?? null)) {
                            $fromPicklist = DB::connection('vtiger')
                                ->table('vtiger_picklistdetails')
                                ->where('picklistid', $fieldRow->picklistid)
                                ->orderBy('sortorderid')
                                ->pluck('picklist_value')
                                ->map(fn ($v) => trim((string) $v))
                                ->filter()
                                ->values()
                                ->toArray();
                            break;
                        }
                    } elseif ($tbl === 'vtiger_helpdesk_category') {
                        $fromPicklist = DB::connection('vtiger')
                            ->table($tbl)
                            ->orderBy('sortorderid')
                            ->pluck('category')
                            ->map(fn ($v) => trim((string) $v))
                            ->filter()
                            ->values()
                            ->toArray();
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Picklist tables may not exist or have different schema
            }

            $configCats = config('tickets.categories', []);
            $customCats = \App\Models\CrmSetting::tableExists()
                ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_categories_custom'))
                : [];
            $all = collect($fromPicklist)
                ->merge($fromTickets)
                ->merge($configCats)
                ->merge($customCats)
                ->unique()
                ->filter(fn ($v) => trim((string) $v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->values()
                ->toArray();

            return array_values(array_unique($all));
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketCategoriesFromCrm: ' . $e->getMessage());
            $base = config('tickets.categories', []);
            $custom = \App\Models\CrmSetting::tableExists()
                ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_categories_custom'))
                : [];

            return array_values(array_unique(array_merge($base, $custom)));
        }
    }

    /**
     * Get all ticket sources from the CRM (vtiger).
     * Fetches distinct source values from vtiger_crmentity for HelpDesk/Ticket records.
     */
    public function getTicketSourcesFromCrm(): array
    {
        try {
            $fromCrm = DB::connection('vtiger')
                ->table('vtiger_crmentity as e')
                ->join('vtiger_troubletickets as t', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereNotNull('e.source')
                ->whereRaw('TRIM(e.source) != ?', [''])
                ->distinct()
                ->pluck('e.source')
                ->map(fn ($s) => trim((string) $s))
                ->filter()
                ->values()
                ->toArray();

            $configSources = config('tickets.sources', []);
            $customSources = \App\Models\CrmSetting::tableExists()
                ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_sources_custom'))
                : [];
            $all = collect($fromCrm)
                ->merge($configSources)
                ->merge($customSources)
                ->unique()
                ->filter(fn ($v) => trim((string) $v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->values()
                ->toArray();

            return array_values(array_unique($all));
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketSourcesFromCrm: ' . $e->getMessage());
            $base = config('tickets.sources', ['CRM', 'Email', 'Web', 'Phone', 'Call', 'USSD', 'Agent']);
            $custom = \App\Models\CrmSetting::tableExists()
                ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_sources_custom'))
                : [];

            return array_values(array_unique(array_merge($base, $custom)));
        }
    }

    /**
     * Get tickets grouped by category for reports.
     */
    public function getTicketsByCategory(): array
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->selectRaw('COALESCE(NULLIF(TRIM(t.category), ""), "General") as category, count(*) as cnt')
                ->groupBy('category')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'category')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketsByCategory: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tickets grouped by status for reports.
     */
    public function getTicketsByStatusReport(): array
    {
        return $this->getTicketCountsByStatus();
    }

    /**
     * Get open tickets aging report (older than N days).
     */
    public function getTicketAgingReport(int $days = 7, int $limit = 100): \Illuminate\Support\Collection
    {
        try {
            $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');
            return DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereNotIn('t.status', ['Closed', 'Resolved', config('tickets.inactive_status', 'Inactive')])
                ->whereRaw('e.createdtime < ?', [$cutoff])
                ->select(
                    't.ticketid',
                    't.ticket_no',
                    't.title',
                    't.status',
                    't.category',
                    't.priority',
                    'e.createdtime',
                    'e.smownerid',
                    'c.firstname',
                    'c.lastname',
                    'u.first_name as owner_first',
                    'u.last_name as owner_last'
                )
                ->orderBy('e.createdtime')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getTicketAgingReport: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get contacts summary for reports (total, new in period).
     */
    public function getContactsSummaryReport(int $days = 30): array
    {
        try {
            $total = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->count();
            $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');
            $newCount = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->whereRaw('e.createdtime >= ?', [$cutoff])
                ->count();
            return ['total' => $total, 'new_last_days' => $newCount, 'days' => $days];
        } catch (\Throwable $e) {
            Log::warning('CrmService::getContactsSummaryReport: ' . $e->getMessage());
            return ['total' => 0, 'new_last_days' => 0, 'days' => $days];
        }
    }

    /**
     * Get PBX calls summary for reports (by status, by user, total duration).
     */
    public function getCallsSummaryReport(): array
    {
        try {
            $byStatus = DB::connection('vtiger')
                ->table('vtiger_pbxmanager')
                ->selectRaw('COALESCE(callstatus, "unknown") as status, count(*) as cnt')
                ->groupBy('callstatus')
                ->pluck('cnt', 'status')
                ->toArray();
            $userRows = DB::connection('vtiger')
                ->table('vtiger_pbxmanager as p')
                ->leftJoin('vtiger_users as u', 'p.user', '=', 'u.id')
                ->selectRaw('p.user, u.first_name, u.last_name, u.user_name, count(*) as cnt')
                ->groupBy('p.user', 'u.first_name', 'u.last_name', 'u.user_name')
                ->orderByDesc('cnt')
                ->get();
            $byUser = $userRows->map(fn ($r) => (object) [
                'user_name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ($r->user_name ?? 'Unassigned'),
                'cnt' => $r->cnt,
            ])->toArray();
            $totals = DB::connection('vtiger')
                ->table('vtiger_pbxmanager')
                ->selectRaw('count(*) as total_calls, COALESCE(SUM(COALESCE(billduration, totalduration, 0)), 0) as total_duration_sec')
                ->first();
            return [
                'by_status' => $byStatus,
                'by_user' => $byUser,
                'total_calls' => (int) ($totals->total_calls ?? 0),
                'total_duration_sec' => (int) ($totals->total_duration_sec ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCallsSummaryReport: ' . $e->getMessage());
            return ['by_status' => [], 'by_user' => [], 'total_calls' => 0, 'total_duration_sec' => 0];
        }
    }

    /**
     * Pick first non-empty policy value, excluding KRA PIN and client IDs.
     * Uses shared helper for consistent behavior.
     */
    protected function pickPolicyExcludingPin(?string ...$candidates): ?string
    {
        return pick_policy_excluding_pin(...$candidates);
    }

    /**
     * Find vtiger contact by policy number (searches cf_860, cf_856, cf_852, cf_872).
     */
    public function findContactByPolicyNumber(string $policyNumber): ?object
    {
        $policyNumber = trim(preg_replace('/\s+/', '', $policyNumber));
        if ($policyNumber === '') {
            return null;
        }
        try {
            $term = '%' . $policyNumber . '%';
            $row = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->where(function ($q) use ($term) {
                    $q->where('cf.cf_860', 'like', $term)
                        ->orWhere('cf.cf_856', 'like', $term)
                        ->orWhere('cf.cf_852', 'like', $term)
                        ->orWhere('cf.cf_872', 'like', $term);
                })
                ->select([
                    'c.contactid',
                    'c.firstname',
                    'c.lastname',
                    'c.email',
                    'c.mobile',
                    'c.phone',
                    'e.smownerid',
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username',
                ])
                ->first();
            return $row;
        } catch (\Throwable $e) {
            Log::warning('CrmService::findContactByPolicyNumber: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find vtiger contact by phone or email (for ERP client matching).
     */
    public function findContactByPhoneOrEmail(?string $phone, ?string $email): ?object
    {
        $phone = $phone ? preg_replace('/\D/', '', (string) $phone) : '';
        $email = trim((string) $email);
        if ($phone === '' && $email === '') {
            return null;
        }
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);

            if ($phone !== '' && $email !== '') {
                $query->where(function ($q) use ($phone, $email) {
                    $q->where('c.mobile', 'like', '%' . $phone . '%')
                        ->orWhere('c.phone', 'like', '%' . $phone . '%')
                        ->orWhere('c.email', 'like', '%' . $email . '%');
                });
            } elseif ($phone !== '') {
                $query->where(function ($q) use ($phone) {
                    $q->where('c.mobile', 'like', '%' . $phone . '%')
                        ->orWhere('c.phone', 'like', '%' . $phone . '%');
                });
            } else {
                $query->where('c.email', 'like', '%' . $email . '%');
            }

            return $query->select('c.contactid', 'c.firstname', 'c.lastname')->first();
        } catch (\Throwable $e) {
            Log::warning('CrmService::findContactByPhoneOrEmail: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create vtiger contact from ERP client data. Returns contactid or null.
     */
    public function createContactFromErpClient(array $erpClient): ?int
    {
        $name = trim($erpClient['name'] ?? $erpClient['client_name'] ?? (($erpClient['first_name'] ?? '') . ' ' . ($erpClient['last_name'] ?? '')));
        $firstName = $erpClient['first_name'] ?? explode(' ', $name, 2)[0] ?? 'Client';
        $lastName = $erpClient['last_name'] ?? (explode(' ', $name, 2)[1] ?? '');
        if ($lastName === '' && strpos($name, ' ') !== false) {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }
        $email = $erpClient['email'] ?? $erpClient['email_adr'] ?? '';
        $mobile = $erpClient['mobile'] ?? $erpClient['phone'] ?? '';
        $phone = $erpClient['phone'] ?? $erpClient['mobile'] ?? '';
        $policyNumber = $erpClient['policy_number'] ?? $erpClient['policy_no'] ?? $erpClient['POLICY_NUMBER'] ?? $erpClient['POLICY_NO'] ?? '';

        $ownerId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
        $label = trim($firstName . ' ' . $lastName) ?: $name ?: 'ERP Client';
        $now = now()->format('Y-m-d H:i:s');

        try {
            $id = (int) DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

            DB::connection('vtiger')->transaction(function () use ($id, $ownerId, $label, $now, $firstName, $lastName, $email, $mobile, $phone, $policyNumber) {
                DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $id,
                    'smcreatorid' => $ownerId,
                    'smownerid' => $ownerId,
                    'modifiedby' => $ownerId,
                    'setype' => 'Contacts',
                    'description' => '',
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'viewedtime' => null,
                    'status' => '',
                    'version' => 0,
                    'presence' => 1,
                    'deleted' => 0,
                    'smgroupid' => 0,
                    'source' => 'CRM',
                    'label' => $label,
                ]);

                DB::connection('vtiger')->table('vtiger_contactdetails')->insert([
                    'contactid' => $id,
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email,
                    'mobile' => $mobile,
                    'phone' => $phone,
                ]);

                if ($policyNumber !== '') {
                    try {
                        DB::connection('vtiger')->table('vtiger_contactscf')->insert([
                            'contactid' => $id,
                            'cf_860' => $policyNumber,
                        ]);
                    } catch (\Throwable $cfEx) {
                        Log::warning('Contact cf insert: ' . $cfEx->getMessage());
                    }
                }
            });

            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return $id;
        } catch (\Throwable $e) {
            Log::error('CrmService::createContactFromErpClient', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        }
    }
}
