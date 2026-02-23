<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateErpSyncToken
{
    /**
     * Require ERP_SYNC_TOKEN for the clients import endpoint.
     * Accepts: X-API-Key header, Authorization: Bearer <token>, or ?api_key=
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = env('ERP_SYNC_TOKEN');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'error' => 'ERP sync is not configured (ERP_SYNC_TOKEN).',
            ], 503);
        }

        $provided = $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        if (empty($provided) || ! hash_equals($token, $provided)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
