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
        $ownerId = config('dashboard.show_all_stats', true) ? null : crm_owner_filter();
        $stats = $this->crm->getDashboardStats(120, $ownerId);

        // Keep Contacts card aligned with Contacts page (owner-filtered view).
        $contactsOwnerId = crm_owner_filter();
        $stats['contactsCount'] = (int) $this->crm->getContactsCount($contactsOwnerId);
        $stats['contactsCountDeferred'] = false;

        // Resolve Clients count on server to avoid "..." stuck state on dashboard.
        $source = config('erp.clients_view_source', 'crm');
        if (in_array($source, ['erp_http', 'erp_sync'], true)) {
            $cachedClientsCount = Cache::get('geminia_clients_count');
            if ($cachedClientsCount === null) {
                try {
                    $cachedClientsCount = (int) ($this->erp->getClientsCount(15) ?? 0);
                } catch (\Throwable) {
                    $cachedClientsCount = 0;
                }
                Cache::put('geminia_clients_count', (int) $cachedClientsCount, 120);
            }
            $stats['clientsCount'] = (int) $cachedClientsCount;
        } else {
            // CRM-only mode: mirror contacts count.
            $stats['clientsCount'] = (int) ($stats['contactsCount'] ?? 0);
        }
        $stats['clientsCountDeferred'] = false;

        $stats['pbxCanCall'] = $this->pbxConfig->isConfigured();
        $stats['salesByPerson'] = Cache::remember('geminia_dashboard_sales_by_person_top8', 120, fn () => $this->crm->getSalesByPerson(8));

        return view('dashboard', $stats);
    }

    /**
     * Lightweight endpoint for lazy-loaded clients count (avoids blocking dashboard on slow ERP).
     * Uses ErpClientService::getClientsCount() — same as Support > Clients “All” stat (group + individual
     * + mortgage + group pension when those views are configured in Laravel .env).
     */
    public function clientsCount(): \Illuminate\Http\JsonResponse
    {
        $source = config('erp.clients_view_source', 'crm');
        if (! in_array($source, ['erp_http', 'erp_sync'])) {
            // For CRM-only mode, Clients mirrors local contacts count.
            return response()->json(['count' => (int) ($this->crm->getContactsCount(crm_owner_filter()) ?? 0)]);
        }
        $count = Cache::remember('geminia_clients_count', 120, function () {
            try {
                return (int) ($this->erp->getClientsCount(25) ?? 0);
            } catch (\Throwable $e) {
                return 0;
            }
        });
        return response()->json(['count' => (int) $count]);
    }
}
