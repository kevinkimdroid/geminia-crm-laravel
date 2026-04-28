<?php

namespace App\Http\Controllers;

use App\Models\UserReportingLine;
use App\Models\VtigerUser;
use App\Models\WorkTicket;
use App\Models\WorkTicketUpdate;
use App\Services\WorkTicketNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkTicketController extends Controller
{
    public function __construct(
        protected WorkTicketNotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user, 401);

        $requestedScope = (string) $request->get('scope', 'mine');
        $reporteeIds = $this->getReporteeIds((int) $user->id);
        $canSeeAll = $user->isAdministrator();
        $canSeeTeam = $canSeeAll || !empty($reporteeIds);

        $scope = in_array($requestedScope, ['mine', 'team', 'all'], true) ? $requestedScope : 'mine';
        if ($scope === 'all' && !$canSeeAll) {
            $scope = 'mine';
        }
        if ($scope === 'team' && !$canSeeTeam) {
            $scope = 'mine';
        }

        $status = trim((string) $request->get('status', ''));
        $search = trim((string) $request->get('search', ''));

        $query = WorkTicket::query();
        $this->applyVisibilityScope($query, (int) $user->id, $scope, $canSeeAll, $reporteeIds);

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('ticket_no', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $tickets = $query
            ->orderByRaw("CASE status
                WHEN 'Blocked' THEN 1
                WHEN 'In Progress' THEN 2
                WHEN 'Open' THEN 3
                WHEN 'Done' THEN 4
                WHEN 'Closed' THEN 4
                WHEN 'Cancelled' THEN 5
                ELSE 6
            END")
            ->orderBy('updated_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $userIds = $tickets->getCollection()
            ->flatMap(fn (WorkTicket $t) => [(int) $t->assignee_id, (int) ($t->reporting_manager_id ?? 0), (int) $t->created_by])
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $usersById = $this->getUsersById($userIds);

        $statBase = WorkTicket::query();
        $this->applyVisibilityScope($statBase, (int) $user->id, $scope, $canSeeAll, $reporteeIds);
        $stats = [
            'total' => (clone $statBase)->count(),
            'open' => (clone $statBase)->where('status', 'Open')->count(),
            'in_progress' => (clone $statBase)->where('status', 'In Progress')->count(),
            'blocked' => (clone $statBase)->where('status', 'Blocked')->count(),
            'done' => (clone $statBase)->whereIn('status', ['Done', 'Closed'])->count(),
            'due_today' => (clone $statBase)->whereDate('due_date', now()->toDateString())->count(),
            'tat_breached' => (clone $statBase)
                ->whereNotNull('tat_due_at')
                ->where(function (Builder $q): void {
                    $q->where(function (Builder $open): void {
                        $open->whereNotIn('status', ['Done', 'Closed'])->where('tat_due_at', '<', now());
                    })->orWhere(function (Builder $closed): void {
                        $closed->whereIn('status', ['Done', 'Closed'])
                            ->whereNotNull('completed_at')
                            ->whereColumn('completed_at', '>', 'tat_due_at');
                    });
                })
                ->count(),
        ];

        return view('work-tickets.index', [
            'tickets' => $tickets,
            'usersById' => $usersById,
            'scope' => $scope,
            'canSeeAll' => $canSeeAll,
            'canSeeTeam' => $canSeeTeam,
            'status' => $status,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    public function create(): View
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user, 401);

        $users = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $managerMap = UserReportingLine::query()->pluck('manager_id', 'user_id')->toArray();
        $userNamesById = $users
            ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name])
            ->toArray();

        return view('work-tickets.create', [
            'users' => $users,
            'managerMap' => $managerMap,
            'userNamesById' => $userNamesById,
            'canManageReportingLines' => $user->isAdministrator(),
            'tatByPriority' => $this->getPriorityTatMap(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'status' => 'required|in:Open,In Progress,Blocked,Done,Closed,Cancelled',
            'priority' => 'required|in:Low,Medium,High,Urgent',
            'assignee_id' => 'required|integer|min:1',
            'reporting_manager_id' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date',
            'tat_hours' => 'nullable|integer|min:1|max:720',
            'initial_update' => 'nullable|string|max:20000',
        ]);

        $status = $this->normalizeStatus((string) $validated['status']);
        $now = now();
        $assigneeId = (int) $validated['assignee_id'];
        $tatHours = !empty($validated['tat_hours']) ? (int) $validated['tat_hours'] : $this->getTatHoursForPriority((string) $validated['priority']);
        $tatDueAt = $now->copy()->addHours($tatHours);
        $dueDate = $validated['due_date'] ?? $tatDueAt->toDateString();
        $autoManagerId = UserReportingLine::query()
            ->where('user_id', $assigneeId)
            ->value('manager_id');

        $ticket = WorkTicket::create([
            'ticket_no' => $this->generateTicketNo(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'priority' => $validated['priority'],
            'assignee_id' => $assigneeId,
            // Auto-assign reporting manager from mapping table.
            'reporting_manager_id' => $autoManagerId ? (int) $autoManagerId : (!empty($validated['reporting_manager_id']) ? (int) $validated['reporting_manager_id'] : null),
            'created_by' => (int) $user->id,
            'due_date' => $dueDate,
            'tat_hours' => $tatHours,
            'tat_due_at' => $tatDueAt,
            'tat_breached_at' => null,
            'started_at' => $status === 'In Progress' ? $now : null,
            'completed_at' => $status === 'Done' ? $now : null,
        ]);

        $this->updateTatBreachState($ticket);
        $this->notifications->notifyCreated($ticket);

        if (!empty(trim((string) ($validated['initial_update'] ?? '')))) {
            WorkTicketUpdate::create([
                'work_ticket_id' => $ticket->id,
                'user_id' => (int) $user->id,
                'update_text' => trim((string) $validated['initial_update']),
                'status_after_update' => $status,
                'work_mode' => 'Remote',
            ]);
        }

        return redirect()
            ->route('work-tickets.show', $ticket)
            ->with('success', 'Work ticket created successfully.');
    }

    public function reportingLines(): View
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user && $user->isAdministrator(), 403);

        $users = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $reportingMap = UserReportingLine::query()
            ->pluck('manager_id', 'user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return view('work-tickets.reporting-lines', [
            'users' => $users,
            'reportingMap' => $reportingMap,
        ]);
    }

    public function saveReportingLines(Request $request): RedirectResponse
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user && $user->isAdministrator(), 403);

        $validated = $request->validate([
            'manager' => 'nullable|array',
            'manager.*' => 'nullable|integer|min:1',
        ]);

        $activeUserIds = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $incoming = collect($validated['manager'] ?? [])
            ->mapWithKeys(function ($managerId, $userId) {
                return [(int) $userId => $managerId ? (int) $managerId : null];
            })
            ->filter(fn ($_, $userId) => $userId > 0)
            ->filter(fn ($_, $userId) => in_array((int) $userId, $activeUserIds, true));

        $rows = [];
        foreach ($incoming as $userId => $managerId) {
            if (!$managerId || $userId === $managerId) {
                continue;
            }
            if (!in_array($managerId, $activeUserIds, true)) {
                continue;
            }
            $rows[] = [
                'user_id' => $userId,
                'manager_id' => $managerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows, $activeUserIds): void {
            $keptUserIds = collect($rows)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            UserReportingLine::query()
                ->whereIn('user_id', $activeUserIds)
                ->when(!empty($keptUserIds), fn ($q) => $q->whereNotIn('user_id', $keptUserIds))
                ->when(empty($keptUserIds), fn ($q) => $q)
                ->delete();

            if (!empty($rows)) {
                UserReportingLine::query()->upsert(
                    $rows,
                    ['user_id'],
                    ['manager_id', 'updated_at']
                );
            }
        });

        return redirect()
            ->route('work-tickets.reporting-lines')
            ->with('success', 'Reporting managers updated successfully.');
    }

    public function show(WorkTicket $workTicket): View
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user, 401);

        abort_unless($this->canAccessTicket((int) $user->id, $user->isAdministrator(), $workTicket), 403);

        $updates = WorkTicketUpdate::query()
            ->where('work_ticket_id', $workTicket->id)
            ->latest()
            ->get();

        $userIds = collect([$workTicket->assignee_id, $workTicket->reporting_manager_id, $workTicket->created_by])
            ->merge($updates->pluck('user_id')->all())
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $usersById = $this->getUsersById($userIds);

        return view('work-tickets.show', [
            'ticket' => $workTicket,
            'updates' => $updates,
            'usersById' => $usersById,
            'canSeeAll' => $user->isAdministrator(),
            'canSeeTeam' => $user->isAdministrator() || !empty($this->getReporteeIds((int) $user->id)),
        ]);
    }

    public function storeUpdate(Request $request, WorkTicket $workTicket): RedirectResponse
    {
        $user = Auth::guard('vtiger')->user();
        abort_unless($user, 401);

        abort_unless($this->canAccessTicket((int) $user->id, $user->isAdministrator(), $workTicket), 403);

        $validated = $request->validate([
            'update_text' => 'required|string|max:20000',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:1|max:1440',
            'work_mode' => 'nullable|in:Remote,Office,Field',
            'status_after_update' => 'nullable|in:Open,In Progress,Blocked,Done,Closed,Cancelled',
            'is_blocked' => 'nullable|boolean',
            'blocker_reason' => 'nullable|string|max:20000',
        ]);

        $isBlocked = (bool) ($validated['is_blocked'] ?? false);
        WorkTicketUpdate::create([
            'work_ticket_id' => $workTicket->id,
            'user_id' => (int) $user->id,
            'update_text' => trim($validated['update_text']),
            'progress_percent' => $validated['progress_percent'] ?? null,
            'time_spent_minutes' => $validated['time_spent_minutes'] ?? null,
            'work_mode' => $validated['work_mode'] ?? null,
            'status_after_update' => $validated['status_after_update'] ?? null,
            'is_blocked' => $isBlocked,
            'blocker_reason' => $validated['blocker_reason'] ?? null,
        ]);

        $statusAfterUpdate = isset($validated['status_after_update'])
            ? $this->normalizeStatus((string) $validated['status_after_update'])
            : null;
        if ($isBlocked && $statusAfterUpdate === null) {
            $statusAfterUpdate = 'Blocked';
        }
        if (($validated['progress_percent'] ?? null) === 100 && $statusAfterUpdate === null) {
            $statusAfterUpdate = 'Closed';
        }
        $wasClosedBefore = in_array((string) $workTicket->status, ['Done', 'Closed'], true);
        if ($statusAfterUpdate !== null) {
            $workTicket->status = $statusAfterUpdate;
            if ($statusAfterUpdate === 'In Progress' && !$workTicket->started_at) {
                $workTicket->started_at = now();
            }
            if (in_array($statusAfterUpdate, ['Done', 'Closed'], true)) {
                $workTicket->completed_at = now();
                if (!$workTicket->started_at) {
                    $workTicket->started_at = now();
                }
            }
            if (!in_array($statusAfterUpdate, ['Done', 'Closed'], true)) {
                $workTicket->completed_at = null;
            }
        }
        if (!$workTicket->tat_due_at && !empty($workTicket->tat_hours)) {
            $workTicket->tat_due_at = $workTicket->created_at
                ? $workTicket->created_at->copy()->addHours((int) $workTicket->tat_hours)
                : now()->addHours((int) $workTicket->tat_hours);
        }
        $this->updateTatBreachState($workTicket);
        if (! $wasClosedBefore && in_array((string) $workTicket->status, ['Done', 'Closed'], true)) {
            $this->notifications->notifyClosed($workTicket, (int) $user->id);
        }

        return redirect()
            ->route('work-tickets.show', $workTicket)
            ->with('success', 'Daily update saved.');
    }

    protected function applyVisibilityScope(Builder $query, int $userId, string $scope, bool $canSeeAll, array $reporteeIds): void
    {
        if ($canSeeAll && $scope === 'all') {
            return;
        }

        if ($scope === 'team' && !empty($reporteeIds)) {
            $query->where(function (Builder $q) use ($reporteeIds, $userId): void {
                $q->whereIn('assignee_id', $reporteeIds)
                    ->orWhereIn('created_by', $reporteeIds)
                    ->orWhere('reporting_manager_id', $userId);
            });
            return;
        }

        $query->where(function (Builder $q) use ($userId): void {
            $q->where('assignee_id', $userId)
                ->orWhere('created_by', $userId)
                ->orWhere('reporting_manager_id', $userId);
        });
    }

    protected function canAccessTicket(int $userId, bool $isAdmin, WorkTicket $ticket): bool
    {
        if ($isAdmin) {
            return true;
        }

        if ((int) $ticket->assignee_id === $userId || (int) $ticket->created_by === $userId || (int) $ticket->reporting_manager_id === $userId) {
            return true;
        }

        $reporteeIds = $this->getReporteeIds($userId);
        return in_array((int) $ticket->assignee_id, $reporteeIds, true);
    }

    protected function getReporteeIds(int $managerId): array
    {
        return UserReportingLine::query()
            ->where('manager_id', $managerId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  int[]  $ids
     * @return array<int, string>
     */
    protected function getUsersById(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return VtigerUser::on('vtiger')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name])
            ->toArray();
    }

    protected function generateTicketNo(): string
    {
        $prefix = 'WT-' . now()->format('Ym');
        $latest = WorkTicket::query()
            ->where('ticket_no', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('ticket_no');

        $next = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    protected function getPriorityTatMap(): array
    {
        return [
            'Urgent' => 8,
            'High' => 24,
            'Medium' => 48,
            'Low' => 72,
        ];
    }

    protected function getTatHoursForPriority(string $priority): int
    {
        $map = $this->getPriorityTatMap();
        return $map[$priority] ?? 48;
    }

    protected function updateTatBreachState(WorkTicket $ticket): void
    {
        $dueAt = $ticket->tat_due_at;
        if (!$dueAt && !empty($ticket->tat_hours)) {
            $dueAt = ($ticket->created_at ?? now())->copy()->addHours((int) $ticket->tat_hours);
            $ticket->tat_due_at = $dueAt;
        }

        if (!$dueAt) {
            $ticket->save();
            return;
        }

        $breachedAt = null;
        if (in_array((string) $ticket->status, ['Done', 'Closed'], true)) {
            if ($ticket->completed_at && $ticket->completed_at->gt($dueAt)) {
                $breachedAt = $ticket->completed_at;
            }
        } elseif (now()->gt($dueAt)) {
            $breachedAt = now();
        }

        $ticket->tat_breached_at = $breachedAt;
        $ticket->save();
    }

    protected function normalizeStatus(string $status): string
    {
        $status = trim($status);
        return $status === 'Done' ? 'Closed' : $status;
    }
}
