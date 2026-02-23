<?php

namespace App\Http\Controllers;

use App\Models\VtigerUser;
use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(
        private CrmService $crm
    ) {}

    public function index(Request $request): View
    {
        $activityType = $request->get('type');
        $status = $request->get('status');
        $search = $request->get('search');
        $contactId = $request->filled('contact_id') ? (int) $request->get('contact_id') : null;
        $ticketId = $request->filled('ticket_id') ? (int) $request->get('ticket_id') : null;
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $activities = collect();
        if ($contactId || $ticketId) {
            $activities = $this->crm->getActivities($perPage, $offset, $activityType, $status, $search, $contactId, $ticketId);
        }

        try {
            $users = VtigerUser::on('vtiger')->where('status', 'Active')->orderBy('first_name')->orderBy('last_name')->get();
        } catch (\Throwable $e) {
            $users = collect();
        }
        $contacts = $this->crm->getContacts(200, 0);
        $tickets = $contactId ? $this->crm->getTicketsForContact($contactId) : collect();

        return view('activities.index', [
            'activities' => $activities,
            'activityType' => $activityType,
            'status' => $status,
            'search' => $search,
            'contactId' => $contactId,
            'ticketId' => $ticketId,
            'users' => $users,
            'contacts' => $contacts,
            'tickets' => $tickets,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->get('type', 'Event');
        $relatedTo = $request->get('related_to');
        $contacts = $this->crm->getContacts(200, 0);
        return view('activities.create', ['type' => $type, 'contacts' => $contacts, 'relatedTo' => $relatedTo]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::guard('vtiger')->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to schedule activities.');
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'activitytype' => 'required|in:Task,Event,Meeting,Call',
            'date_start' => 'required|date',
            'due_date' => 'nullable|date',
            'time_start' => 'nullable|string|max:20',
            'time_end' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'related_to' => 'nullable|integer',
            'ticket_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer',
        ]);

        $validated['due_date'] = $validated['due_date'] ?? $validated['date_start'];
        $ownerId = !empty($validated['assigned_to']) ? (int) $validated['assigned_to'] : $user->id;

        $id = $this->crm->createActivity($validated, $ownerId);
        if ($id) {
            $params = [];
            if (!empty($validated['related_to'])) {
                $params['contact_id'] = $validated['related_to'];
            }
            if (!empty($validated['ticket_id'])) {
                $params['ticket_id'] = $validated['ticket_id'];
            }
            return redirect()->route('activities.index', $params)->with('success', 'Activity scheduled successfully.');
        }

        return back()->withInput()->with('error', 'Failed to create activity.');
    }

    public function ticketsForContact(int $contact): JsonResponse
    {
        $tickets = $this->crm->getTicketsForContact($contact);
        return response()->json($tickets);
    }
}
