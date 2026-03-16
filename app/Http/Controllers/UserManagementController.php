<?php

namespace App\Http\Controllers;

use App\Models\VtigerUser;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function sendResetLink(Request $request, int $user): RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        $sent = app(UserManagementService::class)->sendPasswordResetEmail($targetUser);

        if ($sent) {
            return redirect()->route('settings.crm', ['section' => 'users'])
                ->with('success', 'Password reset link sent to ' . ($targetUser->email1 ?? $targetUser->user_name));
        }

        return redirect()->route('settings.crm', ['section' => 'users'])
            ->with('error', 'Could not send reset email. Ensure the user has an email address and mail is configured.');
    }

    public function edit(int $user): View|RedirectResponse
    {
        $targetUser = VtigerUser::on('vtiger')
            ->where('id', $user)
            ->where('status', 'Active')
            ->firstOrFail();

        return view('settings.sections.user-edit', ['user' => $targetUser]);
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
        ]);

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $user)
            ->update($validated);

        return redirect()->route('settings.crm', ['section' => 'users'])
            ->with('success', 'User details updated successfully.');
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

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $user)
            ->update(['status' => 'Inactive']);

        return redirect()->route('settings.crm', ['section' => 'users'])
            ->with('success', 'User "' . $targetUser->full_name . '" has been deactivated. They can no longer sign in.');
    }
}
