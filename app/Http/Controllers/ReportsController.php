<?php

namespace App\Http\Controllers;

use App\Exports\PipelineByStageExport;
use App\Exports\SalesByPersonExport;
use App\Exports\SlaBrokenExport;
use App\Exports\TicketAgingExport;
use App\Exports\TicketsByDateRangeExport;
use App\Exports\ManagementUsageExport;
use App\Exports\AssignmentHandlersExport;
use App\Exports\BouncedEmailsExport;
use App\Models\Ticket;
use App\Models\TicketReassignment;
use App\Services\CrmService;
use App\Services\TicketSlaService;
use App\Services\UserDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
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
        $tickets = $sla->getBrokenSlaTickets(100);
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
        $tickets = $crm->getTicketAgingReport($days, 200);
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

        $total = $crm->countTicketsByDateRange($dateFrom, $dateTo, $status, $search, $assignedTo, null, $onlyWithContact);
        $rowCollection = $crm->getTicketsByDateRange($dateFrom, $dateTo, $perPage, $offset, $status, $search, $assignedTo, null, $onlyWithContact);

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
        $summary = $crm->getContactsSummaryReport($days);
        return view('reports.contacts-summary', $summary);
    }

    public function callsSummary(CrmService $crm): View
    {
        $data = $crm->getCallsSummaryReport();
        return view('reports.calls-summary', $data);
    }

    public function managementUsage(CrmService $crm, Request $request): View
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $dateTo = (string) $request->get('date_to', now()->format('Y-m-d'));
        $data = $crm->getManagementUsageReport($dateFrom, $dateTo);

        return view('reports.management-usage', $data);
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

    public function reassignmentAudit(Request $request, UserDepartmentService $userDept): View
    {
        $limit = min(1000, max(50, (int) $request->get('limit', 200)));
        $ticketRef = trim((string) $request->get('ticket', ''));
        $ticketId = $this->parseTicketIdFromReference($ticketRef);

        $query = \App\Models\TicketReassignment::query();
        if ($ticketId !== null) {
            $query->where('ticket_id', $ticketId)->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $reassignments = $query->limit($limit)->get();
        $userIds = collect($reassignments->pluck('from_user_id')->merge($reassignments->pluck('to_user_id')))->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $reassignments = $reassignments->map(fn ($r) => (object) array_merge($r->toArray(), [
            'from_user_department' => $r->from_user_id ? ($departments[$r->from_user_id] ?? null) : null,
            'to_user_department' => $r->to_user_id ? ($departments[$r->to_user_id] ?? null) : null,
        ]));
        return view('reports.reassignment-audit', [
            'reassignments' => $reassignments,
            'limit' => $limit,
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

    public function exportAssignmentHandlers(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
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

        $filename = 'assignment-handlers-' . $dateFrom . '-to-' . $dateTo . '.xlsx';
        return Excel::download(new AssignmentHandlersExport($rows), $filename);
    }

    public function exportReassignmentAudit(Request $request, UserDepartmentService $userDept)
    {
        $limit = min(10000, max(50, (int) $request->get('limit', 1000)));
        $ticketRef = trim((string) $request->get('ticket', ''));
        $ticketId = $this->parseTicketIdFromReference($ticketRef);

        $query = \App\Models\TicketReassignment::query();
        if ($ticketId !== null) {
            $query->where('ticket_id', $ticketId)->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $reassignments = $query->limit($limit)->get();
        $userIds = collect($reassignments->pluck('from_user_id')->merge($reassignments->pluck('to_user_id')))->filter()->unique()->values()->all();
        $departments = $userDept->getDepartmentsForUsers($userIds);
        $groupedByTicket = $reassignments
            ->groupBy('ticket_id')
            ->map(function ($trail) {
                return $trail->sortBy(function ($row) {
                    return $this->formatDateTimeValue($row->created_at, 'Y-m-d H:i:s');
                })->values();
            });

        $maxSteps = (int) $groupedByTicket->map(fn ($trail) => $trail->count())->max();
        $maxSteps = max(1, $maxSteps);

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

        $rows = $groupedByTicket->map(function ($trail, $ticketId) use ($maxSteps, $departments) {
            $row = ['TT' . $ticketId, $trail->count()];
            for ($i = 0; $i < $maxSteps; $i++) {
                $step = $trail->get($i);
                if ($step === null) {
                    array_push($row, '', '', '', '', '', '', '');
                    continue;
                }
                array_push(
                    $row,
                    $step->from_user_name ?? 'Unassigned',
                    $departments[$step->from_user_id ?? 0] ?? '',
                    $step->to_user_name ?? '—',
                    $departments[$step->to_user_id ?? 0] ?? '',
                    $step->reassigned_by_name ?? '—',
                    $this->formatDateTimeValue($step->created_at, 'Y-m-d'),
                    $this->formatDateTimeValue($step->created_at, 'H:i:s')
                );
            }

            return $row;
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
}
