<?php

namespace App\Http\Controllers;

use App\Services\TicketAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketAutomationController extends Controller
{
    public function __construct(
        private TicketAutomationService $automation
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'keywords' => 'required|string|max:500',
            'assign_to_user_id' => 'required|integer',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $data = [
            'name' => $validated['name'],
            'keywords' => $validated['keywords'],
            'assign_to_user_id' => $validated['assign_to_user_id'],
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => $request->has('is_active'),
        ];

        try {
            $this->automation->createRule($data);
            return redirect($request->input('redirect', route('settings.crm') . '?section=ticket-automation'))
                ->with('success', 'Rule created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create rule: ' . $e->getMessage());
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'keywords' => 'required|string|max:500',
            'assign_to_user_id' => 'required|integer',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $data = [
            'name' => $validated['name'],
            'keywords' => $validated['keywords'],
            'assign_to_user_id' => $validated['assign_to_user_id'],
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => $request->has('is_active'),
        ];

        try {
            $this->automation->updateRule($id, $data);
            return redirect($request->input('redirect', route('settings.crm') . '?section=ticket-automation'))
                ->with('success', 'Rule updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update rule: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->automation->deleteRule($id);
            return redirect(route('settings.crm') . '?section=ticket-automation')->with('success', 'Rule deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete rule: ' . $e->getMessage());
        }
    }
}
