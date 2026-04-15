<?php

namespace App\Http\Controllers;

use App\Models\CrmSetting;
use App\Models\Department;
use App\Models\UserReportingLine;
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
        $section = $request->get('section', 'overview');

        $data = ['section' => $section];

        if ($section === 'users') {
            $statusFilter = $request->get('status', 'active');
            $usersQuery = VtigerUser::on('vtiger');
            if ($statusFilter === 'inactive') {
                $usersQuery->where('status', 'Inactive');
            } elseif ($statusFilter === 'all') {
                // Show both
            } else {
                $usersQuery->where('status', 'Active');
            }
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
            $users = $usersQuery->orderBy('first_name')->orderBy('last_name')->get();
            $deptFilter = trim((string) $request->get('department', ''));
            if ($deptFilter !== '') {
                $depts = app(\App\Services\UserDepartmentService::class)->getDepartmentsForUsers($users->pluck('id')->all());
                $users = $users->filter(fn ($u) => ($depts[$u->id] ?? '') === $deptFilter)->values();
            }
            $data['usersStatusFilter'] = $statusFilter;
            $data['usersDeptFilter'] = $deptFilter;
            $data['users'] = $users;
            $data['userDepartments'] = app(\App\Services\UserDepartmentService::class)->getDepartmentsForUsers($users->pluck('id')->all());
            $data['departmentsList'] = app(\App\Services\UserDepartmentService::class)->getDepartmentsList();
            $data['usersSearch'] = $search;
            $data['usersRoleFilter'] = $roleFilter ?? '';
            $data['userRoles'] = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->pluck('roleid', 'userid')
                ->toArray();
            $data['roles'] = VtigerRole::on('vtiger')->orderBy('rolename')->get();
            $data['reportingLines'] = UserReportingLine::query()->pluck('manager_id', 'user_id')->toArray();
            $data['reportingManagerOptions'] = VtigerUser::on('vtiger')
                ->where('status', 'Active')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'user_name']);
        } elseif ($section === 'departments') {
            $data['departments'] = Department::orderBy('sort_order')->orderBy('name')->get();
            $data['userCounts'] = DB::table('user_departments')->selectRaw('department, count(*) as cnt')->groupBy('department')->pluck('cnt', 'department')->toArray();
            $editId = $request->get('edit');
            if ($editId) {
                $data['editDepartment'] = Department::find($editId);
            }
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
        } elseif ($section === 'ticket-dropdowns') {
            $data['ticketCategoriesCustom'] = CrmSetting::tableExists()
                ? (CrmSetting::get('ticket_categories_custom') ?? '')
                : '';
            $data['ticketSourcesCustom'] = CrmSetting::tableExists()
                ? (CrmSetting::get('ticket_sources_custom') ?? '')
                : '';
            $crm = app(\App\Services\CrmService::class);
            $data['previewCategories'] = $crm->getTicketCategoriesFromCrm();
            $data['previewSources'] = $crm->getTicketSourcesFromCrm();
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
