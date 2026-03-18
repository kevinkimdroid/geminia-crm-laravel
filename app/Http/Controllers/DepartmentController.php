<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = trim($validated['name']);
        if (Department::where('name', $name)->exists()) {
            return back()->withInput()->with('error', 'A department with that name already exists.');
        }

        $maxOrder = Department::max('sort_order') ?? 0;
        Department::create([
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()->route('settings.crm', ['section' => 'departments'])
            ->with('success', 'Department added.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = trim($validated['name']);
        $existing = Department::where('name', $name)->where('id', '!=', $department->id)->first();
        if ($existing) {
            return back()->withInput()->with('error', 'A department with that name already exists.');
        }

        $oldName = $department->name;
        $department->update(['name' => $name]);

        // Update user_departments so assignments stay correct
        DB::table('user_departments')->where('department', $oldName)->update(['department' => $name]);

        return redirect()->route('settings.crm', ['section' => 'departments'])
            ->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $count = DB::table('user_departments')->where('department', $department->name)->count();
        if ($count > 0) {
            return back()->with('error', "Cannot delete. {$count} user(s) are assigned to this department. Reassign them first.");
        }

        $department->delete();
        return redirect()->route('settings.crm', ['section' => 'departments'])
            ->with('success', 'Department deleted.');
    }
}
