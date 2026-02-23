<?php

namespace App\Http\Controllers;

use App\Models\VtigerProfile;
use App\Models\VtigerTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * List all profiles.
     */
    public function index(): View
    {
        $profiles = VtigerProfile::on('vtiger')
            ->orderBy('profilename')
            ->withCount('roles')
            ->get();

        return view('settings.profiles-index', ['profiles' => $profiles]);
    }

    /**
     * Show profile detail view (matching Vtiger Profile view layout).
     */
    /** @return View|RedirectResponse */
    public function show(string $id)
    {
        $profile = VtigerProfile::on('vtiger')->find($id);
        if (! $profile) {
            return redirect()->route('settings.crm', ['section' => 'profiles'])
                ->withErrors(['profile' => 'Profile not found.']);
        }

        $profile->load(['tabs', 'roles']);

        $vtigerNames = array_filter(array_unique(array_values(config('modules.app_to_vtiger', []))));
        $allTabs = VtigerTab::on('vtiger')
            ->whereIn('name', $vtigerNames)
            ->orderBy('name')
            ->get();

        $allowedTabIds = $profile->tabs->pluck('tabid')->toArray();

        $standardPerms = DB::connection('vtiger')
            ->table('vtiger_profile2standardpermissions')
            ->where('profileid', $profile->profileid)
            ->get()
            ->groupBy('tabid');

        $fieldPerms = DB::connection('vtiger')
            ->table('vtiger_profile2field')
            ->where('profileid', $profile->profileid)
            ->get()
            ->groupBy('tabid');

        $moduleList = [];
        foreach ($allTabs as $tab) {
            $perms = $standardPerms->get($tab->tabid, collect());
            $hasView = in_array($tab->tabid, $allowedTabIds);
            $moduleList[] = [
                'tabid' => $tab->tabid,
                'name' => $tab->name,
                'label' => $this->tabLabel($tab->name),
                'view' => $hasView,
                'create' => (bool) (($p = $perms->firstWhere('operation', 0)) ? ($p->permissions ?? 0) : 0),
                'edit' => (bool) (($p = $perms->firstWhere('operation', 1)) ? ($p->permissions ?? 0) : 0),
                'delete' => (bool) (($p = $perms->firstWhere('operation', 2)) ? ($p->permissions ?? 0) : 0),
                'fields' => $hasView ? $this->getFieldsForTab($tab->tabid, $fieldPerms->get($tab->tabid, collect())) : [],
            ];
        }

        $tools = $this->getProfileTools($profile->profileid);

        return view('settings.profile-detail', [
            'profile' => $profile,
            'moduleList' => $moduleList,
            'tools' => $tools,
        ]);
    }

    /**
     * Update profile permissions.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $profile = VtigerProfile::on('vtiger')->find($id);
        if (! $profile) {
            return back()->withErrors(['profile' => 'Profile not found.']);
        }

        $request->validate([
            'profilename' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($request->filled('profilename')) {
            $profile->update(['profilename' => $request->profilename]);
        }
        if ($request->has('description')) {
            $profile->update(['description' => $request->description]);
        }

        $modules = $request->input('modules', []);
        if (is_array($modules)) {
            $this->updateModulePermissions($profile->profileid, $modules);
        }

        $tools = $request->input('tools', []);
        if (is_array($tools)) {
            $this->updateProfileTools($profile->profileid, $tools);
        }

        $fields = $request->input('fields', []);
        if (is_array($fields)) {
            $this->updateFieldPermissions($profile->profileid, $fields);
        }

        return back()->with('success', 'Profile permissions updated.');
    }

    protected function tabLabel(string $name): string
    {
        $labels = [
            'Home' => 'Dashboards',
            'Potentials' => 'Opportunities',
            'HelpDesk' => 'Tickets',
            'Contacts' => 'Contacts',
            'Leads' => 'Leads',
            'Campaigns' => 'Campaigns',
            'Reports' => 'Reports',
        ];

        return $labels[$name] ?? $name;
    }

    protected function getFieldsForTab(int $tabid, $fieldPerms): array
    {
        $fields = DB::connection('vtiger')
            ->table('vtiger_field')
            ->where('tabid', $tabid)
            ->whereIn('presence', [0, 2])
            ->orderBy('sequence')
            ->limit(30)
            ->get(['fieldid', 'fieldlabel', 'fieldname']);

        $permMap = $fieldPerms->keyBy('fieldid');

        return $fields->map(function ($f) use ($permMap) {
            $p = $permMap->get($f->fieldid);
            $visible = $p ? (int) $p->visible : 1;
            $readonly = $p ? (int) $p->readonly : 0;
            $access = $visible === 0 ? 'invisible' : ($readonly === 1 ? 'readonly' : 'write');

            return [
                'fieldid' => $f->fieldid,
                'label' => $f->fieldlabel ?: $f->fieldname,
                'access' => $access,
            ];
        })->values()->all();
    }

    protected function getProfileTools(int $profileid): array
    {
        try {
            $utils = DB::connection('vtiger')
                ->table('vtiger_profile2utility')
                ->where('profileid', $profileid)
                ->where('permission', 1)
                ->pluck('activityid')
                ->toArray();

            return [
                'Import' => in_array(5, $utils),
                'Export' => in_array(6, $utils),
                'DuplicatesHandling' => in_array(10, $utils),
            ];
        } catch (\Throwable $e) {
            return ['Import' => false, 'Export' => false, 'DuplicatesHandling' => false];
        }
    }

    protected function updateModulePermissions(int $profileid, array $modules): void
    {
        $managedTabIds = array_map('intval', array_keys($modules));
        $managedTabIds = array_filter($managedTabIds, fn ($id) => $id > 0);

        DB::connection('vtiger')->transaction(function () use ($profileid, $modules, $managedTabIds) {
            if (! empty($managedTabIds)) {
                DB::connection('vtiger')->table('vtiger_profile2tab')
                    ->where('profileid', $profileid)
                    ->whereIn('tabid', $managedTabIds)
                    ->delete();

                DB::connection('vtiger')->table('vtiger_profile2standardpermissions')
                    ->where('profileid', $profileid)
                    ->whereIn('tabid', $managedTabIds)
                    ->delete();
            }

            $ops = [0 => 'create', 1 => 'edit', 2 => 'delete'];

            foreach ($modules as $tabId => $perms) {
                $tabId = (int) $tabId;
                if ($tabId <= 0) {
                    continue;
                }
                $perms = is_array($perms) ? $perms : [];
                $hasView = ! empty($perms['view']);

                if ($hasView) {
                    DB::connection('vtiger')->table('vtiger_profile2tab')->insert([
                        'profileid' => $profileid,
                        'tabid' => $tabId,
                        'permissions' => 1,
                    ]);

                    foreach ($ops as $opNum => $key) {
                        DB::connection('vtiger')->table('vtiger_profile2standardpermissions')->insert([
                            'profileid' => $profileid,
                            'tabid' => $tabId,
                            'operation' => $opNum,
                            'permissions' => ! empty($perms[$key]) ? 1 : 0,
                        ]);
                    }
                    foreach ([3 => 1, 4 => 1] as $opNum => $perm) {
                        DB::connection('vtiger')->table('vtiger_profile2standardpermissions')->insert([
                            'profileid' => $profileid,
                            'tabid' => $tabId,
                            'operation' => $opNum,
                            'permissions' => $perm,
                        ]);
                    }
                }
            }
        });
    }

    protected function updateProfileTools(int $profileid, array $tools): void
    {
        $activityMap = ['Import' => 5, 'Export' => 6, 'DuplicatesHandling' => 10];
        $tabs = DB::connection('vtiger')->table('vtiger_profile2utility')
            ->where('profileid', $profileid)
            ->select('tabid')
            ->distinct()
            ->pluck('tabid');
        $tabid = $tabs->first() ?? 2;

        DB::connection('vtiger')->table('vtiger_profile2utility')
            ->where('profileid', $profileid)
            ->delete();

        foreach ($activityMap as $tool => $aid) {
            if (! empty($tools[$tool])) {
                DB::connection('vtiger')->table('vtiger_profile2utility')->insert([
                    'profileid' => $profileid,
                    'tabid' => $tabid,
                    'activityid' => $aid,
                    'permission' => 1,
                ]);
            }
        }
    }

    protected function updateFieldPermissions(int $profileid, array $fields): void
    {
        $tabIds = [];
        foreach (array_keys($fields) as $key) {
            $parts = explode('_', $key, 2);
            if (count($parts) === 2 && (int) $parts[0] > 0) {
                $tabIds[] = (int) $parts[0];
            }
        }
        $tabIds = array_unique($tabIds);

        if (! empty($tabIds)) {
            DB::connection('vtiger')->table('vtiger_profile2field')
                ->where('profileid', $profileid)
                ->whereIn('tabid', $tabIds)
                ->delete();
        }

        foreach ($fields as $key => $access) {
            $parts = explode('_', $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$tabid, $fieldid] = $parts;
            $tabid = (int) $tabid;
            $fieldid = (int) $fieldid;
            if ($tabid <= 0 || $fieldid <= 0) {
                continue;
            }
            $visible = $access === 'invisible' ? 0 : 1;
            $readonly = $access === 'readonly' ? 1 : 0;

            DB::connection('vtiger')->table('vtiger_profile2field')->insert([
                'profileid' => $profileid,
                'tabid' => $tabid,
                'fieldid' => $fieldid,
                'visible' => $visible,
                'readonly' => $readonly,
            ]);
        }
    }
}
