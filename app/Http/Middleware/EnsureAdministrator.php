<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    /**
     * Allow only Administrator role. Others are redirected to dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('vtiger')->user();
        if (!$user || !$user->isAdministrator()) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access that page.');
        }

        return $next($request);
    }
}
