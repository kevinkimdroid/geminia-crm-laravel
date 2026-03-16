<?php

namespace App\Http\Controllers;

use App\Models\VtigerProfile;
use App\Models\VtigerRole;
use App\Models\VtigerTab;
use App\Models\VtigerUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function crm(Request $request): View
    {
        $section = $request->get('section', 'users');

        $data = ['section' => $section];

        if ($section === 'users') {
            $usersQuery = VtigerUser::on('vtiger')
                ->where('status', 'Active');
            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $term = '%' . $search . '%';
                $usersQuery->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email1', 'like', $term)
                        ->orWhere('user_name', 'like', $term);
                });
            }
            $roleFilter = $request->get('role');
            if ($roleFilter && $roleFilter !== '') {
                $userIdsWithRole = DB::connection('vtiger')
                    ->table('vtiger_user2role')
                    ->where('roleid', $roleFilter)
                    ->pluck('userid')
                    ->toArray();
                $usersQuery->whereIn('id', $userIdsWithRole ?: [0]);
            }
            $data['users'] = $usersQuery->orderBy('first_name')->orderBy('last_name')->get();
            $data['usersSearch'] = $search;
            $data['usersRoleFilter'] = $roleFilter ?? '';
            $data['userRoles'] = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->pluck('roleid', 'userid')
                ->toArray();
            $data['roles'] = VtigerRole::on('vtiger')->orderBy('rolename')->get();
        } elseif ($section === 'roles') {
            $data['roles'] = VtigerRole::on('vtiger')
                ->orderBy('rolename')
                ->with('profiles')
                ->get();
        } elseif ($section === 'groups') {
            $crm = app(\App\Services\CrmService::class);
            $page = max(1, (int) $request->get('page', 1));
            $perPage = 50;
            $data['groups'] = $crm->getGroups($perPage, ($page - 1) * $perPage);
            $data['groupsTotal'] = $crm->getGroupsCount();
            $data['groupsPage'] = $page;
            $data['groupsPerPage'] = $perPage;
            $editId = $request->get('edit');
            if ($editId) {
                $data['editGroup'] = DB::connection('vtiger')->table('vtiger_groups')->where('groupid', $editId)->first();
            }
        } elseif ($section === 'ticket-automation') {
            $automation = app(\App\Services\TicketAutomationService::class);
            $data['automationRules'] = $automation->getRules();
            $data['users'] = VtigerUser::on('vtiger')->where('status', 'Active')->orderBy('first_name')->orderBy('last_name')->get();
            $editId = $request->get('edit');
            if ($editId) {
                $data['editRule'] = $automation->getRule((int) $editId);
            }
        } elseif ($section === 'ticket-sla') {
            $sla = app(\App\Services\TicketSlaService::class);
            $data['roles'] = \App\Models\VtigerRole::on('vtiger')->orderBy('rolename')->get();
            $data['rolesCanClose'] = $sla->getRolesCanClose();
            $data['departmentTat'] = $sla->getAllDepartmentTat();
            $data['categoriesWithoutTat'] = $sla->getCategoriesWithoutTat();
        } elseif ($section === 'modules') {
            $moduleService = app(\App\Services\ModuleService::class);
            $data['modules'] = $moduleService->getAllModules();
        } elseif ($section === 'scheduler') {
            $scheduler = app(\App\Services\SchedulerService::class);
            $data['cronTasks'] = $scheduler->getCronTasks();
        } elseif ($section === 'login-history') {
            $loginHistory = app(\App\Services\LoginHistoryService::class);
            $page = max(1, (int) $request->get('page', 1));
            $perPage = 50;
            $filter = $request->get('filter', 'all');
            $data['loginRecords'] = $loginHistory->getRecords($perPage, ($page - 1) * $perPage, $filter);
            $data['loginTotal'] = $loginHistory->getCount($filter);
            $data['loginPage'] = $page;
            $data['loginPerPage'] = $perPage;
            $data['loginFilter'] = $filter;
        } elseif ($section === 'pbx-extension-mapping') {
            $extService = app(\App\Services\PbxExtensionMappingService::class);
            $data['pbxMappings'] = $extService->getAll();
            $data['vtigerUsers'] = VtigerUser::on('vtiger')->where('status', 'Active')->orderBy('first_name')->orderBy('last_name')->get();
        }

        return view('settings', $data);
    }
}
