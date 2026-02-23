<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ErpClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpClientController extends Controller
{
    public function __construct(
        protected ErpClientService $erp
    ) {}

    /**
     * Fetch all clients from the ERP system (Oracle/PL-SQL).
     *
     * GET /api/erp/clients
     * Query params: limit (int), offset (int), page (int)
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 100);
        $limit = min(max($limit, 1), 1000);
        $page = $request->integer('page', 1);
        $offset = ($page - 1) * $limit;

        $result = $this->erp->getClients($limit, $offset);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'data' => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'total' => $result['total'],
                'limit' => $limit,
                'offset' => $offset,
                'page' => $page,
            ],
        ]);
    }
}
