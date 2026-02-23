<?php

namespace App\Http\Controllers;

use App\Models\VtigerRole;
use App\Services\TicketSlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketSlaController extends Controller
{
    /** @var TicketSlaService */
    protected $sla;

    public function __construct(TicketSlaService $sla)
    {
        $this->sla = $sla;
    }

    public function index(): View
    {
        $roles = VtigerRole::on('vtiger')->orderBy('rolename')->get();
        $rolesCanClose = $this->sla->getRolesCanClose();
        $departmentTat = $this->sla->getAllDepartmentTat();

        return view('settings.sections.ticket-sla', [
            'roles' => $roles,
            'rolesCanClose' => $rolesCanClose,
            'departmentTat' => $departmentTat,
        ]);
    }

    public function updateRoles(Request $request): RedirectResponse
    {
        $roles = $request->input('roles_can_close', []);
        $roles = is_array($roles) ? array_filter($roles) : [];
        $this->sla->setRolesCanClose($roles);
        return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('success', 'Close ticket permissions updated.');
    }

    public function updateDepartmentTat(Request $request): RedirectResponse
    {
        $department = $request->input('department');
        $tatHours = (int) $request->input('tat_hours', 24);
        if ($department && $tatHours > 0) {
            $this->sla->setDepartmentTat($department, $tatHours);
        }
        return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('success', 'Department TAT updated.');
    }

    public function addDepartmentTat(Request $request): RedirectResponse
    {
        $request->validate([
            'department' => 'required|string|max:100',
            'tat_hours' => 'required|integer|min:1|max:720',
        ]);
        $this->sla->setDepartmentTat($request->department, $request->tat_hours);
        return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('success', 'Department added.');
    }

    public function deleteDepartmentTat(string $department): RedirectResponse
    {
        $this->sla->deleteDepartmentTat($department);
        return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('success', 'Department TAT removed.');
    }

    /**
     * Sync departments from ticket categories. Ensures every category has a TAT (SLA between departments).
     */
    public function syncFromCategories(): RedirectResponse
    {
        $result = $this->sla->syncDepartmentsFromCategories();
        $added = $result['added'] ?? [];
        if (count($added) > 0) {
            $msg = 'Added ' . count($added) . ' department(s) from ticket categories: ' . implode(', ', $added) . '. Each has 24h default TAT.';
            return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('success', $msg);
        }
        return redirect()->route('settings.crm', ['section' => 'ticket-sla'])->with('info', 'All ticket categories already have department TAT configured.');
    }
}
