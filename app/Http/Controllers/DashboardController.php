<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use App\Services\ErpClientService;
use App\Services\PbxConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @var CrmService */
    protected $crm;
    /** @var ErpClientService */
    protected $erp;
    /** @var PbxConfigService */
    protected $pbxConfig;

    public function __construct(CrmService $crm, ErpClientService $erp, PbxConfigService $pbxConfig)
    {
        $this->crm = $crm;
        $this->erp = $erp;
        $this->pbxConfig = $pbxConfig;
    }

    public function index(): View
    {
        $ownerId = crm_owner_filter();
        $stats = $this->crm->getDashboardStats(120, $ownerId);

        // Clients count: don't block on slow ERP API — load via AJAX for fast first paint
        $source = config('erp.clients_view_source', 'crm');
        $stats['clientsCountDeferred'] = in_array($source, ['erp_http', 'erp_sync']);
        $stats['clientsCount'] = $stats['clientsCountDeferred'] ? null : ($stats['contactsCount'] ?? 0);
        // Contacts page shows same data as Support > Customers — use same source for count
        if ($stats['clientsCountDeferred']) {
            $stats['contactsCount'] = null;
            $stats['contactsCountDeferred'] = true;
        } else {
            $stats['contactsCount'] = $stats['contactsCount'] ?? 0;
            $stats['contactsCountDeferred'] = false;
        }
        $stats['pbxCanCall'] = $this->pbxConfig->isConfigured();
        $stats['salesByPerson'] = $this->crm->getSalesByPerson(8);

        return view('dashboard', $stats);
    }

    /**
     * Lightweight endpoint for lazy-loaded clients count (avoids blocking dashboard on slow ERP).
     * Uses the same logic as Support > Clients "All" view so the count always matches.
     */
    public function clientsCount(): \Illuminate\Http\JsonResponse
    {
        $source = config('erp.clients_view_source', 'crm');
        if (! in_array($source, ['erp_http', 'erp_sync'])) {
            return response()->json(['count' => null]);
        }
        $count = Cache::remember('geminia_clients_count', 120, function () {
            try {
                // Use same source as Clients page "All" filter (group + individual merged total)
                $result = $this->erp->getClientsForListView(1, 0, null, null);
                return $result['error'] ? 0 : (int) ($result['total'] ?? 0);
            } catch (\Throwable $e) {
                return 0;
            }
        });
        return response()->json(['count' => (int) $count]);
    }
}
