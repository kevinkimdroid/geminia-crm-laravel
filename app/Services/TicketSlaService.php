<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketSlaService
{
    /**
     * Roles that are allowed to close tickets. Stored in ticket_sla_settings.
     */
    public function getRolesCanClose(): array
    {
        $row = DB::table('ticket_sla_settings')->where('key', 'roles_can_close')->first();
        if (!$row || empty($row->value)) {
            return ['Administrator'];
        }
        $decoded = json_decode($row->value, true);
        return is_array($decoded) ? $decoded : ['Administrator'];
    }

    public function setRolesCanClose(array $roles): void
    {
        DB::table('ticket_sla_settings')->updateOrInsert(
            ['key' => 'roles_can_close'],
            ['value' => json_encode($roles), 'updated_at' => now()]
        );
    }

    /**
     * Check if the current user's role can close tickets.
     */
    public function canUserCloseTickets(?string $userRoleName): bool
    {
        if (!$userRoleName) {
            return false;
        }
        $allowed = $this->getRolesCanClose();
        return in_array($userRoleName, $allowed, true);
    }

    /**
     * Get TAT (hours) for a department/category.
     */
    public function getTatForDepartment(?string $department): int
    {
        if (!$department) {
            return 24;
        }
        $row = DB::table('ticket_department_tat')->where('department', $department)->first();
        return $row ? (int) $row->tat_hours : 24;
    }

    /**
     * Get all department TAT configs.
     */
    public function getAllDepartmentTat(): \Illuminate\Support\Collection
    {
        return DB::table('ticket_department_tat')->orderBy('department')->get();
    }

    /**
     * Save or update department TAT.
     */
    public function setDepartmentTat(string $department, int $tatHours): void
    {
        $exists = DB::table('ticket_department_tat')->where('department', $department)->exists();
        if ($exists) {
            DB::table('ticket_department_tat')->where('department', $department)->update(['tat_hours' => $tatHours, 'updated_at' => now()]);
        } else {
            $now = now();
            DB::table('ticket_department_tat')->insert([
                'department' => $department,
                'tat_hours' => $tatHours,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Delete department TAT.
     */
    public function deleteDepartmentTat(string $department): void
    {
        DB::table('ticket_department_tat')->where('department', $department)->delete();
    }

    /**
     * Sync departments from ticket categories. Adds any category that doesn't have TAT configured.
     * Ticket category = department for SLA purposes.
     *
     * @return array{added: array<string>, existing: array<string>}
     */
    public function syncDepartmentsFromCategories(): array
    {
        $categories = config('tickets.categories', []);
        $existing = $this->getAllDepartmentTat()->pluck('department')->map(fn ($d) => (string) $d)->toArray();
        $added = [];

        foreach ($categories as $cat) {
            $cat = trim((string) $cat);
            if ($cat === '') {
                continue;
            }
            if (! in_array($cat, $existing, true)) {
                $this->setDepartmentTat($cat, 24);
                $added[] = $cat;
                $existing[] = $cat;
            }
        }

        return ['added' => $added, 'existing' => $existing];
    }

    /**
     * Get ticket categories that don't have department TAT configured.
     */
    public function getCategoriesWithoutTat(): array
    {
        $categories = config('tickets.categories', []);
        $configured = $this->getAllDepartmentTat()->pluck('department')->map(fn ($d) => strtolower((string) $d))->toArray();

        return array_values(array_filter($categories, function ($cat) use ($configured) {
            return ! in_array(strtolower(trim((string) $cat)), $configured, true);
        }));
    }

    /**
     * Get tickets that have broken SLA (exceeded TAT).
     * Returns open tickets past TAT, and closed tickets that were closed after TAT.
     */
    public function getBrokenSlaTickets(int $limit = 100): \Illuminate\Support\Collection
    {
        $tickets = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->leftJoin('vtiger_contactdetails as c', 't.contact_id', '=', 'c.contactid')
            ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->whereNotNull('t.contact_id')
            ->where('t.contact_id', '>', 0)
            ->select(
                't.ticketid',
                't.title',
                't.ticket_no',
                't.status',
                't.category',
                't.priority',
                'e.createdtime',
                'e.modifiedtime',
                'e.smownerid',
                'c.firstname as contact_first',
                'c.lastname as contact_last',
                'u.first_name as owner_first',
                'u.last_name as owner_last'
            )
            ->orderByDesc('e.createdtime')
            ->limit($limit * 2)
            ->get();

        $broken = collect();
        foreach ($tickets as $t) {
            $department = $t->category ?? 'General';
            $tatHours = $this->getTatForDepartment($department);
            $created = \Carbon\Carbon::parse($t->createdtime);
            $dueAt = $created->copy()->addHours($tatHours);

            $resolvedAt = $t->status === 'Closed' ? \Carbon\Carbon::parse($t->modifiedtime) : null;
            $isBreached = $resolvedAt
                ? $resolvedAt->gt($dueAt)
                : now()->gt($dueAt);

            if ($isBreached) {
                $broken->push((object) array_merge((array) $t, [
                    'tat_hours' => $tatHours,
                    'due_at' => $dueAt,
                    'breached_at' => $resolvedAt ?? now(),
                    'hours_overdue' => $resolvedAt
                        ? $resolvedAt->diffInHours($dueAt)
                        : now()->diffInHours($dueAt),
                ]));
                if ($broken->count() >= $limit) {
                    break;
                }
            }
        }

        return $broken->take($limit);
    }
}
