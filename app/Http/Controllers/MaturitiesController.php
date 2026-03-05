<?php

namespace App\Http\Controllers;

use App\Exports\MaturitiesExport;
use App\Services\ErpClientService;
use App\Services\TicketAutoCreateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MaturitiesController extends Controller
{
    public function __construct(
        protected TicketAutoCreateService $maturityService,
        protected ErpClientService $erpClientService
    ) {}

    /**
     * List policies maturing within configured days. Paginated with search, sort, and product filter.
     */
    public function index(Request $request): View
    {
        $days = max(7, min(90, (int) ($request->get('days') ?: config('tickets.auto_maturity.days_before', 30))));
        $search = $request->get('search');
        $product = $request->get('product');
        $sort = $request->get('sort', 'maturity');
        $dir = $request->get('dir', 'asc');
        $perPage = max(25, min(100, (int) ($request->get('per_page') ?: 50)));

        $policies = $this->maturityService->getMaturingPoliciesPaginated($days, $search, $sort, $dir, $perPage, $product);

        $products = $this->getProductsForFilter();

        return view('support.maturities', [
            'policies' => $policies,
            'days' => $days,
            'search' => $search,
            'product' => $product,
            'products' => $products,
            'sort' => $sort,
            'dir' => $dir,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Export maturing policies to Excel. Uses same filters as index (policy_status='A').
     */
    public function export(Request $request)
    {
        $days = max(7, min(90, (int) ($request->get('days') ?: config('tickets.auto_maturity.days_before', 30))));
        $search = $request->get('search');
        $product = $request->get('product');
        $sort = $request->get('sort', 'maturity');
        $dir = $request->get('dir', 'asc');

        $rows = $this->maturityService->getMaturingPoliciesForExport($days, $search, $sort, $dir, $product);
        $filename = 'maturities-' . now()->format('Y-m-d-His');

        return Excel::download(new MaturitiesExport($rows), $filename . '.xlsx');
    }

    /**
     * Get product names for the filter dropdown (from cache or ERP API).
     */
    protected function getProductsForFilter(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('maturities_cache')) {
            $products = DB::table('maturities_cache')
                ->whereNotNull('product')
                ->where('product', '!=', '')
                ->distinct()
                ->orderBy('product')
                ->pluck('product')
                ->filter()
                ->values()
                ->toArray();
            if (! empty($products)) {
                return $products;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('erp_clients_cache')) {
            $products = DB::table('erp_clients_cache')
                ->whereNotNull('product')
                ->where('product', '!=', '')
                ->distinct()
                ->orderBy('product')
                ->pluck('product')
                ->filter()
                ->values()
                ->toArray();
            if (! empty($products)) {
                return $products;
            }
        }
        if (config('erp.clients_view_source') === 'erp_http') {
            return $this->erpClientService->getProductsForMaturitiesFilter();
        }
        return [];
    }
}
