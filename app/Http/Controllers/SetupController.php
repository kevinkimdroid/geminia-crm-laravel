<?php

namespace App\Http\Controllers;

use App\Models\VtigerProfile;
use App\Models\VtigerRole;
use App\Models\VtigerTab;
use App\Models\VtigerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupController extends Controller
{
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
        $users = VtigerUser::on('vtiger')
            ->where('status', 'Active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $userRoles = DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->pluck('roleid', 'userid')
            ->toArray();

        $roles = VtigerRole::on('vtiger')->orderBy('rolename')->get();

        return view('setup.users', [
            'users' => $users,
            'userRoles' => $userRoles,
            'roles' => $roles,
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
