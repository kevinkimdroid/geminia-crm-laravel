<?php

namespace App\Http\Controllers;

use App\Models\UserReportingLine;
use App\Models\VtigerProfile;
use App\Models\VtigerRole;
use App\Models\VtigerTab;
use App\Models\VtigerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SetupController extends Controller
{
    /**
     * Build users query from setup filters.
     */
    protected function buildUsersQueryFromRequest(Request $request)
    {
        $statusFilter = (string) $request->get('status', 'active');
        $search = trim((string) $request->get('search', ''));

        $usersQuery = VtigerUser::on('vtiger');
        if ($statusFilter === 'inactive') {
            $usersQuery->where('status', 'Inactive');
        } elseif ($statusFilter === 'all') {
            // show all
        } else {
            $usersQuery->where('status', 'Active');
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $usersQuery->where(function ($q) use ($term): void {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email1', 'like', $term)
                    ->orWhere('user_name', 'like', $term);
            });
        }

        return [$usersQuery, $statusFilter, $search];
    }

    /**
     * Show the setup index (redirects to users).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('setup.users');
    }

    /**
     * List users and allow assigning roles.
     */
    public function users(Request $request): View
    {
        [$usersQuery, $statusFilter, $search] = $this->buildUsersQueryFromRequest($request);

        $users = $usersQuery
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $userRoles = DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->pluck('roleid', 'userid')
            ->toArray();

        $roles = VtigerRole::on('vtiger')->orderBy('rolename')->get();
        $reportingLines = UserReportingLine::query()->pluck('manager_id', 'user_id')->toArray();
        $managers = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'user_name']);

        return view('setup.users', [
            'users' => $users,
            'userRoles' => $userRoles,
            'roles' => $roles,
            'reportingLines' => $reportingLines,
            'managers' => $managers,
            'usersStatusFilter' => $statusFilter,
            'usersSearch' => $search,
        ]);
    }

    public function exportUsers(Request $request): StreamedResponse
    {
        [$usersQuery, $statusFilter, $search] = $this->buildUsersQueryFromRequest($request);

        $users = $usersQuery
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $userRoles = DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->pluck('roleid', 'userid')
            ->toArray();

        $rolesById = VtigerRole::on('vtiger')
            ->orderBy('rolename')
            ->get()
            ->keyBy('roleid');

        $reportingLines = UserReportingLine::query()->pluck('manager_id', 'user_id')->toArray();
        $allUserNames = VtigerUser::on('vtiger')
            ->whereIn('id', array_unique(array_merge($users->pluck('id')->all(), array_values($reportingLines))))
            ->get()
            ->mapWithKeys(fn (VtigerUser $u) => [(int) $u->id => $u->full_name])
            ->toArray();

        $fileSuffix = $statusFilter . ($search !== '' ? '-search' : '');
        $filename = 'setup-users-' . $fileSuffix . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($users, $userRoles, $rolesById, $reportingLines, $allUserNames): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Full Name',
                'Email',
                'Username',
                'Status',
                'Role',
                'Reports To',
            ]);

            foreach ($users as $user) {
                $roleId = $userRoles[$user->id] ?? null;
                $roleName = $roleId && isset($rolesById[$roleId]) ? ($rolesById[$roleId]->rolename ?? '') : '';
                $managerId = (int) ($reportingLines[$user->id] ?? 0);
                $managerName = $managerId > 0 ? ($allUserNames[$managerId] ?? ('User #' . $managerId)) : '';

                fputcsv($handle, [
                    $user->full_name,
                    $user->email1 ?? '',
                    $user->user_name ?? '',
                    $user->status ?? '',
                    $roleName,
                    $managerName,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Update a user's role.
     */
    public function updateUserRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'role_id' => 'required|string',
        ]);

        $user = VtigerUser::on('vtiger')->find($validated['user_id']);
        if (!$user) {
            return back()->withErrors(['user' => 'User not found.']);
        }

        $role = VtigerRole::on('vtiger')->find($validated['role_id']);
        if (!$role) {
            return back()->withErrors(['role' => 'Role not found.']);
        }

        DB::connection('vtiger')->table('vtiger_user2role')->updateOrInsert(
            ['userid' => $validated['user_id']],
            ['roleid' => $validated['role_id']]
        );

        return back()->with('success', "Role updated for {$user->full_name}.");
    }

    public function updateUserReportingManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'manager_id' => 'nullable|integer|min:1',
        ]);

        $user = VtigerUser::on('vtiger')
            ->where('id', (int) $validated['user_id'])
            ->where('status', 'Active')
            ->first();
        if (! $user) {
            return back()->withErrors(['user' => 'User not found.']);
        }

        $managerId = !empty($validated['manager_id']) ? (int) $validated['manager_id'] : null;
        if ($managerId !== null) {
            if ($managerId === (int) $user->id) {
                return back()->withErrors(['manager' => 'A user cannot report to themselves.']);
            }
            $managerExists = VtigerUser::on('vtiger')
                ->where('id', $managerId)
                ->where('status', 'Active')
                ->exists();
            if (! $managerExists) {
                return back()->withErrors(['manager' => 'Selected manager is not active.']);
            }
        }

        if ($managerId === null) {
            UserReportingLine::query()->where('user_id', (int) $user->id)->delete();
            return back()->with('success', "Reporting manager removed for {$user->full_name}.");
        }

        UserReportingLine::query()->updateOrCreate(
            ['user_id' => (int) $user->id],
            ['manager_id' => $managerId]
        );

        return back()->with('success', "Reporting manager updated for {$user->full_name}.");
    }

    public function updateUserReportingManagersBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'manager' => 'nullable|array',
            'manager.*' => 'nullable|integer|min:1',
        ]);

        $activeUsers = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->get(['id', 'first_name', 'last_name', 'user_name'])
            ->keyBy('id');
        $activeUserIds = $activeUsers->keys()->map(fn ($id) => (int) $id)->all();

        $incoming = collect($validated['manager'] ?? [])
            ->mapWithKeys(fn ($managerId, $userId) => [(int) $userId => $managerId ? (int) $managerId : null]);

        $invalid = [];
        foreach ($incoming as $userId => $managerId) {
            if (!in_array($userId, $activeUserIds, true)) {
                continue;
            }
            if ($managerId === null) {
                continue;
            }
            if ($managerId === $userId) {
                $name = $activeUsers[$userId]->full_name ?? ('User #' . $userId);
                $invalid[] = $name . ' cannot report to self';
                continue;
            }
            if (!in_array($managerId, $activeUserIds, true)) {
                $name = $activeUsers[$userId]->full_name ?? ('User #' . $userId);
                $invalid[] = $name . ' has an invalid manager';
            }
        }

        if (!empty($invalid)) {
            return back()->withErrors(['manager' => 'Some rows are invalid: ' . implode('; ', array_slice($invalid, 0, 3)) . (count($invalid) > 3 ? '...' : '')]);
        }

        DB::transaction(function () use ($activeUserIds, $incoming): void {
            foreach ($activeUserIds as $userId) {
                $managerId = $incoming->has($userId) ? $incoming[$userId] : null;
                if (empty($managerId)) {
                    UserReportingLine::query()->where('user_id', $userId)->delete();
                    continue;
                }
                UserReportingLine::query()->updateOrCreate(
                    ['user_id' => $userId],
                    ['manager_id' => (int) $managerId]
                );
            }
        });

        return back()->with('success', 'Reporting managers updated for all personnel.');
    }

    /**
     * List roles and their profiles.
     */
    public function roles(): View
    {
        $roles = VtigerRole::on('vtiger')
            ->orderBy('rolename')
            ->with('profiles')
            ->get();

        return view('setup.roles', ['roles' => $roles]);
    }

    /**
     * Edit a role's module permissions.
     */
    /** @return View|RedirectResponse */
    public function editRoleModules(string $roleId)
    {
        $role = VtigerRole::on('vtiger')->find($roleId);
        if (!$role) {
            return redirect()->route('setup.roles')->withErrors(['role' => 'Role not found.']);
        }

        $role->load('profiles.tabs');
        $profile = $role->profiles->first();

        $vtigerNames = array_filter(array_unique(array_values(config('modules.app_to_vtiger', []))));
        $allTabs = VtigerTab::on('vtiger')
            ->whereIn('name', $vtigerNames)
            ->orderBy('name')
            ->get();

        $appModules = config('modules.app_to_vtiger', []);
        $moduleList = [];
        foreach ($appModules as $appKey => $vtigerName) {
            if ($vtigerName) {
                $tab = $allTabs->firstWhere('name', $vtigerName);
                if ($tab) {
                    $moduleList[] = [
                        'key' => $appKey,
                        'label' => ucfirst(str_replace(['.', '-'], [' ', ' '], $appKey)),
                        'tabid' => $tab->tabid,
                        'tab_name' => $vtigerName,
                    ];
                }
            }
        }

        $allowedTabIds = $profile ? $profile->tabs->pluck('tabid')->toArray() : [];

        return view('setup.role-modules', [
            'role' => $role,
            'profile' => $profile,
            'moduleList' => $moduleList,
            'allowedTabIds' => $allowedTabIds,
        ]);
    }

    /**
     * Update a role's module permissions.
     */
    public function updateRoleModules(Request $request, string $roleId): RedirectResponse
    {
        $role = VtigerRole::on('vtiger')->find($roleId);
        if (!$role) {
            return redirect()->route('setup.roles')->withErrors(['role' => 'Role not found.']);
        }

        $profile = $role->profiles()->first();
        if (!$profile) {
            return back()->withErrors(['profile' => 'This role has no profile assigned. Assign a profile in Vtiger first.']);
        }

        $tabIds = $request->input('modules', []);
        if (!is_array($tabIds)) {
            $tabIds = [];
        }
        $tabIds = array_map('intval', array_filter($tabIds));

        DB::connection('vtiger')->transaction(function () use ($profile, $tabIds) {
            DB::connection('vtiger')->table('vtiger_profile2tab')
                ->where('profileid', $profile->profileid)
                ->delete();

            foreach ($tabIds as $tabId) {
                DB::connection('vtiger')->table('vtiger_profile2tab')->insert([
                    'profileid' => $profile->profileid,
                    'tabid' => $tabId,
                    'permissions' => 0,
                ]);
            }
        });

        return back()->with('success', 'Module permissions updated.');
    }
}
