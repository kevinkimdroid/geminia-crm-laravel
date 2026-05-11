<?php

namespace App\Http\Middleware;

use App\Services\UserDepartmentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceDepartment
{
    public function __construct(
        protected UserDepartmentService $departments
    ) {}

    /**
     * Allow only Finance users (and Administrators) into finance pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('vtiger')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
            return $next($request);
        }

        $department = strtolower(trim((string) $this->departments->getDepartment((int) $user->id)));
        $roleName = strtolower(trim((string) ($user->primary_role->rolename ?? '')));
        $email = strtolower(trim((string) ($user->email1 ?? '')));
        $isFinance = str_contains($department, 'finance')
            || str_contains($roleName, 'finance')
            || str_contains($email, 'finance');

        if (!$isFinance) {
            return redirect()->route('dashboard')
                ->with('error', 'You cannot open Finance links: your profile is not in the Finance department (and you are not an Administrator). Ask an admin to assign Finance access or add your user to the Finance department.');
        }

        return $next($request);
    }
}
