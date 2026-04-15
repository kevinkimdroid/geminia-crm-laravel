<?php

namespace App\Http\Controllers;

use App\Models\UserReportingLine;
use App\Models\VtigerRole;
use App\Models\VtigerUser;
use App\Services\CrmService;
use App\Services\UserDepartmentService;
use App\Services\UserManagementService;
use App\Services\UserOffboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function create(): View
    {
        $departmentsList = app(UserDepartmentService::class)->getDepartmentsList();
        $roles = VtigerRole::on('vtiger')->orderBy('rolename')->get();

        return view('settings.sections.user-create', [
            'departmentsList' => $departmentsList,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = DB::connection('vtiger')
                        ->table('vtiger_users')
                        ->where('user_name', $value)
                        ->where('deleted', 0)
                        ->exists();
                    if ($exists) {
                        $fail('This username is already taken.');
                    }
                },
            ],
            'first_name' => 'required|string|max:40',
            'last_name' => 'required|string|max:80',
            'email1' => [
                'required',
                'email',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = DB::connection('vtiger')
                        ->table('vtiger_users')
                        ->where('email1', $value)
                        ->where('deleted', 0)
                        ->exists();
                    if ($exists) {
                        $fail('A user with this email already exists.');
                    }
                },
            ],
            'role_id' => 'required|string',
            'department' => 'nullable|string|max:100',
        ]);

        $roleOk = VtigerRole::on('vtiger')->where('roleid', $validated['role_id'])->exists();
        if (! $roleOk) {
            return back()->withErrors(['role_id' => 'The selected role is invalid.'])->withInput();
        }

        // Placeholder password: unknown to anyone; user sets a real password via the emailed link.
        $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $accessKey = strtoupper(bin2hex(random_bytes(8)));

        $userId = null;

        DB::connection('vtiger')->transaction(function () use ($validated, $placeholderHash, $accessKey, &$userId): void {
            $userId = (int) DB::connection('vtiger')
                ->table('vtiger_users')
                ->insertGetId([
                    'user_name' => $validated['user_name'],
                    'user_password' => $placeholderHash,
                    'confirm_password' => $placeholderHash,
                    'crypt_type' => 'PHASH',
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email1' => $validated['email1'],
                    'status' => 'Active',
                    'is_admin' => 'off',
                    'accesskey' => $accessKey,
                ]);

            DB::connection('vtiger')->table('vtiger_user2role')->updateOrInsert(
                ['userid' => $userId],
                ['roleid' => $validated['role_id']]
            );
        });

        $userDept = app(UserDepartmentService::class);
        if ($userId && ! empty(trim($validated['department'] ?? ''))) {
            $userDept->setDepartment($userId, trim($validated['department']));
        }

        $listUrl = route('settings.crm', ['section' => 'users']);
        $displayName = $validated['first_name'] . ' ' . $validated['last_name'];
        $emailSent = false;
        if ($userId) {
            $newUser = VtigerUser::on('vtiger')->find($userId);
            if ($newUser) {
                $emailSent = app(UserManagementService::class)->sendPasswordResetEmail($newUser, true);
            }
        }

        if ($emailSent) {
            return redirect($listUrl)->with(
                'success',
                'User "' . $displayName . '" was created. A password setup email was sent to ' . $validated['email1'] . '.'
            );
        }

        return redirect($listUrl)
            ->with('success', 'User "' . $displayName . '" was created.')
            ->with(
                'error',
                'The password setup email could not be sent. Check mail configuration, or use Reset next to this user to resend the link.'
            );
    }

    public function sendResetLink(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        $sent = app(UserManagementService::class)->sendPasswordResetEmail($targetUser);

        if ($sent) {
            return redirect($this->safeRedirect($request))
                ->with('success', 'Password reset link sent to ' . ($targetUser->email1 ?? $targetUser->user_name));
        }

        return redirect($this->safeRedirect($request))
            ->with('error', 'Could not send reset email. Ensure the user has an email address and mail is configured.');
    }

    public function edit(int $user): View|RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        $departmentsList = app(UserDepartmentService::class)->getDepartmentsList();
        return view('settings.sections.user-edit', ['user' => $targetUser, 'departmentsList' => $departmentsList]);
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required|string|max:40',
            'last_name' => 'required|string|max:80',
            'email1' => 'required|email|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $user)
            ->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email1' => $validated['email1'],
            ]);

        $userDept = app(UserDepartmentService::class);
        if (!empty(trim($validated['department'] ?? ''))) {
            $userDept->setDepartment($user, trim($validated['department']));
        } else {
            $userDept->removeDepartment($user);
        }

        return redirect($this->safeRedirect($request))
            ->with('success', 'User details updated successfully.');
    }

    public function updateDepartment(Request $request, int $user): RedirectResponse
    {
        $validated = $request->validate([
            'department' => 'nullable|string|max:100',
        ]);
        $userDept = app(UserDepartmentService::class);
        $dept = trim($validated['department'] ?? '');
        if ($dept !== '') {
            $userDept->setDepartment($user, $dept);
        } else {
            $userDept->removeDepartment($user);
        }
        return redirect($this->safeRedirect($request))
            ->with('success', 'Department updated.');
    }

    public function updateReportingManager(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        $validated = $request->validate([
            'manager_id' => 'nullable|integer|min:1',
        ]);

        $managerId = !empty($validated['manager_id']) ? (int) $validated['manager_id'] : null;
        if ($managerId !== null) {
            if ($managerId === (int) $targetUser->id) {
                return redirect($this->safeRedirect($request))
                    ->with('error', 'A user cannot report to themselves.');
            }

            $managerExists = VtigerUser::on('vtiger')
                ->where('id', $managerId)
                ->where('status', 'Active')
                ->exists();

            if (! $managerExists) {
                return redirect($this->safeRedirect($request))
                    ->with('error', 'Selected manager is not an active user.');
            }
        }

        if ($managerId === null) {
            UserReportingLine::query()->where('user_id', (int) $targetUser->id)->delete();
        } else {
            UserReportingLine::query()->updateOrCreate(
                ['user_id' => (int) $targetUser->id],
                ['manager_id' => $managerId]
            );
        }

        return redirect($this->safeRedirect($request))
            ->with('success', 'Reporting manager updated.');
    }

    public function offboard(int $user): View|RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')->where('id', $user)->firstOrFail();

        if (Auth::guard('vtiger')->id() === $targetUser->id) {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'You cannot offboard your own account.');
        }

        if (($targetUser->status ?? '') === 'Inactive') {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'User is already deactivated.');
        }

        $recordCounts = app(UserOffboardingService::class)->getRecordCounts($user);
        $totalRecords = array_sum($recordCounts);
        $users = app(CrmService::class)->getActiveUsers()->filter(fn ($u) => (int) $u->id !== $user);

        return view('settings.sections.user-offboard', [
            'user' => $targetUser,
            'recordCounts' => $recordCounts,
            'totalRecords' => $totalRecords,
            'users' => $users,
        ]);
    }

    public function offboardSubmit(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')->where('id', $user)->firstOrFail();

        if (Auth::guard('vtiger')->id() === $targetUser->id) {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'You cannot offboard your own account.');
        }

        if (($targetUser->status ?? '') === 'Inactive') {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'User is already deactivated.');
        }

        $reassignTo = (int) $request->get('reassign_to', 0);

        $offboard = app(UserOffboardingService::class);
        $recordCounts = $offboard->getRecordCounts($user);
        $totalRecords = array_sum($recordCounts);

        if ($totalRecords > 0) {
            if ($reassignTo === $user) {
                return back()->with('error', 'Cannot reassign to the same user.');
            }
            $reassigned = $offboard->reassignRecords($user, $reassignTo);
            $this->forgetCachesAfterReassignment();
        }

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $user)
            ->update(['status' => 'Inactive']);

        app(UserDepartmentService::class)->removeDepartment($user);

        $msg = $totalRecords > 0
            ? sprintf('User "%s" has been offboarded. %d record(s) reassigned. They can no longer sign in.', $targetUser->full_name ?? $targetUser->user_name, $totalRecords)
            : sprintf('User "%s" has been deactivated. They can no longer sign in.', $targetUser->full_name ?? $targetUser->user_name);

        $redirect = $this->safeRedirect($request);
        $base = route('settings.crm', ['section' => 'users', 'status' => 'inactive']);
        if ($redirect === $base || !Str::startsWith($redirect, [url('/'), config('app.url')])) {
            return redirect($base)->with('success', $msg);
        }
        $sep = str_contains($redirect, '?') ? '&' : '?';
        $redirect = preg_replace('/[?&]status=[^&]*/', '', $redirect) . $sep . 'status=inactive';
        return redirect($redirect)->with('success', $msg);
    }

    public function reactivate(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')->where('id', $user)->firstOrFail();

        if (($targetUser->status ?? '') !== 'Inactive') {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'User is already active.');
        }

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $user)
            ->update(['status' => 'Active']);

        return redirect($this->safeRedirect($request))
            ->with('success', 'User "' . ($targetUser->full_name ?? $targetUser->user_name) . '" has been reactivated. They can sign in again.');
    }

    private function forgetCachesAfterReassignment(): void
    {
        foreach (['geminia_ticket_counts_by_status', 'geminia_tickets_count', 'tickets_list_default', 'geminia_contacts_count', 'geminia_leads_count', 'geminia_deals_count', 'geminia_dashboard_stats'] as $key) {
            Cache::forget($key);
        }
        foreach (['Open', 'In_Progress', 'Wait_For_Response', 'Closed', 'Inactive', 'Unassigned'] as $slug) {
            Cache::forget('tickets_list_' . $slug);
        }
        Cache::forget('ticket_assign_users');
        \App\Events\DashboardStatsUpdated::dispatch();
    }

    private function safeRedirect(Request $request): string
    {
        $url = $request->input('redirect', '');
        if ($url && Str::startsWith($url, [url('/'), config('app.url')])) {
            return $url;
        }
        return route('settings.crm', ['section' => 'users']);
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        if (Auth::guard('vtiger')->id() === $targetUser->id) {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('error', 'You cannot delete your own account.');
        }

        return redirect()->route('settings.users.offboard', $user);
    }
}
