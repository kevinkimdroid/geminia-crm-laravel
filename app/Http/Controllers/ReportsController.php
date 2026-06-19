<?php

namespace App\Http\Controllers;

use App\Exports\PipelineByStageExport;
use App\Exports\SalesByPersonExport;
use App\Exports\SlaBrokenExport;
use App\Exports\TicketAgingExport;
use App\Exports\TicketsByDateRangeExport;
use App\Exports\ManagementUsageExport;
use App\Exports\RealIssuesBacklogExport;
use App\Exports\AssignmentHandlersExport;
use App\Exports\BouncedEmailsExport;
use App\Exports\TicketWorkloadPerformanceExport;
use App\Exports\WorkActivitiesExport;
use Carbon\Carbon;
use App\Models\Ticket;
use App\Models\TicketReassignment;
use App\Models\UserReportingLine;
use App\Models\VtigerUser;
use App\Models\WorkTicket;
use App\Models\WorkTicketUpdate;
use App\Services\CrmService;
use App\Services\TicketSlaService;
use App\Services\UserDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController
{
    public function index(CrmService $crm): View
    {
        return view('reports', $crm->getReportsIndexData(120));
    }

    public function slaBroken(TicketSlaService $sla, UserDepartmentService $userDept): View
    {
        $tickets = Cache::remember('reports:sla-broken:view', 60, fn () => $sla->getBrokenSlaTickets(100));
        $userIds = $tickets->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $tickets = $tickets->map(fn ($t) => (object) array_merge((array) $t, [
            'owner_department' => isset($t->smownerid) ? ($departments[$t->smownerid] ?? null) : null,
        ]));
        return view('reports.sla-broken', ['tickets' => $tickets]);
    }

    public function ticketAging(CrmService $crm, Request $request, UserDepartmentService $userDept): View
    {
        $days = (int) $request->get('days', 7);
        $cacheKey = 'reports:ticket-aging:view:' . $days;
        $tickets = Cache::remember($cacheKey, 60, fn () => $crm->getTicketAgingReport($days, 200));
        $userIds = $tickets->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $tickets = $tickets->map(fn ($t) => (object) array_merge((array) $t, [
            'owner_department' => isset($t->smownerid) ? ($departments[$t->smownerid] ?? null) : null,
        ]));
        return view('reports.ticket-aging', [
            'tickets' => $tickets,
            'days' => $days,
        ]);
    }

    public function ticketsByDate(CrmService $crm, Request $request, UserDepartmentService $userDept): View
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $statusRaw = $request->get('status');
        $status = is_string($statusRaw) && trim($statusRaw) !== '' ? trim($statusRaw) : null;
        $searchRaw = $request->get('search');
        $search = is_string($searchRaw) && trim($searchRaw) !== '' ? trim($searchRaw) : null;
        $onlyWithContact = $request->boolean('only_with_contact');

        $ownerFilter = crm_owner_filter();
        $assignedTo = $ownerFilter ?? ($request->filled('assigned_to') ? (int) $request->get('assigned_to') : null);

        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(10, min(200, (int) ($request->get('per_page') ?: 50)));
        $offset = ($page - 1) * $perPage;

        $cacheKeyBase = 'reports:tickets-by-date:' . sha1(json_encode([
            'from' => $dateFrom,
            'to' => $dateTo,
            'status' => $status,
            'search' => $search,
            'assigned_to' => $assignedTo,
            'only_with_contact' => $onlyWithContact,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        $total = Cache::remember($cacheKeyBase . ':total', 60, fn () => $crm->countTicketsByDateRange($dateFrom, $dateTo, $status, $search, $assignedTo, null, $onlyWithContact));
        $rowCollection = Cache::remember($cacheKeyBase . ':rows', 60, fn () => $crm->getTicketsByDateRange($dateFrom, $dateTo, $perPage, $offset, $status, $search, $assignedTo, null, $onlyWithContact));

        $userIds = $rowCollection->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $tickets = $rowCollection->map(fn ($t) => (object) array_merge((array) $t, [
            'owner_department' => isset($t->smownerid) ? ($departments[$t->smownerid] ?? null) : null,
        ]));

        $ticketsPaginator = new LengthAwarePaginator(
            $tickets,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $usersList = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());

        return view('reports.tickets-by-date', [
            'tickets' => $ticketsPaginator,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'search' => $search,
            'onlyWithContact' => $onlyWithContact,
            'assignedTo' => $assignedTo,
            'ownerFilter' => $ownerFilter,
            'perPage' => $perPage,
            'users' => $usersList,
        ]);
    }

    public function exportTicketsByDate(CrmService $crm, UserDepartmentService $userDept, Request $request)
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $statusRaw = $request->get('status');
        $status = is_string($statusRaw) && trim($statusRaw) !== '' ? trim($statusRaw) : null;
        $searchRaw = $request->get('search');
        $search = is_string($searchRaw) && trim($searchRaw) !== '' ? trim($searchRaw) : null;
        $onlyWithContact = $request->boolean('only_with_contact');

        $ownerFilter = crm_owner_filter();
        $assignedTo = $ownerFilter ?? ($request->filled('assigned_to') ? (int) $request->get('assigned_to') : null);

        $limit = min(50000, max(100, (int) $request->get('limit', 20000)));
        $rows = $crm->getTicketsByDateRange($dateFrom, $dateTo, $limit, 0, $status, $search, $assignedTo, null, $onlyWithContact);

        $userIds = $rows->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);

        $exportRows = $rows->map(fn ($t) => [
            $t->ticket_no ?? 'TT'.($t->ticketid ?? ''),
            $t->title ?? '',
            $t->status ?? '',
            $t->priority ?? '',
            $t->category ?? '',
            trim(($t->contact_first ?? '').' '.($t->contact_last ?? '')) ?: '',
            $t->createdtime ?? '',
            $t->modifiedtime ?? '',
            trim(($t->owner_first ?? '').' '.($t->owner_last ?? '')) ?: 'Unassigned',
            $departments[$t->smownerid ?? 0] ?? '',
            $t->source ?? '',
        ])->toArray();

        $filename = 'tickets-'.$dateFrom.'-to-'.$dateTo;
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new TicketsByDateRangeExport($exportRows), $filename.'.xlsx');
        }

        return $this->csvResponse($exportRows, [
            'Ticket', 'Title', 'Status', 'Priority', 'Category', 'Contact', 'Created', 'Last modified', 'Assigned to', 'User dept', 'Source',
        ], $filename);
    }

    public function contactsSummary(CrmService $crm, Request $request): View
    {
        $days = (int) $request->get('days', 30);
        $cacheKey = 'reports:contacts-summary:' . $days;
        $summary = Cache::remember($cacheKey, 120, fn () => $crm->getContactsSummaryReport($days));
        return view('reports.contacts-summary', $summary);
    }

    public function callsSummary(CrmService $crm): View
    {
        $data = Cache::remember('reports:calls-summary', 120, fn () => $crm->getCallsSummaryReport());
        return view('reports.calls-summary', $data);
    }

    public function managementUsage(CrmService $crm, Request $request): View
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        $cacheKey = 'reports:management-usage:' . sha1($dateFrom . '|' . $dateTo);
        $data = Cache::remember($cacheKey, 120, fn () => $crm->getManagementUsageReport($dateFrom, $dateTo));

        return view('reports.management-usage', $data);
    }

    public function ticketWorkloadPerformance(Request $request, UserDepartmentService $userDept): View
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        // Management minimum per user per month.
        $target = max(200, min(2000, (int) $request->get('target', 200)));
        $dataset = $this->buildTicketWorkloadPerformanceDataset($dateFrom, $dateTo, $target, $userDept);

        return view('reports.ticket-workload-performance', [
            'rows' => $dataset['rows'],
            'months' => $dataset['months'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'target' => $target,
            'summary' => $dataset['summary'],
        ]);
    }

    public function workActivities(CrmService $crm, Request $request, UserDepartmentService $userDept): View
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request, now()->startOfMonth(), now());

        $sourceRaw = strtolower((string) $request->get('source', 'all'));
        $source = in_array($sourceRaw, ['all', 'calendar', 'work', 'ticket'], true) ? $sourceRaw : 'all';

        $typeRaw = $request->get('type');
        $activityType = is_string($typeRaw) && trim($typeRaw) !== '' ? trim($typeRaw) : null;

        $searchRaw = $request->get('search');
        $search = is_string($searchRaw) && trim($searchRaw) !== '' ? trim($searchRaw) : null;

        $scope = $this->resolveWorkActivitiesScope($request);

        $detailLimit = 500;
        $cacheKey = 'reports:work-activities:' . sha1(json_encode([
            $dateFrom, $dateTo, $source, $activityType, $search, $scope['owner_ids'], $detailLimit,
        ]));
        $dataset = Cache::remember($cacheKey, 120, fn () => $this->buildWorkActivitiesDataset(
            $crm,
            $userDept,
            $dateFrom,
            $dateTo,
            $source,
            $activityType,
            $search,
            $scope['owner_ids'],
            $detailLimit
        ));

        // Active users for the "narrow to one person" picker (admins: all; managers: their team).
        if ($scope['is_admin']) {
            $usersList = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());
        } elseif ($scope['is_manager']) {
            $teamIds = array_values(array_unique(array_merge([$scope['self_id']], $scope['reportees'])));
            $usersList = VtigerUser::on('vtiger')
                ->whereIn('id', $teamIds)
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'user_name', 'first_name', 'last_name']);
        } else {
            $usersList = collect();
        }

        return view('reports.work-activities', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'source' => $source,
            'activityType' => $activityType,
            'search' => $search,
            'assignedTo' => $scope['assigned_to'],
            'managerId' => $scope['manager_id'],
            'isAdmin' => $scope['is_admin'],
            'isManager' => $scope['is_manager'],
            'users' => $usersList,
            'managers' => $scope['managers'],
            'summary' => $dataset['summary'],
            'userRows' => $dataset['userRows'],
            'detailRows' => $dataset['detailRows'],
            'detailLimit' => $detailLimit,
            'detailTruncated' => $dataset['detailTruncated'],
        ]);
    }

    /**
     * Resolve who the viewer may see in the Work Activities report.
     *
     * - Admin: everyone by default; may pick a manager (their whole team) or a single user.
     * - Manager (has reportees): their team (self + reportees); may narrow to one team member.
     * - Regular user: themselves only.
     *
     * @return array{owner_ids: ?array<int>, self_id: int, is_admin: bool, is_manager: bool,
     *   reportees: array<int>, manager_id: ?int, assigned_to: ?int, managers: \Illuminate\Support\Collection}
     */
    private function resolveWorkActivitiesScope(Request $request): array
    {
        $user = Auth::guard('vtiger')->user() ?? Auth::user();
        $isAdmin = $user && method_exists($user, 'isAdministrator') && $user->isAdministrator();
        $selfId = (int) ($user->id ?? $user?->getAuthIdentifier() ?? 0);

        $reportees = $this->reporteeIds($selfId);
        $isManager = ! empty($reportees);

        $managerId = ($isAdmin && $request->filled('manager_id')) ? (int) $request->get('manager_id') : null;
        $assignedTo = $request->filled('assigned_to') ? (int) $request->get('assigned_to') : null;

        if ($isAdmin) {
            $managers = Cache::remember('reports:work-activities:managers', 300, function () {
                $ids = UserReportingLine::query()->distinct()->pluck('manager_id')
                    ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
                if (empty($ids)) {
                    return collect();
                }

                return VtigerUser::on('vtiger')->whereIn('id', $ids)
                    ->orderBy('first_name')->orderBy('last_name')
                    ->get(['id', 'user_name', 'first_name', 'last_name']);
            });

            if ($managerId) {
                $ownerIds = array_values(array_unique(array_merge([$managerId], $this->reporteeIds($managerId))));
            } elseif ($assignedTo) {
                $ownerIds = [$assignedTo];
            } else {
                $ownerIds = null; // all users
            }
        } elseif ($isManager) {
            $allowed = array_values(array_unique(array_merge([$selfId], $reportees)));
            $ownerIds = ($assignedTo && in_array($assignedTo, $allowed, true)) ? [$assignedTo] : $allowed;
            $managers = collect();
        } else {
            $ownerIds = [$selfId];
            $assignedTo = null;
            $managers = collect();
        }

        return [
            'owner_ids' => $ownerIds,
            'self_id' => $selfId,
            'is_admin' => (bool) $isAdmin,
            'is_manager' => $isManager,
            'reportees' => $reportees,
            'manager_id' => $managerId,
            'assigned_to' => $assignedTo,
            'managers' => $managers,
        ];
    }

    /**
     * Vtiger user ids that report to the given manager (from user_reporting_lines).
     *
     * @return array<int>
     */
    private function reporteeIds(int $managerId): array
    {
        if ($managerId <= 0) {
            return [];
        }

        return UserReportingLine::query()
            ->where('manager_id', $managerId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function exportWorkActivities(CrmService $crm, UserDepartmentService $userDept, Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request, now()->startOfMonth(), now());

        $sourceRaw = strtolower((string) $request->get('source', 'all'));
        $source = in_array($sourceRaw, ['all', 'calendar', 'work', 'ticket'], true) ? $sourceRaw : 'all';

        $typeRaw = $request->get('type');
        $activityType = is_string($typeRaw) && trim($typeRaw) !== '' ? trim($typeRaw) : null;
        $searchRaw = $request->get('search');
        $search = is_string($searchRaw) && trim($searchRaw) !== '' ? trim($searchRaw) : null;

        $viewScope = $this->resolveWorkActivitiesScope($request);

        $scope = strtolower((string) $request->get('scope', 'detail'));
        // Summary export needs no detail rows; detail export caps at a sane upper bound.
        $exportDetailLimit = $scope === 'summary' ? 1 : 20000;
        $dataset = $this->buildWorkActivitiesDataset(
            $crm,
            $userDept,
            $dateFrom,
            $dateTo,
            $source,
            $activityType,
            $search,
            $viewScope['owner_ids'],
            $exportDetailLimit
        );

        if ($scope === 'summary') {
            $headings = ['User', 'Department', 'Total Activities', 'Calendar Activities', 'CRM Tickets', 'Work Ticket Updates', 'Time Logged (mins)', 'Last Activity'];
            $rows = $dataset['userRows']->map(fn ($r) => [
                (string) $r->user_name,
                (string) ($r->department ?? ''),
                (int) $r->total,
                (int) $r->calendar_count,
                (int) $r->ticket_count,
                (int) $r->work_count,
                (int) $r->time_spent_minutes,
                $r->last_activity ? $this->formatDateTimeValue($r->last_activity, 'Y-m-d H:i') : '',
            ])->values()->toArray();
            $filename = 'work-activities-summary-' . $dateFrom . '-to-' . $dateTo;
        } else {
            $headings = ['User', 'Department', 'Source', 'Type', 'Subject / Update', 'Contact', 'Ticket', 'Ticket title', 'Ticket status', 'Activity status', 'Activity date'];
            $rows = $dataset['detailRows']->map(fn ($r) => [
                (string) $r->user_name,
                (string) ($r->department ?? ''),
                (string) $r->source,
                (string) $r->type,
                (string) $r->subject,
                (string) ($r->contact ?? ''),
                (string) ($r->ticket_no ?? ''),
                (string) ($r->ticket_title ?? ''),
                (string) ($r->ticket_status ?? ''),
                (string) ($r->status ?? ''),
                $r->activity_date ? $this->formatDateTimeValue($r->activity_date, 'Y-m-d H:i') : '',
            ])->values()->toArray();
            $filename = 'work-activities-' . $dateFrom . '-to-' . $dateTo;
        }

        if ($request->get('format') === 'csv') {
            return $this->csvResponse($rows, $headings, $filename);
        }

        return Excel::download(new WorkActivitiesExport($rows, $headings), $filename . '.xlsx');
    }

    /**
     * Build the combined "work activities by user" dataset from Vtiger calendar activities
     * and internal work ticket updates.
     *
     * @return array{
     *   summary: array<string, int>,
     *   userRows: \Illuminate\Support\Collection<int, object>,
     *   detailRows: \Illuminate\Support\Collection<int, object>,
     *   detailTruncated: bool
     * }
     */
    private function buildWorkActivitiesDataset(
        CrmService $crm,
        UserDepartmentService $userDept,
        string $dateFrom,
        string $dateTo,
        string $source,
        ?string $activityType,
        ?string $search,
        ?array $ownerIds,
        int $detailLimit
    ): array {
        $rangeStart = $dateFrom . ' 00:00:00';
        $rangeEnd = $dateTo . ' 23:59:59';

        $includeCalendar = $source === 'all' || $source === 'calendar';
        $includeWork = $source === 'all' || $source === 'work';
        // CRM tickets carry no calendar "type", so skip them when a type filter is set.
        $includeTicket = ($source === 'all' || $source === 'ticket') && $activityType === null;

        // ---------------------------------------------------------------------
        // Per-user summary — aggregated in SQL so we never hydrate every row.
        // ---------------------------------------------------------------------
        $agg = [];
        $ensure = function (int $uid, ?string $name = null) use (&$agg): object {
            if (! isset($agg[$uid])) {
                $agg[$uid] = (object) [
                    'user_id' => $uid,
                    'user_name' => null,
                    'department' => null,
                    'calendar_count' => 0,
                    'ticket_count' => 0,
                    'work_count' => 0,
                    'time_spent_minutes' => 0,
                    'last_activity' => null,
                ];
            }
            if ($name !== null && ($agg[$uid]->user_name === null || $agg[$uid]->user_name === '')) {
                $clean = trim($name);
                if ($clean !== '') {
                    $agg[$uid]->user_name = $clean;
                }
            }

            return $agg[$uid];
        };
        $touchLast = function (object $obj, $value): void {
            $formatted = $this->formatDateTimeValue($value, 'Y-m-d H:i:s');
            if ($formatted !== '' && ($obj->last_activity === null || $formatted > $obj->last_activity)) {
                $obj->last_activity = $formatted;
            }
        };

        if ($includeCalendar) {
            foreach ($crm->getWorkActivitiesAssigneeSummaryReport($dateFrom, $dateTo, $ownerIds, $activityType, $search) as $r) {
                $o = $ensure((int) ($r->smownerid ?? 0), (string) ($r->user_name ?? ''));
                $o->calendar_count += (int) ($r->cnt ?? 0);
                $touchLast($o, $r->last_at ?? null);
            }
        }

        if ($includeTicket) {
            foreach ($crm->getTicketActivitiesAssigneeSummaryReport($dateFrom, $dateTo, $ownerIds, $search) as $r) {
                $o = $ensure((int) ($r->smownerid ?? 0), (string) ($r->user_name ?? ''));
                $o->ticket_count += (int) ($r->cnt ?? 0);
                $touchLast($o, $r->last_at ?? null);
            }
        }

        if ($includeWork) {
            $workAggQuery = WorkTicketUpdate::query()
                ->join('work_tickets as wt', 'work_ticket_updates.work_ticket_id', '=', 'wt.id')
                ->whereBetween('work_ticket_updates.created_at', [$rangeStart, $rangeEnd])
                ->whereNotNull('work_ticket_updates.user_id');

            if ($ownerIds !== null) {
                if (empty($ownerIds)) {
                    $workAggQuery->whereRaw('1 = 0');
                } else {
                    $workAggQuery->whereIn('work_ticket_updates.user_id', $ownerIds);
                }
            }

            if ($search !== null) {
                $workAggQuery->where(function ($q) use ($search) {
                    $q->where('work_ticket_updates.update_text', 'like', '%' . $search . '%')
                        ->orWhere('wt.ticket_no', 'like', '%' . $search . '%')
                        ->orWhere('wt.title', 'like', '%' . $search . '%');
                });
            }

            $workAgg = $workAggQuery
                ->groupBy('work_ticket_updates.user_id')
                ->selectRaw('work_ticket_updates.user_id as user_id, COUNT(*) as cnt, COALESCE(SUM(work_ticket_updates.time_spent_minutes), 0) as mins, MAX(work_ticket_updates.created_at) as last_at')
                ->get();

            foreach ($workAgg as $r) {
                $o = $ensure((int) ($r->user_id ?? 0));
                $o->work_count += (int) ($r->cnt ?? 0);
                $o->time_spent_minutes += (int) ($r->mins ?? 0);
                $touchLast($o, $r->last_at ?? null);
            }
        }

        $userRows = collect(array_values($agg))->map(function (object $o) {
            $o->total = $o->calendar_count + $o->ticket_count + $o->work_count;

            return $o;
        });

        // Resolve names for work-only users (the work aggregate has no name column).
        $missingIds = $userRows
            ->filter(fn ($o) => $o->user_name === null || $o->user_name === '')
            ->pluck('user_id')
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if (! empty($missingIds)) {
            $names = VtigerUser::on('vtiger')
                ->whereIn('id', $missingIds)
                ->get()
                ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name]);
            $userRows = $userRows->map(function (object $o) use ($names) {
                if ($o->user_name === null || $o->user_name === '') {
                    $o->user_name = (string) ($names[$o->user_id] ?? ('User #' . $o->user_id));
                }

                return $o;
            });
        }

        $userIds = $userRows->pluck('user_id')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $userRows = $userRows
            ->map(function (object $o) use ($departments) {
                $o->department = $departments[$o->user_id] ?? null;

                return $o;
            })
            ->sortByDesc('total')
            ->values();

        $deptByUser = $userRows->mapWithKeys(fn ($r) => [$r->user_id => $r->department]);
        $totalActivities = (int) $userRows->sum('total');

        // ---------------------------------------------------------------------
        // Detailed log — only fetch up to the display/export limit per source.
        // ---------------------------------------------------------------------
        $details = collect();

        // Source 1: Vtiger calendar activities (Task / Event / Meeting / Call).
        if ($includeCalendar) {
            $calendar = $crm->getWorkActivitiesReport(
                $dateFrom,
                $dateTo,
                $ownerIds,
                $activityType,
                null,
                $search,
                $detailLimit
            );

            foreach ($calendar as $row) {
                $start = (string) ($row->date_start ?? '');
                $time = trim((string) ($row->time_start ?? ''));
                $activityDate = $start !== '' ? trim($start . ' ' . $time) : null;
                $name = trim((string) ($row->user_name ?? ''));

                $details->push((object) [
                    'user_id' => (int) ($row->smownerid ?? 0),
                    'user_name' => $name !== '' ? $name : 'Unassigned',
                    'source' => 'Calendar',
                    'type' => (string) ($row->activitytype ?? 'Task'),
                    'subject' => (string) ($row->subject ?? ''),
                    'contact' => trim((string) ($row->related_to_name ?? '')),
                    'ticket_id' => (int) ($row->related_ticket_id ?? 0),
                    'ticket_module' => 'crm',
                    'ticket_no' => trim((string) ($row->related_ticket_no ?? '')),
                    'ticket_title' => trim((string) ($row->related_ticket_title ?? '')),
                    'ticket_status' => trim((string) ($row->related_ticket_status ?? '')),
                    'status' => (string) ($row->status ?? ''),
                    'activity_date' => $activityDate,
                    'time_spent_minutes' => 0,
                ]);
            }
        }

        // Source 2: internal work ticket updates (progress logs).
        if ($includeWork) {
            $workQuery = WorkTicketUpdate::query()
                ->join('work_tickets as wt', 'work_ticket_updates.work_ticket_id', '=', 'wt.id')
                ->whereBetween('work_ticket_updates.created_at', [$rangeStart, $rangeEnd])
                ->whereNotNull('work_ticket_updates.user_id');

            if ($ownerIds !== null) {
                if (empty($ownerIds)) {
                    $workQuery->whereRaw('1 = 0');
                } else {
                    $workQuery->whereIn('work_ticket_updates.user_id', $ownerIds);
                }
            }

            if ($search !== null) {
                $workQuery->where(function ($q) use ($search) {
                    $q->where('work_ticket_updates.update_text', 'like', '%' . $search . '%')
                        ->orWhere('wt.ticket_no', 'like', '%' . $search . '%')
                        ->orWhere('wt.title', 'like', '%' . $search . '%');
                });
            }

            $workUpdates = $workQuery
                ->orderByDesc('work_ticket_updates.created_at')
                ->limit($detailLimit)
                ->get([
                    'work_ticket_updates.user_id',
                    'work_ticket_updates.update_text',
                    'work_ticket_updates.status_after_update',
                    'work_ticket_updates.time_spent_minutes',
                    'work_ticket_updates.created_at',
                    'wt.id as ticket_id',
                    'wt.ticket_no',
                    'wt.title as ticket_title',
                    'wt.status as ticket_status',
                ]);

            $workUserIds = $workUpdates->pluck('user_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
            $workUserNames = empty($workUserIds)
                ? collect()
                : VtigerUser::on('vtiger')
                    ->whereIn('id', $workUserIds)
                    ->get()
                    ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name]);

            foreach ($workUpdates as $row) {
                $uid = (int) ($row->user_id ?? 0);
                $statusAfter = trim((string) ($row->status_after_update ?? ''));

                $details->push((object) [
                    'user_id' => $uid,
                    'user_name' => (string) ($workUserNames[$uid] ?? ('User #' . $uid)),
                    'source' => 'Work Ticket',
                    'type' => 'Update',
                    'subject' => (string) ($row->update_text ?? ''),
                    'contact' => '',
                    'ticket_id' => (int) ($row->ticket_id ?? 0),
                    'ticket_module' => 'work',
                    'ticket_no' => trim((string) ($row->ticket_no ?? '')),
                    'ticket_title' => trim((string) ($row->ticket_title ?? '')),
                    'ticket_status' => trim((string) ($row->ticket_status ?? '')),
                    'status' => $statusAfter !== '' ? $statusAfter : trim((string) ($row->ticket_status ?? '')),
                    'activity_date' => $row->created_at,
                    'time_spent_minutes' => (int) ($row->time_spent_minutes ?? 0),
                ]);
            }
        }

        // Source 3: CRM trouble tickets (HelpDesk) created in the range, attributed to owner.
        // Skipped when a calendar-only activity type filter is applied.
        if ($includeTicket) {
            $tickets = $crm->getTicketActivitiesReport(
                $dateFrom,
                $dateTo,
                $ownerIds,
                $search,
                $detailLimit
            );

            foreach ($tickets as $row) {
                $name = trim((string) ($row->user_name ?? ''));

                $details->push((object) [
                    'user_id' => (int) ($row->smownerid ?? 0),
                    'user_name' => $name !== '' ? $name : 'Unassigned',
                    'source' => 'CRM Ticket',
                    'type' => 'Ticket',
                    'subject' => (string) ($row->title ?? ''),
                    'contact' => trim((string) ($row->contact_name ?? '')),
                    'ticket_id' => (int) ($row->ticketid ?? 0),
                    'ticket_module' => 'crm',
                    'ticket_no' => trim((string) ($row->ticket_no ?? '')),
                    'ticket_title' => trim((string) ($row->title ?? '')),
                    'ticket_status' => trim((string) ($row->status ?? '')),
                    'status' => (string) ($row->status ?? ''),
                    'activity_date' => $row->createdtime,
                    'time_spent_minutes' => 0,
                ]);
            }
        }

        $detailRows = $details
            ->map(function (object $r) use ($deptByUser) {
                $r->department = $deptByUser[$r->user_id] ?? null;

                return $r;
            })
            ->sortByDesc(fn ($r) => $this->formatDateTimeValue($r->activity_date, 'Y-m-d H:i:s'))
            ->take($detailLimit)
            ->values();

        $summary = [
            'total_activities' => $totalActivities,
            'calendar_total' => (int) $userRows->sum('calendar_count'),
            'ticket_total' => (int) $userRows->sum('ticket_count'),
            'work_total' => (int) $userRows->sum('work_count'),
            'users' => $userRows->count(),
            'time_spent_minutes' => (int) $userRows->sum('time_spent_minutes'),
            'avg_per_user' => $userRows->count() > 0 ? round($totalActivities / $userRows->count(), 1) : 0,
        ];

        return [
            'summary' => $summary,
            'userRows' => $userRows,
            'detailRows' => $detailRows,
            'detailTruncated' => $detailRows->count() < $totalActivities,
        ];
    }

    /**
     * Parse and normalize a date_from / date_to range from the request.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request, Carbon $defaultFrom, Carbon $defaultTo): array
    {
        $dateFrom = (string) $request->get('date_from', $defaultFrom->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', $defaultTo->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $defaultFrom->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $defaultTo->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
    }

    public function ticketAutomationAnalysis(): View
    {
        $normalRows = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->select('t.status', DB::raw('COUNT(*) as total'))
            ->groupBy('t.status')
            ->get();

        $workRows = DB::table('work_tickets')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $normalTotal = (int) $normalRows->sum('total');
        $workTotal = (int) $workRows->sum('total');

        $normalByStatus = $normalRows->mapWithKeys(fn ($r) => [(string) $r->status => (int) $r->total])->toArray();
        $workByStatus = $workRows->mapWithKeys(fn ($r) => [(string) $r->status => (int) $r->total])->toArray();

        $normalClosed = (int) ($normalByStatus['Closed'] ?? 0);
        $normalOpen = (int) ($normalByStatus['Open'] ?? 0);
        $normalInProgress = (int) ($normalByStatus['In Progress'] ?? 0);
        $normalWait = (int) ($normalByStatus['Wait For Response'] ?? 0);
        $normalBacklog = $normalOpen + $normalInProgress + $normalWait;
        $normalClosureRate = $normalTotal > 0 ? round(($normalClosed / $normalTotal) * 100, 1) : 0;
        $normalBacklogRate = $normalTotal > 0 ? round(($normalBacklog / $normalTotal) * 100, 1) : 0;

        $workClosed = (int) (($workByStatus['Closed'] ?? 0) + ($workByStatus['Done'] ?? 0));
        $workOpen = (int) ($workByStatus['Open'] ?? 0);
        $workInProgress = (int) ($workByStatus['In Progress'] ?? 0);
        $workBlocked = (int) ($workByStatus['Blocked'] ?? 0);
        $workBacklog = $workOpen + $workInProgress + $workBlocked;
        $workClosureRate = $workTotal > 0 ? round(($workClosed / $workTotal) * 100, 1) : 0;
        $workBacklogRate = $workTotal > 0 ? round(($workBacklog / $workTotal) * 100, 1) : 0;

        $overallTotal = $normalTotal + $workTotal;
        $overallClosed = $normalClosed + $workClosed;
        $overallBacklog = $normalBacklog + $workBacklog;
        $overallClosureRate = $overallTotal > 0 ? round(($overallClosed / $overallTotal) * 100, 1) : 0;
        $overallBacklogRate = $overallTotal > 0 ? round(($overallBacklog / $overallTotal) * 100, 1) : 0;

        $automationRecommendations = [
            'Implement automatic stale-ticket reminders (no update in 48h) for both modules.',
            'Add pre-breach and breach alerts for TAT/SLA (assignee + manager escalation).',
            'Auto-route new tickets by category/keywords to reduce manual assignment.',
            'Auto-escalate blocked work tickets to reporting managers same day.',
            'Normalize status lifecycle (e.g. Done -> Closed after verification window).',
        ];

        if ($workBacklogRate > 30) {
            $automationRecommendations[] = 'Work ticket backlog is elevated. Prioritize daily automation for update compliance.';
        }
        if ($normalWait > 20) {
            $automationRecommendations[] = 'Wait-for-response volume is high. Add recurring customer nudges via email/SMS.';
        }

        $realIssueInsights = $this->buildRealIssueInsights();

        return view('reports.ticket-automation-analysis', [
            'normalTotal' => $normalTotal,
            'workTotal' => $workTotal,
            'normalByStatus' => $normalByStatus,
            'workByStatus' => $workByStatus,
            'normalClosureRate' => $normalClosureRate,
            'workClosureRate' => $workClosureRate,
            'normalBacklogRate' => $normalBacklogRate,
            'workBacklogRate' => $workBacklogRate,
            'overallTotal' => $overallTotal,
            'overallClosureRate' => $overallClosureRate,
            'overallBacklogRate' => $overallBacklogRate,
            'automationRecommendations' => $automationRecommendations,
            'realIssueInsights' => $realIssueInsights,
        ]);
    }

    public function exportManagementUsage(CrmService $crm, Request $request)
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        $data = $crm->getManagementUsageReport($dateFrom, $dateTo);

        if ($request->boolean('simple')) {
            $simpleRows = [];
            $range = ($data['dateFrom'] ?? $dateFrom).' to '.($data['dateTo'] ?? $dateTo);
            $simpleRows[] = ['Summary', $range, 'Total Reported', (int) ($data['totalReported'] ?? 0), '', ''];
            $simpleRows[] = ['Summary', $range, 'Total Tracked', (int) ($data['totalTracked'] ?? 0), '', ''];
            $simpleRows[] = ['Summary', $range, 'Backlog Ratio %', ((int) ($data['totalReported'] ?? 0) > 0) ? round(((int) ($data['totalTracked'] ?? 0) / (int) ($data['totalReported'] ?? 1)) * 100, 1) : 0, '', ''];
            $simpleRows[] = ['Decision Snapshot', $range, 'Health Status', (string) (($data['decisionSummary']['health_status'] ?? 'No Data')), '', ''];
            $simpleRows[] = ['Decision Snapshot', $range, 'Closure Rate %', (float) (($data['decisionSummary']['closure_rate'] ?? 0)), '', ''];
            $simpleRows[] = ['Decision Snapshot', $range, 'Backlog Ratio %', (float) (($data['decisionSummary']['backlog_ratio'] ?? 0)), '', ''];

            $topPerformer = $data['topPerformer'] ?? null;
            if (! empty($topPerformer)) {
                $simpleRows[] = [
                    'Who Does Most',
                    (string) ($topPerformer['owner_name'] ?? 'Unassigned'),
                    'Closed Tickets',
                    (int) ($topPerformer['closed_count'] ?? 0),
                    'Close Rate: '.number_format((float) ($topPerformer['close_rate'] ?? 0), 1).'%',
                    'Top Issue: '.(string) ($topPerformer['top_issue'] ?? 'General'),
                ];
            }

            $topReported = $data['mostReported'][0] ?? null;
            if (! empty($topReported)) {
                $simpleRows[] = [
                    'Top Issue',
                    (string) ($topReported['issue'] ?? 'General'),
                    'Most Reported Count',
                    (int) ($topReported['count'] ?? 0),
                    '',
                    '',
                ];
            }

            $topTracked = $data['mostTracked'][0] ?? null;
            if (! empty($topTracked)) {
                $simpleRows[] = [
                    'Top Issue',
                    (string) ($topTracked['issue'] ?? 'General'),
                    'Most Tracked Count',
                    (int) ($topTracked['count'] ?? 0),
                    '',
                    '',
                ];
            }

            foreach (array_slice((array) ($data['recommendations'] ?? []), 0, 5) as $idx => $rec) {
                $simpleRows[] = ['Action Plan', 'Action '.($idx + 1), 'Recommendation', (string) $rec, '', ''];
            }

            foreach (array_slice((array) ($data['improvementsDone'] ?? []), 0, 5) as $idx => $item) {
                $simpleRows[] = ['Business Impact', 'Improvement '.($idx + 1), 'Delivered', (string) $item, '', ''];
            }

            $filename = 'management-usage-simple-'.$dateFrom.'-to-'.$dateTo;
            return Excel::download(new ManagementUsageExport($simpleRows), $filename.'.xlsx');
        }

        $rows = [];
        $rows[] = [
            'Executive Summary',
            ($data['dateFrom'] ?? $dateFrom).' to '.($data['dateTo'] ?? $dateTo),
            'Total Reported',
            (int) ($data['totalReported'] ?? 0),
            '',
            '',
        ];
        $rows[] = ['Executive Summary', ($data['dateFrom'] ?? $dateFrom).' to '.($data['dateTo'] ?? $dateTo), 'Total Tracked', (int) ($data['totalTracked'] ?? 0), '', ''];

        if (! empty($data['topPerformer'])) {
            $top = $data['topPerformer'];
            $rows[] = [
                'Executive Summary',
                (string) ($top['owner_name'] ?? 'Unassigned'),
                'Top Performer (Closed Tickets)',
                (int) ($top['closed_count'] ?? 0),
                'Close Rate: '.number_format((float) ($top['close_rate'] ?? 0), 1).'%',
                'Top Issue: '.(string) ($top['top_issue'] ?? 'General'),
            ];
        }

        foreach (($data['daily'] ?? []) as $row) {
            $rows[] = [
                'Daily Trend',
                (string) ($row['date'] ?? ''),
                'Reported',
                (int) ($row['reported'] ?? 0),
                '',
                '',
            ];
            $rows[] = ['Daily Trend', (string) ($row['date'] ?? ''), 'Closed', (int) ($row['closed'] ?? 0), '', ''];
        }

        foreach (($data['mostReported'] ?? []) as $row) {
            $rows[] = [
                'Most Reported Issues',
                (string) ($row['issue'] ?? 'General'),
                'Reported Count',
                (int) ($row['count'] ?? 0),
                '',
                '',
            ];
        }

        foreach (($data['mostTracked'] ?? []) as $row) {
            $rows[] = [
                'Most Tracked Issues',
                (string) ($row['issue'] ?? 'General'),
                'Tracked Count',
                (int) ($row['count'] ?? 0),
                '',
                '',
            ];
        }

        foreach (($data['ownerPerformance'] ?? []) as $row) {
            $owner = (string) ($row['owner_name'] ?? 'Unassigned');
            $rows[] = ['Assignee Performance', $owner, 'Assigned', (int) ($row['assigned_count'] ?? 0), 'Top Issue: '.(string) ($row['top_issue'] ?? 'General'), ''];
            $rows[] = ['Assignee Performance', $owner, 'Closed', (int) ($row['closed_count'] ?? 0), 'Close Rate: '.number_format((float) ($row['close_rate'] ?? 0), 1).'%', ''];
            $rows[] = ['Assignee Performance', $owner, 'Active', (int) ($row['active_count'] ?? 0), 'Avg Close Hrs: '.(isset($row['avg_close_hours']) ? number_format((float) $row['avg_close_hours'], 1) : 'N/A'), ''];
        }

        foreach (($data['recommendations'] ?? []) as $idx => $rec) {
            $rows[] = ['Recommendations', 'Recommendation '.($idx + 1), 'Action', (string) $rec, '', ''];
        }

        $filename = 'management-usage-'.$dateFrom.'-to-'.$dateTo;
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new ManagementUsageExport($rows), $filename.'.xlsx');
        }

        return $this->csvResponse(
            $rows,
            ['Section', 'Subject', 'Metric', 'Value', 'Extra 1', 'Extra 2'],
            $filename
        );
    }

    public function exportTicketWorkloadPerformance(Request $request, UserDepartmentService $userDept)
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $target = max(200, min(2000, (int) $request->get('target', 200)));
        $dataset = $this->buildTicketWorkloadPerformanceDataset($dateFrom, $dateTo, $target, $userDept);
        $months = $dataset['months'];
        // Total Worked sits right after the Normal/Work counts so it is always visible.
        $headings = ['User', 'Section', 'Normal Tickets', 'Work Tickets', 'Total Worked'];
        foreach ($months as $month) {
            $headings[] = (string) ($month['label'] ?? 'Month');
            $headings[] = (string) ($month['label'] ?? 'Month') . ' %';
        }

        $rows = $dataset['rows']->map(function ($row) use ($months) {
            $line = [
                (string) ($row->user_name ?? ''),
                (string) ($row->department ?? ''),
                (int) ($row->overall_normal ?? 0),
                (int) ($row->overall_work ?? 0),
                (int) ($row->overall_total ?? 0),
            ];
            foreach ($months as $month) {
                $key = (string) ($month['key'] ?? '');
                $line[] = (int) ($row->monthly[$key]['total'] ?? 0);
                $line[] = (float) ($row->monthly[$key]['achievement'] ?? 0);
            }

            return $line;
        })->values()->toArray();

        // Grand totals row across all users.
        $totalsRow = [
            'TOTAL (' . $dataset['rows']->count() . ' users)',
            '',
            (int) $dataset['rows']->sum('overall_normal'),
            (int) $dataset['rows']->sum('overall_work'),
            (int) $dataset['rows']->sum('overall_total'),
        ];
        foreach ($months as $month) {
            $key = (string) ($month['key'] ?? '');
            $totalsRow[] = (int) $dataset['rows']->sum(fn ($r) => (int) ($r->monthly[$key]['total'] ?? 0));
            $totalsRow[] = '';
        }
        $rows[] = $totalsRow;

        $filename = 'ticket-workload-performance-' . $dateFrom . '-to-' . $dateTo;
        if ($request->get('format') === 'csv') {
            return $this->csvResponse(
                $rows,
                $headings,
                $filename
            );
        }

        return Excel::download(new TicketWorkloadPerformanceExport($rows, $headings), $filename . '.xlsx');
    }

    /**
     * @return array{
     *   months: array<int, array{key: string, label: string}>,
     *   rows: \Illuminate\Support\Collection<int, object>,
     *   summary: array<string, int>
     * }
     */
    private function buildTicketWorkloadPerformanceDataset(string $dateFrom, string $dateTo, int $target, UserDepartmentService $userDept): array
    {
        $start = Carbon::parse($dateFrom)->startOfMonth();
        $end = Carbon::parse($dateTo)->startOfMonth();
        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ];
            $cursor->addMonth();
        }

        $rangeStart = $dateFrom . ' 00:00:00';
        $rangeEnd = $dateTo . ' 23:59:59';

        $workAssignedRows = WorkTicket::query()
            ->selectRaw("assignee_id as user_id, DATE_FORMAT(created_at, '%Y-%m') as ym, id as ticket_id")
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('assignee_id')
            ->get();
        $workWorkedRows = WorkTicketUpdate::query()
            ->selectRaw("user_id, DATE_FORMAT(created_at, '%Y-%m') as ym, work_ticket_id as ticket_id")
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('user_id')
            ->get();
        $normalAssignedRows = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereBetween('e.createdtime', [$rangeStart, $rangeEnd])
            ->whereNotNull('e.smownerid')
            ->selectRaw("e.smownerid as user_id, DATE_FORMAT(e.createdtime, '%Y-%m') as ym, t.ticketid as ticket_id")
            ->get();
        $normalReassignedRows = TicketReassignment::query()
            ->selectRaw("to_user_id as user_id, DATE_FORMAT(created_at, '%Y-%m') as ym, ticket_id")
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('to_user_id')
            ->get();

        $userIds = collect()
            ->merge($workAssignedRows->pluck('user_id'))
            ->merge($workWorkedRows->pluck('user_id'))
            ->merge($normalAssignedRows->pluck('user_id'))
            ->merge($normalReassignedRows->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $users = empty($userIds)
            ? collect()
            : VtigerUser::on('vtiger')
                ->whereIn('id', $userIds)
                ->get()
                ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name]);
        $departments = $userDept->getDepartmentsForUsers($userIds);

        $monthlyBuckets = [];
        $overallNormalByUser = [];
        $overallWorkByUser = [];

        $addInvolvement = function (int $userId, string $monthKey, string $ticketKey, string $module) use (&$monthlyBuckets, &$overallNormalByUser, &$overallWorkByUser): void {
            if ($userId <= 0 || $monthKey === '' || $ticketKey === '') {
                return;
            }
            $bucketKey = $userId . '|' . $monthKey;
            if (! isset($monthlyBuckets[$bucketKey])) {
                $monthlyBuckets[$bucketKey] = [
                    'all' => [],
                    'normal' => [],
                    'work' => [],
                ];
            }
            $monthlyBuckets[$bucketKey]['all'][$ticketKey] = true;
            $monthlyBuckets[$bucketKey][$module][$ticketKey] = true;

            if ($module === 'normal') {
                if (! isset($overallNormalByUser[$userId])) {
                    $overallNormalByUser[$userId] = [];
                }
                $overallNormalByUser[$userId][$ticketKey] = true;
            }
            if ($module === 'work') {
                if (! isset($overallWorkByUser[$userId])) {
                    $overallWorkByUser[$userId] = [];
                }
                $overallWorkByUser[$userId][$ticketKey] = true;
            }
        };

        foreach ($workAssignedRows as $row) {
            $addInvolvement((int) ($row->user_id ?? 0), (string) ($row->ym ?? ''), 'W-' . (string) ($row->ticket_id ?? ''), 'work');
        }
        foreach ($workWorkedRows as $row) {
            $addInvolvement((int) ($row->user_id ?? 0), (string) ($row->ym ?? ''), 'W-' . (string) ($row->ticket_id ?? ''), 'work');
        }
        foreach ($normalAssignedRows as $row) {
            $addInvolvement((int) ($row->user_id ?? 0), (string) ($row->ym ?? ''), 'N-' . (string) ($row->ticket_id ?? ''), 'normal');
        }
        foreach ($normalReassignedRows as $row) {
            $addInvolvement((int) ($row->user_id ?? 0), (string) ($row->ym ?? ''), 'N-' . (string) ($row->ticket_id ?? ''), 'normal');
        }

        $monthCount = max(1, count($months));

        $rows = collect($userIds)->map(function (int $userId) use ($months, $monthCount, $target, $users, $departments, $monthlyBuckets) {
            $monthly = [];
            $overall = 0;
            $normalSum = 0;
            $workSum = 0;
            $monthsMet = 0;
            $bestMonthTotal = 0;
            foreach ($months as $month) {
                $key = $month['key'];
                $lookup = $userId . '|' . $key;
                $bucket = $monthlyBuckets[$lookup] ?? ['all' => [], 'normal' => [], 'work' => []];
                $normal = count($bucket['normal']);
                $work = count($bucket['work']);
                // Within a month, normal/work keys are disjoint, so normal + work == total.
                $total = $normal + $work;
                $overall += $total;
                $normalSum += $normal;
                $workSum += $work;
                if ($total >= $target) {
                    $monthsMet++;
                }
                if ($total > $bestMonthTotal) {
                    $bestMonthTotal = $total;
                }
                $monthly[$key] = [
                    'normal' => $normal,
                    'work' => $work,
                    'total' => $total,
                    'achievement' => round(($total / $target) * 100, 1),
                    'met' => $total >= $target,
                ];
            }

            return (object) [
                'user_id' => $userId,
                'user_name' => (string) ($users[$userId] ?? ('User #' . $userId)),
                'department' => (string) ($departments[$userId] ?? ''),
                // Sum of monthly counts so Normal + Work == Total (and months sum to Total).
                'overall_normal' => $normalSum,
                'overall_work' => $workSum,
                'monthly' => $monthly,
                'overall_total' => $overall,
                'months_met' => $monthsMet,
                'months_total' => $monthCount,
                'consistency_percent' => round(($monthsMet / $monthCount) * 100, 1),
                'avg_per_month' => round($overall / $monthCount, 1),
                'best_month_total' => $bestMonthTotal,
            ];
        })->sortByDesc('overall_total')->values();

        // Aggregate monthly trend across the whole team for the trend chart.
        $monthlyTrend = [];
        foreach ($months as $month) {
            $key = $month['key'];
            $monthlyTrend[] = [
                'label' => $month['label'],
                'total' => (int) $rows->sum(fn ($r) => (int) ($r->monthly[$key]['total'] ?? 0)),
                'met_users' => (int) $rows->sum(fn ($r) => ! empty($r->monthly[$key]['met']) ? 1 : 0),
            ];
        }

        // A user is "compliant" if they met target in every month of the range.
        $fullyCompliant = $rows->filter(fn ($r) => $r->months_met >= $monthCount)->count();
        $usersCount = $rows->count();

        return [
            'months' => $months,
            'rows' => $rows,
            'summary' => [
                'users' => $usersCount,
                'total_normal' => (int) $rows->sum('overall_normal'),
                'total_work' => (int) $rows->sum('overall_work'),
                'total_worked' => (int) $rows->sum('overall_total'),
                'fully_compliant' => $fullyCompliant,
                'compliance_rate' => $usersCount > 0 ? round(($fullyCompliant / $usersCount) * 100, 1) : 0,
                'avg_per_user_month' => ($usersCount > 0 && $monthCount > 0)
                    ? round($rows->sum('overall_total') / ($usersCount * $monthCount), 1)
                    : 0,
                'months_total' => $monthCount,
                'monthly_trend' => $monthlyTrend,
                'top_performers' => $rows->take(10)->map(fn ($r) => [
                    'name' => $r->user_name,
                    'total' => (int) $r->overall_total,
                ])->values()->all(),
            ],
        ];
    }

    public function exportTicketAutomationAnalysis(Request $request)
    {
        $realIssueInsights = $this->buildRealIssueInsights();
        $formattedRows = $realIssueInsights->map(function (array $row) {
            return [
                (string) ($row['issue'] ?? 'General operations'),
                (int) ($row['count'] ?? 0),
                'Normal: ' . (int) (($row['source_mix']['Normal'] ?? 0)) . ' · Work: ' . (int) (($row['source_mix']['Work'] ?? 0)),
                (string) ($row['most_impacted_owner'] ?? 'Unassigned') . ' (' . (int) ($row['owner_load'] ?? 0) . ')',
                (string) ($row['action'] ?? ''),
            ];
        })->values()->toArray();

        $filename = 'real-issues-backlog-' . date('Y-m-d');
        if ($request->get('format') === 'csv') {
            return $this->csvResponse(
                $formattedRows,
                ['Issue Theme', 'Active Volume', 'Source Mix', 'Most Impacted Owner', 'Recommended Management Action'],
                $filename
            );
        }

        return Excel::download(new RealIssuesBacklogExport($formattedRows), $filename . '.xlsx');
    }

    public function reassignmentAudit(Request $request, UserDepartmentService $userDept): View
    {
        $limitRaw = trim((string) $request->get('limit', '200'));
        $fetchAll = strtolower($limitRaw) === 'all';
        $limit = $fetchAll ? PHP_INT_MAX : min(1000, max(50, (int) $limitRaw));
        $ticketRef = trim((string) $request->get('ticket', ''));
        $reassignments = $this->buildUnifiedAuditRows($ticketRef, $limit);
        $userIds = collect($reassignments->pluck('from_user_id')->merge($reassignments->pluck('to_user_id')))
            ->merge($reassignments->pluck('reassigned_by_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $reassignments = $reassignments->map(fn ($r) => (object) array_merge((array) $r, [
            'from_user_department' => $r->from_user_id ? ($departments[$r->from_user_id] ?? null) : null,
            'to_user_department' => $r->to_user_id ? ($departments[$r->to_user_id] ?? null) : null,
        ]));
        return view('reports.reassignment-audit', [
            'reassignments' => $reassignments,
            'limit' => $limit,
            'limitRaw' => $limitRaw,
            'fetchAll' => $fetchAll,
            'ticket' => $ticketRef,
        ]);
    }

    public function assignmentHandlers(Request $request): View
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $statusRaw = $request->get('status');
        $status = is_string($statusRaw) && trim($statusRaw) !== '' ? trim($statusRaw) : null;
        $perPage = max(10, min(200, (int) $request->get('per_page', 50)));

        $query = Ticket::listQuery()
            ->whereBetween('e.createdtime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($status) {
            $query->where('vtiger_troubletickets.status', $status);
        }

        $tickets = $query
            ->orderByDesc('e.createdtime')
            ->paginate($perPage)
            ->withQueryString();

        $ticketIds = collect($tickets->items())->pluck('ticketid')->filter()->values()->all();
        $trailByTicketId = empty($ticketIds)
            ? collect()
            : TicketReassignment::whereIn('ticket_id', $ticketIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('ticket_id');

        $rows = collect($tickets->items())->map(function ($ticket) use ($trailByTicketId) {
            $trail = $trailByTicketId->get($ticket->ticketid, collect());
            $handlers = $this->resolveTicketHandlers($ticket, $trail);

            return (object) [
                'ticketid' => $ticket->ticketid,
                'ticket_no' => $ticket->ticket_no ?? ('TT' . $ticket->ticketid),
                'title' => $ticket->title ?? '',
                'status' => $ticket->status ?? '',
                'createdtime' => $ticket->createdtime ?? null,
                'created_by' => $handlers['created_by'],
                'checked_by' => $handlers['checked_by'],
                'authorized_by' => $handlers['authorized_by'],
                'closed_by' => $handlers['closed_by'],
            ];
        });

        $rowsPaginator = new LengthAwarePaginator(
            $rows,
            $tickets->total(),
            $tickets->perPage(),
            $tickets->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.assignment-handlers', [
            'rows' => $rowsPaginator,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    public function exportAssignmentHandlers(Request $request)
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = now()->format('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $statusRaw = $request->get('status');
        $status = is_string($statusRaw) && trim($statusRaw) !== '' ? trim($statusRaw) : null;
        $limit = min(50000, max(100, (int) $request->get('limit', 20000)));

        $query = Ticket::listQuery()
            ->whereBetween('e.createdtime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($status) {
            $query->where('vtiger_troubletickets.status', $status);
        }

        $tickets = $query
            ->orderByDesc('e.createdtime')
            ->limit($limit)
            ->get();

        $ticketIds = $tickets->pluck('ticketid')->filter()->values()->all();
        $trailByTicketId = empty($ticketIds)
            ? collect()
            : TicketReassignment::whereIn('ticket_id', $ticketIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('ticket_id');

        $rows = $tickets->map(function ($ticket) use ($trailByTicketId) {
            $trail = $trailByTicketId->get($ticket->ticketid, collect());
            $handlers = $this->resolveTicketHandlers($ticket, $trail);

            return [
                $ticket->ticket_no ?? ('TT' . $ticket->ticketid),
                $ticket->title ?? '',
                $ticket->status ?? '',
                $handlers['created_by'],
                $handlers['checked_by'],
                $handlers['authorized_by'],
                $handlers['closed_by'],
                $ticket->createdtime ?? '',
            ];
        })->toArray();

        $filename = 'assignment-handlers-' . $dateFrom . '-to-' . $dateTo;
        if ($request->get('format') === 'csv') {
            return $this->csvResponse(
                $rows,
                ['Ticket', 'Title', 'Status', 'Created by', 'Checked by', 'Authorized by', 'Closed by', 'Created at'],
                $filename
            );
        }

        return Excel::download(new AssignmentHandlersExport($rows), $filename . '.xlsx');
    }

    public function exportReassignmentAudit(Request $request, UserDepartmentService $userDept)
    {
        $limitRaw = trim((string) $request->get('limit', '1000'));
        $fetchAll = strtolower($limitRaw) === 'all';
        $limit = $fetchAll ? PHP_INT_MAX : min(10000, max(50, (int) $limitRaw));
        $ticketRef = trim((string) $request->get('ticket', ''));
        $reassignments = $this->buildUnifiedAuditRows($ticketRef, $limit);
        $userIds = collect($reassignments->pluck('from_user_id')->merge($reassignments->pluck('to_user_id')))
            ->merge($reassignments->pluck('reassigned_by_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $grouped = $reassignments
            ->groupBy(function ($row) {
                $module = (string) ($row->module_type ?? 'ticket');
                $ticketNumber = trim((string) ($row->ticket_number ?? ''));
                $ticketId = trim((string) ($row->ticket_id ?? ''));
                $fallback = $module === 'work-ticket' ? ('WT-' . $ticketId) : ('TT' . $ticketId);
                return $module . '|' . ($ticketNumber !== '' ? $ticketNumber : $fallback);
            })
            ->map(fn ($rows) => $rows->sortBy(fn ($r) => $this->formatDateTimeValue($r->created_at, 'Y-m-d H:i:s'))->values());

        $maxSteps = max(1, (int) $grouped->map(fn ($rows) => $rows->count())->max());

        $headings = ['Ticket Number', 'Total Reassignments'];
        for ($i = 1; $i <= $maxSteps; $i++) {
            $headings[] = 'Step ' . $i . ' From';
            $headings[] = 'Step ' . $i . ' From Department';
            $headings[] = 'Step ' . $i . ' To';
            $headings[] = 'Step ' . $i . ' To Department';
            $headings[] = 'Step ' . $i . ' Reassigned By';
            $headings[] = 'Step ' . $i . ' Date';
            $headings[] = 'Step ' . $i . ' Time';
        }

        $rows = $grouped->map(function ($rows, $key) use ($departments, $maxSteps) {
            $parts = explode('|', (string) $key, 2);
            $ticketNumber = $parts[1] ?? ('TT' . ($rows->first()->ticket_id ?? ''));
            $line = [$ticketNumber, $rows->count()];

            for ($i = 0; $i < $maxSteps; $i++) {
                $row = $rows->get($i);
                if (! $row) {
                    array_push($line, null, null, null, null, null, null, null);
                    continue;
                }

                array_push(
                    $line,
                    $row->from_user_name ?? 'Unassigned',
                    $departments[$row->from_user_id ?? 0] ?? '',
                    $row->to_user_name ?? '—',
                    $departments[$row->to_user_id ?? 0] ?? '',
                    $row->reassigned_by_name ?? '—',
                    $this->formatDateTimeValue($row->created_at, 'Y-m-d'),
                    $this->formatDateTimeValue($row->created_at, 'H:i:s')
                );
            }

            return $line;
        })->values()->toArray();

        if ($request->get('format') === 'xlsx') {
            return Excel::download(new \App\Exports\ReassignmentAuditExport($rows, $headings), 'ticket-reassignment-audit-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse(
            $rows,
            $headings,
            'ticket-reassignment-audit'
        );
    }

    private function buildUnifiedAuditRows(string $ticketRef, int $limit): Collection
    {
        $ticketRefNormalized = strtoupper(trim($ticketRef));
        $ticketDigits = $this->parseTicketDigits($ticketRef);

        $crmRows = $this->getCrmTicketAuditRows($ticketRef, $limit);
        $workRows = $this->getWorkTicketAuditRows($ticketRef, $limit);

        $rows = collect($crmRows->all())
            ->merge(collect($workRows->all()))
            ->map(fn ($row) => (object) ((array) $row))
            ->filter(function ($row) use ($ticketRefNormalized, $ticketDigits) {
                if ($ticketRefNormalized === '') {
                    return true;
                }

                $ticketNumber = strtoupper(trim((string) ($row->ticket_number ?? '')));
                $ticketId = trim((string) ($row->ticket_id ?? ''));
                if ($ticketNumber !== '' && str_contains($ticketNumber, $ticketRefNormalized)) {
                    return true;
                }

                return $ticketDigits !== null && $ticketId !== '' && $ticketId === $ticketDigits;
            })
            ->sortByDesc(function ($row) {
                return $this->formatDateTimeValue($row->created_at, 'Y-m-d H:i:s');
            })
            ->values();

        // When user searches by ticket reference, do not trim again at the merged level.
        // This prevents older matching events from being hidden by unrelated "recent" rows.
        if (trim($ticketRef) !== '') {
            return $rows;
        }

        return $rows->take($limit)->values();
    }

    private function getCrmTicketAuditRows(string $ticketRef, int $limit): Collection
    {
        $forSpecificTicket = trim($ticketRef) !== '';
        $query = TicketReassignment::query();
        $ticketIds = $this->resolveCrmTicketIdsFromReference($ticketRef, $limit);
        if (! empty($ticketIds)) {
            $query->whereIn('ticket_id', $ticketIds)->orderBy('created_at');
        } elseif ($ticketRef !== '') {
            // Ticket filter provided but no matching CRM ticket found.
            return collect();
        } else {
            $query->orderByDesc('created_at');
        }

        $reassignmentRowsQuery = $forSpecificTicket ? $query : $query->limit($limit);
        $reassignmentRows = $reassignmentRowsQuery->get()->map(function (TicketReassignment $row) {
            return (object) array_merge($row->toArray(), [
                'module_type' => 'ticket',
                'event_type' => 'Reassigned',
                'ticket_number' => 'TT' . $row->ticket_id,
            ]);
        });

        // Keep default report behavior ("original") as reassignment-focused.
        // Only add Created rows when user searches a specific ticket.
        if (trim($ticketRef) === '') {
            return $reassignmentRows->values();
        }

        $createdQuery = Ticket::listQuery();
        if (! empty($ticketIds)) {
            $createdQuery->whereIn('vtiger_troubletickets.ticketid', $ticketIds);
        }

        $createdRows = $createdQuery
            ->orderByDesc('e.createdtime')
            ->when(! $forSpecificTicket, fn ($q) => $q->limit($limit))
            ->get()
            ->map(function ($ticket) {
                $assigneeName = trim((string) ($ticket->assigned_to_name ?? ''));
                $creatorName = trim((string) ($ticket->created_by_name ?? ''));

                return (object) [
                    'module_type' => 'ticket',
                    'event_type' => 'Created',
                    'ticket_id' => (string) ($ticket->ticketid ?? ''),
                    'ticket_number' => $ticket->ticket_no ?: ('TT' . ($ticket->ticketid ?? '')),
                    'from_user_id' => null,
                    'from_user_name' => '—',
                    'to_user_id' => (int) ($ticket->smownerid ?? 0),
                    'to_user_name' => $assigneeName !== '' ? $assigneeName : 'Unassigned',
                    'reassigned_by_user_id' => (int) ($ticket->smcreatorid ?? 0),
                    'reassigned_by_name' => $creatorName !== '' ? $creatorName : 'System',
                    'created_at' => $ticket->createdtime,
                ];
            });

        return $reassignmentRows
            ->merge($createdRows)
            ->sortByDesc(function ($row) {
                return $this->formatDateTimeValue($row->created_at, 'Y-m-d H:i:s');
            })
            ->take($limit)
            ->values();
    }

    private function resolveCrmTicketIdsFromReference(string $ticketRef, int $limit): array
    {
        $ticketRef = trim($ticketRef);
        if ($ticketRef === '') {
            return [];
        }

        $ids = collect();
        $ticketDigits = $this->parseTicketDigits($ticketRef);
        if ($ticketDigits !== null) {
            $ids->push($ticketDigits);
        }

        $matchedByNumber = DB::connection('vtiger')
            ->table('vtiger_troubletickets')
            ->where('ticket_no', 'like', '%' . $ticketRef . '%')
            ->limit($limit)
            ->get(['ticketid'])
            ->pluck('ticketid');

        return $ids
            ->merge($matchedByNumber)
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function parseTicketDigits(string $ticketRef): ?string
    {
        $ticketRef = trim($ticketRef);
        if ($ticketRef === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $ticketRef) ?? '';
        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : null;
    }

    private function getWorkTicketAuditRows(string $ticketRef, int $limit): Collection
    {
        $forSpecificTicket = trim($ticketRef) !== '';
        $workQuery = WorkTicket::query()
            ->orderByDesc('created_at');
        if ($ticketRef !== '') {
            $workQuery->where(function ($q) use ($ticketRef): void {
                $q->where('ticket_no', 'like', '%' . $ticketRef . '%')
                    ->orWhere('title', 'like', '%' . $ticketRef . '%');
            });
        }

        $workTickets = $forSpecificTicket ? $workQuery->get() : $workQuery->limit($limit)->get();
        if ($workTickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $workTickets->pluck('id')->all();
        $updatesByTicket = WorkTicketUpdate::query()
            ->whereIn('work_ticket_id', $ticketIds)
            ->orderByDesc('created_at')
            ->when(! $forSpecificTicket, fn ($q) => $q->limit($limit * 3))
            ->get()
            ->groupBy('work_ticket_id');

        $userIds = $workTickets->flatMap(function (WorkTicket $ticket) use ($updatesByTicket) {
            return collect([(int) $ticket->assignee_id, (int) $ticket->created_by])
                ->merge($updatesByTicket->get($ticket->id, collect())->pluck('user_id')->map(fn ($id) => (int) $id));
        })->filter(fn ($id) => $id > 0)->unique()->values()->all();

        $users = VtigerUser::on('vtiger')
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name])
            ->toArray();
        $name = fn (?int $id): ?string => ($id && isset($users[$id])) ? $users[$id] : null;

        $events = collect();
        foreach ($workTickets as $ticket) {
            $events->push((object) [
                'module_type' => 'work-ticket',
                'event_type' => 'Created',
                'ticket_id' => (int) $ticket->id,
                'ticket_number' => (string) $ticket->ticket_no,
                'from_user_id' => null,
                'from_user_name' => '—',
                'to_user_id' => (int) $ticket->assignee_id,
                'to_user_name' => $name((int) $ticket->assignee_id) ?? ('User #' . (int) $ticket->assignee_id),
                'reassigned_by_user_id' => (int) $ticket->created_by,
                'reassigned_by_name' => $name((int) $ticket->created_by) ?? ('User #' . (int) $ticket->created_by),
                'created_at' => $ticket->created_at,
                'is_work_ticket' => true,
            ]);

            foreach ($updatesByTicket->get($ticket->id, collect()) as $update) {
                $events->push((object) [
                    'module_type' => 'work-ticket',
                    'event_type' => !empty($update->status_after_update) ? ('Update: ' . $update->status_after_update) : 'Updated',
                    'ticket_id' => (int) $ticket->id,
                    'ticket_number' => (string) $ticket->ticket_no,
                    'from_user_id' => (int) $ticket->assignee_id,
                    'from_user_name' => $name((int) $ticket->assignee_id) ?? ('User #' . (int) $ticket->assignee_id),
                    'to_user_id' => (int) ($update->user_id ?? 0),
                    'to_user_name' => $name((int) ($update->user_id ?? 0)) ?? ('User #' . (int) ($update->user_id ?? 0)),
                    'reassigned_by_user_id' => (int) ($update->user_id ?? 0),
                    'reassigned_by_name' => $name((int) ($update->user_id ?? 0)) ?? ('User #' . (int) ($update->user_id ?? 0)),
                    'created_at' => $update->created_at,
                    'is_work_ticket' => true,
                ]);
            }
        }

        $sorted = $events->sortByDesc(function ($row) {
            return $this->formatDateTimeValue($row->created_at, 'Y-m-d H:i:s');
        });

        if ($forSpecificTicket) {
            return $sorted->values();
        }

        return $sorted->take($limit)->values();
    }

    public function exportSlaBroken(TicketSlaService $sla, UserDepartmentService $userDept, Request $request)
    {
        $tickets = $sla->getBrokenSlaTickets(500);
        $userIds = $tickets->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $rows = $tickets->map(fn ($t) => [
            $t->ticket_no ?? 'TT' . $t->ticketid,
            $t->title ?? '',
            $t->category ?? 'General',
            $t->status ?? '',
            trim(($t->owner_first ?? '') . ' ' . ($t->owner_last ?? '')) ?: 'Unassigned',
            $departments[$t->smownerid ?? 0] ?? '',
            trim(($t->contact_first ?? '') . ' ' . ($t->contact_last ?? '')) ?: '',
            $t->createdtime ?? '',
            isset($t->due_at) ? $t->due_at->format('Y-m-d H:i:s') : '',
            ($t->status ?? '') === 'Closed' && isset($t->breached_at) ? $t->breached_at->format('Y-m-d H:i:s') : 'Still open',
            $t->tat_hours ?? 24,
            $t->hours_overdue ?? 0,
        ])->toArray();
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new SlaBrokenExport($rows), 'broken-sla-report-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse($rows, ['Ticket', 'Title', 'Department', 'Status', 'Assigned to', 'User Dept', 'Contact', 'Created', 'Due by', 'Resolved at', 'TAT (h)', 'Hours Overdue'], 'broken-sla-report');
    }

    public function exportTicketAging(CrmService $crm, UserDepartmentService $userDept, Request $request)
    {
        $days = (int) $request->get('days', 7);
        $tickets = $crm->getTicketAgingReport($days, 500);
        $userIds = $tickets->pluck('smownerid')->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $rows = $tickets->map(fn ($t) => [
            $t->ticket_no ?? 'TT' . $t->ticketid,
            $t->title ?? '',
            $t->status ?? '',
            $t->category ?? 'General',
            trim(($t->firstname ?? '') . ' ' . ($t->lastname ?? '')) ?: '',
            $t->createdtime ?? '',
            trim(($t->owner_first ?? '') . ' ' . ($t->owner_last ?? '')) ?: 'Unassigned',
            $departments[$t->smownerid ?? 0] ?? '',
        ])->toArray();
        $filename = 'ticket-aging-' . $days . 'd-' . date('Y-m-d');
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new TicketAgingExport($rows), $filename . '.xlsx');
        }
        return $this->csvResponse($rows, ['Ticket', 'Title', 'Status', 'Category', 'Contact', 'Created', 'Assigned To', 'User Dept'], 'ticket-aging-' . $days . 'd');
    }

    public function exportSalesByPerson(CrmService $crm, Request $request)
    {
        $data = $crm->getSalesByPerson(100);
        $rows = $data->map(fn ($r) => [trim($r->name) ?: 'Unassigned', $r->total])->toArray();
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new SalesByPersonExport($rows), 'sales-by-person-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse(array_map(fn ($r) => [$r[0], number_format($r[1], 0)], $rows), ['Salesperson', 'Revenue (KES)']);
    }

    public function exportPipelineByStage(CrmService $crm, Request $request)
    {
        $data = $crm->getPipelineByStage();
        $rows = [];
        foreach ($data as $stage => $d) {
            $rows[] = [$stage, $d['count'], $d['amount']];
        }
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new PipelineByStageExport($rows), 'pipeline-by-stage-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse(array_map(fn ($r) => [$r[0], $r[1], number_format($r[2], 0)], $rows), ['Stage', 'Count', 'Amount (KES)']);
    }

    public function exportAllExcel(CrmService $crm, TicketSlaService $sla, Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'reports-all-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new \App\Exports\ReportsAllExport($crm, $sla, (int) $request->get('days', 7)), $filename);
    }

    public function bouncedEmailsReport(): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $pattern = storage_path('app/reports/bounced_emails_life_geminialife_co_ke_*.txt');
        $files = glob($pattern) ?: [];
        $generatedAt = date('Y-m-d H:i:s');
        $filename = 'bounced-emails-' . date('Y-m-d') . '.xlsx';

        if (empty($files)) {
            $rows = [[
                '',
                '',
                'No bounced emails source file was found. Expected path pattern: ' . $pattern,
                'N/A',
                $generatedAt,
            ]];

            return Excel::download(new BouncedEmailsExport($rows), $filename);
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $latest = $files[0];
        $lines = @file($latest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $sourceFile = basename($latest);

        $rows = collect($lines)
            ->map(fn (string $line) => $this->parseBouncedEmailLine($line))
            ->filter(fn (?array $row) => ! empty($row))
            ->map(fn (array $row) => [
                $row['email'],
                $row['smtp_code'],
                $row['reason'],
                $sourceFile,
                $generatedAt,
            ])
            ->values()
            ->toArray();

        if (empty($rows)) {
            $rows = collect($lines)
                ->map(fn (string $line) => trim($line))
                ->filter(fn (string $line) => $line !== '')
                ->map(fn (string $line) => [
                    '',
                    '',
                    $line,
                    $sourceFile,
                    $generatedAt,
                ])
                ->values()
                ->toArray();
        }

        if (empty($rows)) {
            $rows = [[
                '',
                '',
                'Bounced emails file was found but it appears empty.',
                $sourceFile,
                $generatedAt,
            ]];
        }

        return Excel::download(new BouncedEmailsExport($rows), $filename);
    }

    protected function csvResponse(array $rows, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Response::streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename . '-' . date('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"',
        ]);
    }

    private function resolveTicketHandlers($ticket, $trail): array
    {
        $assignees = collect();
        if ($trail && $trail->isNotEmpty()) {
            $firstFrom = $this->normalizeTicketUserName($trail->first()->from_user_name ?? null);
            if ($firstFrom) {
                $assignees->push($firstFrom);
            }
            foreach ($trail as $item) {
                $toName = $this->normalizeTicketUserName($item->to_user_name ?? null);
                if ($toName) {
                    $assignees->push($toName);
                }
            }
        }

        $currentAssignee = $this->normalizeTicketUserName($ticket->assigned_to_name ?? null);
        if ($assignees->isEmpty() && $currentAssignee) {
            $assignees->push($currentAssignee);
        }

        $createdBy = $this->normalizeTicketUserName($ticket->created_by_name ?? null) ?? '—';
        $closedBy = $this->normalizeTicketUserName($ticket->closed_by_name ?? null);
        if (! $closedBy && (($ticket->status ?? '') !== 'Closed')) {
            $closedBy = 'Not closed yet';
        }

        return [
            'created_by' => $createdBy,
            'checked_by' => $assignees->get(0) ?? '—',
            'authorized_by' => $assignees->get(2) ?? '—',
            'closed_by' => $closedBy ?: '—',
        ];
    }

    private function normalizeTicketUserName($name): ?string
    {
        $value = trim((string) ($name ?? ''));
        if ($value === '' || $value === '—' || strcasecmp($value, 'Unassigned') === 0) {
            return null;
        }
        return $value;
    }

    private function parseTicketIdFromReference(string $ticketRef): ?int
    {
        $ticketRef = trim($ticketRef);
        if ($ticketRef === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $ticketRef) ?? '';
        if ($digits === '') {
            return null;
        }
        $ticketId = (int) $digits;
        return $ticketId > 0 ? $ticketId : null;
    }

    private function formatDateTimeValue($value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format($format);
        }
        if (is_string($value) && trim($value) !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date($format, $ts);
            }
        }

        return '';
    }

    private function parseBouncedEmailLine(string $line): ?array
    {
        $raw = trim($line);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $email = trim((string) ($decoded['email'] ?? $decoded['recipient'] ?? $decoded['to'] ?? ''));
            $reason = trim((string) ($decoded['reason'] ?? $decoded['error'] ?? $decoded['message'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                preg_match('/\b([245]\d{2})\b/', $reason, $smtpCode);

                return [
                    'email' => $email,
                    'smtp_code' => $smtpCode[1] ?? '',
                    'reason' => $reason !== '' ? $reason : 'Reason not provided',
                ];
            }
        }

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $emailMatch);
        if (empty($emailMatch[0])) {
            return null;
        }

        $email = trim($emailMatch[0]);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $reason = trim((string) preg_replace('/' . preg_quote($email, '/') . '/i', '', $raw, 1));
        $reason = trim($reason, " \t\n\r\0\x0B-:|,;");
        if ($reason === '') {
            $reason = 'Reason not provided';
        }

        preg_match('/\b([245]\d{2})\b/', $reason, $smtpCode);

        return [
            'email' => $email,
            'smtp_code' => $smtpCode[1] ?? '',
            'reason' => $reason,
        ];
    }

    private function buildRealIssueInsights(): Collection
    {
        $normalActive = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereIn('t.status', ['Open', 'In Progress', 'Wait For Response'])
            ->select('t.category', 'e.smownerid')
            ->get();

        $normalOwnerIds = $normalActive->pluck('smownerid')->filter()->unique()->values()->all();
        $normalOwners = empty($normalOwnerIds)
            ? []
            : DB::connection('vtiger')->table('vtiger_users')
                ->whereIn('id', $normalOwnerIds)
                ->get(['id', 'first_name', 'last_name', 'user_name'])
                ->mapWithKeys(function ($u) {
                    $name = trim((string) ($u->first_name ?? '') . ' ' . (string) ($u->last_name ?? ''));
                    return [(int) $u->id => ($name !== '' ? $name : (string) ($u->user_name ?? ('User #' . $u->id)))];
                })
                ->toArray();

        $workActive = WorkTicket::query()
            ->whereIn('status', ['Open', 'In Progress', 'Blocked'])
            ->select('title', 'description', 'assignee_id')
            ->get();

        $workOwnerIds = $workActive->pluck('assignee_id')->filter()->unique()->values()->all();
        $workOwners = empty($workOwnerIds)
            ? []
            : VtigerUser::on('vtiger')
                ->whereIn('id', $workOwnerIds)
                ->get()
                ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name])
                ->toArray();

        $issueBuckets = collect();
        foreach ($normalActive as $row) {
            $issue = trim((string) ($row->category ?? ''));
            $theme = $issue !== '' ? $issue : 'General';
            $issueBuckets->push([
                'source' => 'Normal',
                'theme' => $theme,
                'owner_name' => $normalOwners[(int) ($row->smownerid ?? 0)] ?? 'Unassigned',
            ]);
        }

        foreach ($workActive as $row) {
            $theme = $this->classifyIssueTheme((string) ($row->title ?? '') . ' ' . (string) ($row->description ?? ''));
            $issueBuckets->push([
                'source' => 'Work',
                'theme' => $theme,
                'owner_name' => $workOwners[(int) ($row->assignee_id ?? 0)] ?? ('User #' . (int) ($row->assignee_id ?? 0)),
            ]);
        }

        return $issueBuckets
            ->groupBy('theme')
            ->map(function ($rows, $theme) {
                $owners = collect($rows)->groupBy('owner_name')->map->count()->sortDesc();
                return [
                    'issue' => (string) $theme,
                    'count' => (int) collect($rows)->count(),
                    'source_mix' => collect($rows)->groupBy('source')->map->count()->map(fn ($v) => (int) $v)->toArray(),
                    'most_impacted_owner' => (string) ($owners->keys()->first() ?? 'Unassigned'),
                    'owner_load' => (int) ($owners->first() ?? 0),
                    'action' => $this->recommendedActionForIssue((string) $theme),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(20);
    }

    private function classifyIssueTheme(string $text): string
    {
        $text = strtolower($text);
        $maps = [
            'Claims processing' => ['claim', 'claims', 'benefit', 'settlement', 'hospital', 'admission'],
            'Policy servicing' => ['policy', 'endorsement', 'amendment', 'correction', 'certificate'],
            'Renewals and maturities' => ['renewal', 'maturity', 'lapse', 'reinstatement'],
            'Payment and finance' => ['payment', 'receipt', 'invoice', 'premium', 'refund', 'reconciliation'],
            'System access and support' => ['login', 'password', 'system', 'error', 'bug', 'access', 'portal', 'sync'],
            'Customer follow-up' => ['follow up', 'callback', 'response', 'email', 'sms', 'client'],
            'Document and compliance' => ['document', 'compliance', 'kyc', 'approval', 'audit', 'form'],
        ];

        foreach ($maps as $theme => $tokens) {
            foreach ($tokens as $token) {
                if (str_contains($text, $token)) {
                    return $theme;
                }
            }
        }

        return 'General operations';
    }

    private function recommendedActionForIssue(string $theme): string
    {
        return match ($theme) {
            'Claims processing' => 'Automate claim triage, SLA alerts, and fast-lane assignment for high-impact claims.',
            'Policy servicing' => 'Automate policy update workflows with standardized forms and validation checks.',
            'Renewals and maturities' => 'Automate renewal reminder journeys and maturity follow-up tasks.',
            'Payment and finance' => 'Automate payment exception matching and aging finance ticket escalations.',
            'System access and support' => 'Automate first-line troubleshooting and route repeated issues to IT queue.',
            'Customer follow-up' => 'Automate multi-channel follow-up nudges and closure reminders.',
            'Document and compliance' => 'Automate document checklist validation and compliance approval routing.',
            default => 'Automate assignment, stale-ticket reminders, and manager escalation for unresolved cases.',
        };
    }
}
