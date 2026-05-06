<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        $ownerId = crm_owner_filter();
        $cacheKey = 'global_search:' . sha1($q . '|' . $limit . '|' . ($ownerId ?? 'all'));
        $results = Cache::remember($cacheKey, 30, fn () => $this->crm->globalSearch($q, $limit, $ownerId));

        return response()->json(['results' => $results]);
    }
}
