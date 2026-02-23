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
}
