<?php

namespace App\Http\Controllers;

use App\Models\VtigerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** @return View|RedirectResponse */
    public function showLogin()
    {
        if (Auth::guard('vtiger')->check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /** @return View */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $user = VtigerUser::on('vtiger')
                ->select(['id', 'user_name', 'first_name', 'last_name', 'email1', 'user_password'])
                ->where('user_name', $validated['user_name'])
                ->where('status', 'Active')
                ->first();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Login DB error: ' . $e->getMessage());
            return back()->withErrors(['user_name' => 'Database unavailable. Please check your connection to the server.'])->withInput();
        }

        if (!$user || !VtigerUser::verifyPassword($validated['password'], $user->user_password)) {
            return back()->withErrors(['user_name' => 'Invalid credentials.'])->withInput();
        }

        Auth::guard('vtiger')->login($user, false); // never remember — session timeout applies
        $request->session()->regenerate();

        // Pre-warm layout cache so dashboard loads fast
        $user->getAllowedModules();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('vtiger')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = $request->email;

        $resetUrl = config('services.password_reset.url');
        if (! empty($resetUrl)) {
            return redirect()->away($resetUrl);
        }

        // Self-service: look up user by email and send reset link
        $user = VtigerUser::on('vtiger')
            ->where('email1', $email)
            ->where('status', 'Active')
            ->first();

        if ($user && app(\App\Services\UserManagementService::class)->sendPasswordResetEmail($user)) {
            return back()->with('status', 'A password reset link has been sent to your email. Check your inbox and follow the link.');
        }

        // Don't reveal whether the email exists; always show success-like message for security
        return back()->with('status', 'If an account exists for that email, a reset link has been sent. Please contact your administrator if you do not receive it.');
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if (! $token || ! $email) {
            return redirect()->route('password.request')
                ->with('status', 'Invalid or expired reset link. Please request a new one.');
        }

        $service = app(\App\Services\UserManagementService::class);
        if (! $service->verifyToken($token, $email)) {
            return redirect()->route('password.request')
                ->with('status', 'This reset link is invalid or has expired. Please request a new one.');
        }

        $user = VtigerUser::on('vtiger')
            ->where('email1', $email)
            ->where('status', 'Active')
            ->select(['user_name'])
            ->first();

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'user_name' => $user?->user_name ?? null,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $service = app(\App\Services\UserManagementService::class);

        if (! $service->verifyToken($request->token, $request->email)) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired. Please contact your administrator for a new one.'])
                ->withInput($request->only('email'));
        }

        $service->resetPassword($request->email, $request->password);

        return redirect()->route('login')->with('status', 'Password reset successfully. You can now sign in.');
    }
}
