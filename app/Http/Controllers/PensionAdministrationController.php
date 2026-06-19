<?php

namespace App\Http\Controllers;

use App\Services\ErpClientService;
use Illuminate\View\View;

class PensionAdministrationController extends Controller
{
    public function index(ErpClientService $erp): View
    {
        $mailbox = strtolower(trim((string) config('pension.mailbox', 'pensions@geminialife.co.ke')));
        $clientSystem = (string) config('pension.client_system', 'group_pension');
        $clientsConfigured = $erp->optionalClientsSegmentConfigured($clientSystem);

        return view('support.pension-administration', [
            'mailbox' => $mailbox,
            'clientSystem' => $clientSystem,
            'clientTabLabel' => config('clients_ui.tab_labels.' . $clientSystem, 'Group Pension'),
            'clientsConfigured' => $clientsConfigured,
            'ticketOrganization' => config('pension.ticket_organization', 'line:Group Pension'),
        ]);
    }
}
