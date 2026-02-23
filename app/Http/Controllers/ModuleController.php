<?php

namespace App\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function toggle(Request $request, ModuleService $modules): RedirectResponse
    {
        $request->validate([
            'module_key' => 'required|string|max:80',
            'enabled' => 'required|boolean',
        ]);

        if ($modules->toggle($request->input('module_key'), (bool) $request->input('enabled'))) {
            return redirect()
                ->route('settings.crm', ['section' => 'modules'])
                ->with('success', 'Module updated.');
        }

        return redirect()
            ->route('settings.crm', ['section' => 'modules'])
            ->with('error', 'Module not found.');
    }
}
