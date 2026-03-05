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
    public function getTicketCountsByStatus(): array
    {
        return Cache::remember('geminia_ticket_counts_by_status', 120, function () {
            return $this->fetchTicketCountsByStatus();
        });
    }

    protected function fetchTicketCountsByStatus(): array
    {
        try {
            $counts = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereNotNull('t.contact_id')
                ->where('t.contact_id', '>', 0)
                ->selectRaw('t.status, count(*) as cnt')
                ->groupBy('t.status')
                ->pluck('cnt', 'status')
                ->toArray();

            $unassigned = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('t.contact_id')->orWhere('t.contact_id', '<=', 0);
                })
                ->count();
            if ($unassigned > 0) {
                $counts['Unassigned'] = $unassigned;
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
    public function globalSearch(string $term, int $limitPerType = 5): array
    {
        $results = [];
        $t = '%' . $term . '%';

        try {
            // Contacts
            $contacts = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
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
            $leads = DB::connection('vtiger')
                ->table('vtiger_leaddetails as l')
                ->join('vtiger_crmentity as e', 'l.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead'])
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
            $tickets = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereNotNull('t.contact_id')
                ->where('t.contact_id', '>', 0)
                ->where(function ($q) use ($t) {
                    $q->where('t.title', 'like', $t)
                        ->orWhere('t.ticket_no', 'like', $t)
                        ->orWhere('c.firstname', 'like', $t)
                        ->orWhere('c.lastname', 'like', $t);
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
            $deals = DB::connection('vtiger')
                ->table('vtiger_potential as p')
                ->join('vtiger_crmentity as e', 'p.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
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

    public function getContactsCount(): int
    {
        return (int) Cache::remember('geminia_contacts_count', 60, fn () => $this->fetchContactsCount());
    }

    protected function fetchContactsCount(): int
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->join('vtiger_crmentity as e', 'vtiger_contactdetails.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchContactsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getLeadsCount(?string $search = null): int
    {
        if (!$search || trim($search) === '') {
            return (int) Cache::remember('geminia_leads_count', 60, fn () => $this->fetchLeadsCount(null));
        }
        return $this->fetchLeadsCount($search);
    }

    protected function fetchLeadsCount(?string $search): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_leaddetails')
                ->join('vtiger_crmentity as e', 'vtiger_leaddetails.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead']);

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

    public function getDealsCount(): int
    {
        return (int) Cache::remember('geminia_deals_count', 60, fn () => $this->fetchDealsCount());
    }

    protected function fetchDealsCount(): int
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_potential')
                ->join('vtiger_crmentity as e', 'vtiger_potential.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
                ->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchDealsCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPipelineValue(): float
    {
        try {
            $sum = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->join('vtiger_crmentity as e', 'vtiger_potential.potentialid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Potentials', 'Opportunity'])
                ->sum('vtiger_potential.amount');
            return (float) $sum;
        } catch (\Throwable $e) {
            Log::warning('CrmService::getPipelineValue: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all dashboard stats in one cached call (avoids 12+ sequential DB queries).
     */
    public function getDashboardStats(int $cacheSeconds = 120): array
    {
        return Cache::remember('geminia_dashboard_stats', $cacheSeconds, function () {
            return [
                'ticketCounts' => $this->getTicketCountsByStatus(),
                'contactsCount' => $this->getContactsCount(),
                'leadsCount' => $this->getLeadsCount(),
                'dealsCount' => $this->getDealsCount(),
                'pipelineValue' => $this->getPipelineValue(),
                'leadsTodayCount' => $this->getLeadsTodayCount(),
                'openTicketsByAssignee' => $this->getOpenTicketsByAssignee(),
                'overdueActivities' => $this->getOverdueActivities(5),
                'upcomingTasks' => $this->getUpcomingTasks(7, 5),
                'leadsBySource' => $this->getLeadsBySource(),
                'dealsClosingSoon' => $this->getDealsClosingSoon(30, 8),
            ];
        });
    }

    public function getLeadsTodayCount(): int
    {
        try {
            $today = now()->format('Y-m-d');
            return DB::connection('vtiger')
                ->table('vtiger_leaddetails')
                ->join('vtiger_crmentity as e', 'vtiger_leaddetails.leadid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead'])
                ->whereRaw('DATE(e.createdtime) = ?', [$today])
                ->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getLeadsTodayCount: ' . $e->getMessage());
            return 0;
        }
    }

    public function getOpenTicketsByAssignee(): array
    {
        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response'])
                ->select('e.smownerid', 'u.first_name', 'u.last_name', 'u.user_name')
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

    public function getOverdueActivities(int $limit = 10): array
    {
        try {
            $today = now()->format('Y-m-d');
            $rows = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity as e', 'vtiger_activity.activityid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where('vtiger_activity.status', '!=', 'Completed')
                ->whereRaw('vtiger_activity.date_start < ?', [$today])
                ->select('vtiger_activity.activityid', 'vtiger_activity.subject', 'vtiger_activity.date_start', 'vtiger_activity.due_date')
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
    public function getUpcomingTasks(int $days = 7, int $limit = 5): array
    {
        try {
            $today = now()->format('Y-m-d');
            $end = now()->addDays($days)->format('Y-m-d');
            $rows = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity as e', 'vtiger_activity.activityid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where('vtiger_activity.status', '!=', 'Completed')
                ->whereNotNull('vtiger_activity.due_date')
                ->whereBetween('vtiger_activity.due_date', [$today, $end])
                ->select('vtiger_activity.activityid', 'vtiger_activity.subject', 'vtiger_activity.due_date')
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

    public function getContacts(int $limit = 50, int $offset = 0)
    {
        try {
            return Contact::listQuery()
                ->orderByDesc('e.createdtime')
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
     */
    public function getCustomers(int $limit = 50, int $offset = 0, ?string $search = null)
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->select(
                    'c.contactid',
                    'c.firstname',
                    'c.lastname',
                    'c.email',
                    'c.mobile',
                    'c.phone',
                    'e.smownerid',
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username'
                );

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term)
                        ->orWhere('c.email', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);
                });
            }

            return $query->orderByDesc('e.createdtime')
                ->offset($offset)
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('CrmService::getCustomers: ' . $e->getMessage());
            return collect();
        }
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
     * Get display name for a contact by ID.
     */
    public function getContactDisplayName(int $contactId): string
    {
        $c = $this->getContactById($contactId);
        return $c ? trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) : '';
    }

    public function getCustomersCount(?string $search = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);

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

    public function getLeads(int $limit = 50, int $offset = 0, ?string $search = null)
    {
        try {
            $query = Lead::listQuery();
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

    public function getTickets(int $limit = 50, int $offset = 0, ?string $status = null, ?string $search = null)
    {
        try {
            $driver = DB::connection('vtiger')->getDriverName();
            $descExpr = in_array($driver, ['mysql', 'mariadb'], true)
                ? 'LEFT(e.description, 500)'
                : 'SUBSTR(COALESCE(e.description, \'\'), 1, 500)';

            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
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
                    'c.firstname as contact_first',
                    'c.lastname as contact_last',
                    DB::raw("{$descExpr} as description"),
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username'
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

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term);
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

    public function getTicketsCount(?string $status = null, ?string $search = null): int
    {
        if ((!$status || trim($status) === '') && (!$search || trim($search) === '')) {
            return (int) Cache::remember('geminia_tickets_count', 60, fn () => $this->fetchTicketsCount(null, null));
        }
        return $this->fetchTicketsCount($status, $search);
    }

    protected function fetchTicketsCount(?string $status, ?string $search): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
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

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('t.title', 'like', $term)
                        ->orWhere('t.ticket_no', 'like', $term)
                        ->orWhere('c.firstname', 'like', $term)
                        ->orWhere('c.lastname', 'like', $term);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            Log::warning('CrmService::fetchTicketsCount: ' . $e->getMessage());
            return DB::connection('vtiger')->table('vtiger_troubletickets')->join('vtiger_crmentity as e', 'vtiger_troubletickets.ticketid', '=', 'e.crmid')->where('e.deleted', 0)->count();
        }
    }

    /**
     * Get tickets for a specific contact (client).
     */
    public function getTicketsForContact(int $contactId, int $limit = 200)
    {
        try {
            return DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId)
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
    public function getTicketsForContactPaginated(int $contactId, int $limit = 20, int $offset = 0, ?string $status = null, ?string $search = null)
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
                ->leftJoin('vtiger_contactscf as cf', 'c.contactid', '=', 'cf.contactid')
                ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId)
                ->select(
                    't.*',
                    'e.createdtime',
                    'e.modifiedtime',
                    'e.smownerid',
                    'c.firstname as contact_first',
                    'c.lastname as contact_last',
                    'cf.cf_860',
                    'cf.cf_856',
                    'cf.cf_852',
                    'cf.cf_872',
                    'u.first_name as owner_first',
                    'u.last_name as owner_last',
                    'u.user_name as owner_username'
                );

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
    public function getTicketsForContactCount(int $contactId, ?string $status = null, ?string $search = null): int
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
                ->where('t.contact_id', $contactId);

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
    public function getAdjacentContactIds(int $contactId): array
    {
        try {
            $base = DB::connection('vtiger')
                ->table('vtiger_contactdetails as c')
                ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact']);

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

    public function getDeals(int $limit = 50, int $offset = 0)
    {
        try {
            return Deal::listQuery()
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
    public function getActivities(int $limit = 50, int $offset = 0, ?string $activityType = null, ?string $status = null, ?string $search = null, ?int $contactId = null, ?int $ticketId = null)
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
     */
    public function createActivity(array $data, int $ownerId): ?int
    {
        try {
            $activityType = $data['activitytype'] ?? 'Task';
            $setype = in_array($activityType, ['Event', 'Meeting', 'Call']) ? 'Events' : 'Task';

            $activityId = DB::connection('vtiger')->table('vtiger_activity')->insertGetId([
                'subject' => $data['subject'] ?? 'Untitled',
                'activitytype' => $activityType,
                'date_start' => $data['date_start'] ?? now()->format('Y-m-d'),
                'due_date' => $data['due_date'] ?? $data['date_start'] ?? now()->format('Y-m-d'),
                'time_start' => $data['time_start'] ?? null,
                'time_end' => $data['time_end'] ?? null,
                'status' => $data['status'] ?? ($activityType === 'Task' ? 'Not Started' : 'Planned'),
                'eventstatus' => $data['eventstatus'] ?? ($activityType === 'Event' ? 'Planned' : null),
                'priority' => $data['priority'] ?? 'Medium',
            ]);

            $subject = $data['subject'] ?? 'Untitled';
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
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

            if (!empty($data['related_to'])) {
                DB::connection('vtiger')->table('vtiger_seactivityrel')->insert([
                    'crmid' => (int) $data['related_to'],
                    'activityid' => $activityId,
                ]);
            }
            if (!empty($data['ticket_id'])) {
                DB::connection('vtiger')->table('vtiger_seactivityrel')->insert([
                    'crmid' => (int) $data['ticket_id'],
                    'activityid' => $activityId,
                ]);
            }

            return $activityId;
        } catch (\Throwable $e) {
            Log::warning('CrmService::createActivity: ' . $e->getMessage());
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
                ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response'])
                ->whereRaw('e.createdtime < ?', [$cutoff])
                ->select(
                    't.ticketid',
                    't.ticket_no',
                    't.title',
                    't.status',
                    't.category',
                    't.priority',
                    'e.createdtime',
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
     * Pick first non-empty policy value, excluding any that look like KRA PIN (e.g. A006533554X).
     * PIN pattern: letter + 9 digits + letter.
     */
    protected function pickPolicyExcludingPin(?string ...$candidates): ?string
    {
        foreach ($candidates as $v) {
            $v = trim((string) ($v ?? ''));
            if ($v === '') {
                continue;
            }
            if (preg_match('/^[A-Z]\d{9}[A-Z]$/i', $v)) {
                continue;
            }
            return $v;
        }
        return null;
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
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->where(function ($q) use ($term) {
                    $q->where('cf.cf_860', 'like', $term)
                        ->orWhere('cf.cf_856', 'like', $term)
                        ->orWhere('cf.cf_852', 'like', $term)
                        ->orWhere('cf.cf_872', 'like', $term);
                })
                ->select('c.contactid', 'c.firstname', 'c.lastname', 'c.email', 'c.mobile', 'c.phone')
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
        $policyNumber = $erpClient['policy_no'] ?? $erpClient['policy_number'] ?? $erpClient['POLICY_NO'] ?? $erpClient['POLICY_NUMBER'] ?? '';

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
