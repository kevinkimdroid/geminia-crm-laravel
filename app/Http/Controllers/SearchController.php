<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** @var CrmService */
    protected $crm;

    public function __construct(CrmService $crm)
    {
        $this->crm = $crm;
    }

    /**
     * Global search across contacts, leads, tickets, and deals.
     * Returns suggestions for autocomplete.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $limit = (int) $request->get('limit', 10);
        $results = $this->crm->globalSearch($q, $limit, crm_owner_filter());

        return response()->json(['results' => $results]);
    }
}
