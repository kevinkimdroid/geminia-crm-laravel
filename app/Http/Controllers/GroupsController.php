<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'groupname' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            DB::connection('vtiger')->table('vtiger_groups')->insert([
                'groupname' => $validated['groupname'],
                'description' => $validated['description'] ?? '',
            ]);
            return redirect($request->input('redirect', route('settings.crm') . '?section=groups'))
                ->with('success', 'Group created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create group: ' . $e->getMessage());
        }
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'groupname' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            DB::connection('vtiger')->table('vtiger_groups')
                ->where('groupid', $id)
                ->update([
                    'groupname' => $validated['groupname'],
                    'description' => $validated['description'] ?? '',
                ]);
            return redirect($request->input('redirect', route('settings.crm') . '?section=groups'))
                ->with('success', 'Group updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update group: ' . $e->getMessage());
        }
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            DB::connection('vtiger')->table('vtiger_groups')->where('groupid', $id)->delete();
            return redirect(route('settings.crm') . '?section=groups')->with('success', 'Group deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete group: ' . $e->getMessage());
        }
    }
}
