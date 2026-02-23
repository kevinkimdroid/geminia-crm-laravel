<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private CrmService $crm
    ) {}

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
        $results = $this->crm->globalSearch($q, $limit);

        return response()->json(['results' => $results]);
    }
}
