<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateErpApiToken
{
    /**
     * Require a valid API token for ERP endpoints.
     * Accepts: X-API-Key header or Authorization: Bearer <token>
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.erp.api_token');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'error' => 'ERP API is not configured.',
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
