<?php

namespace App\Http\Controllers;

use App\Models\CrmSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TicketDropdownSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_categories_custom' => 'nullable|string|max:65000',
            'ticket_sources_custom' => 'nullable|string|max:65000',
        ]);

        CrmSetting::set('ticket_categories_custom', $validated['ticket_categories_custom'] ?? '');
        CrmSetting::set('ticket_sources_custom', $validated['ticket_sources_custom'] ?? '');

        Cache::forget('ticket_categories_from_crm');
        Cache::forget('ticket_sources_from_crm');

        return redirect()
            ->route('settings.crm', ['section' => 'ticket-dropdowns'])
            ->with('success', 'Ticket Category and Ticket Source options were saved. They are merged with values from the CRM and from .env (TICKET_CATEGORIES / TICKET_SOURCES).');
    }
}
