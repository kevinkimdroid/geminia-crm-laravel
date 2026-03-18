<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves user department for reports. Checks:
 * 1. vtiger_users.department (if column exists)
 * 2. user_departments table (Laravel)
 * 3. config('users.departments')[user_id]
 */
class UserDepartmentService
{
    protected ?bool $vtigerHasDepartment = null;

    public function getDepartment(?int $userId): ?string
    {
        if (!$userId || $userId <= 0) {
            return null;
        }

        // Prefer user_departments (Laravel) over vtiger - our UI manages this table
        $dept = $this->fromUserDepartmentsTable($userId);
        if ($dept !== null) {
            return $dept;
        }

        $dept = $this->fromVtiger($userId);
        if ($dept !== null) {
            return $dept;
        }

        return config('users.departments.' . $userId);
    }

    /**
     * Get departments for multiple user IDs in one call (for reports).
     */
    public function getDepartmentsForUsers(array $userIds): array
    {
        $userIds = array_filter(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [];
        }

        $result = [];

        $fromTable = $this->batchFromUserDepartmentsTable($userIds);
        $fromVtiger = $this->batchFromVtiger($userIds);
        $fromConfig = config('users.departments', []);

        foreach ($userIds as $id) {
            $result[$id] = $fromTable[$id]
                ?? $fromVtiger[$id]
                ?? ($fromConfig[$id] ?? null);
        }

        return $result;
    }

    protected function fromVtiger(int $userId): ?string
    {
        if ($this->vtigerHasDepartment === false) {
            return null;
        }

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $userId)
                ->select('department')
                ->first();

            if ($row && !empty(trim((string) ($row->department ?? '')))) {
                return trim($row->department);
            }
        } catch (\Throwable $e) {
            $this->vtigerHasDepartment = false;
            return null;
        }
        $this->vtigerHasDepartment = true;
        return null;
    }

    protected function batchFromVtiger(array $userIds): array
    {
        if ($this->vtigerHasDepartment === false || empty($userIds)) {
            return [];
        }

        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_users')
                ->whereIn('id', $userIds)
                ->select('id', 'department')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                if (!empty(trim((string) ($r->department ?? '')))) {
                    $out[$r->id] = trim($r->department);
                }
            }
            $this->vtigerHasDepartment = true;
            return $out;
        } catch (\Throwable $e) {
            $this->vtigerHasDepartment = false;
            return [];
        }
    }

    protected function fromUserDepartmentsTable(int $userId): ?string
    {
        $row = DB::table('user_departments')->where('user_id', $userId)->first();
        return $row ? trim($row->department) : null;
    }

    protected function batchFromUserDepartmentsTable(array $userIds): array
    {
        $rows = DB::table('user_departments')
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $out = [];
        foreach ($rows as $uid => $r) {
            $out[$uid] = trim($r->department);
        }
        return $out;
    }

    public function setDepartment(int $userId, string $department): void
    {
        DB::table('user_departments')->updateOrInsert(
            ['user_id' => $userId],
            ['department' => trim($department), 'updated_at' => now()]
        );
    }

    public function removeDepartment(int $userId): void
    {
        DB::table('user_departments')->where('user_id', $userId)->delete();
    }

    /**
     * Get the list of department names for dropdowns. Uses DB first, fallback to config.
     */
    public function getDepartmentsList(): array
    {
        try {
            $names = \DB::table('departments')->orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
            if (!empty($names)) {
                return $names;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet
        }
        return config('users.departments_list', []);
    }
}
