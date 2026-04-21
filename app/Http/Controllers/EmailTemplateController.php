<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    protected array $modules = [
        'Marketing',
        'Broadcast',
        'Broadcast SMS',
        'Customers',
        'Events',
        'Leads',
        'Contacts',
        'Deals',
        'Tickets',
    ];

    public function index(Request $request)
    {
        $query = EmailTemplate::query();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('template_name', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('module_name', 'like', $term);
            });
        }

        if ($request->filled('module')) {
            $mod = (string) $request->input('module');
            if (in_array($mod, $this->modules, true)) {
                $query->where('module_name', $mod);
            }
        }

        $templates = $query->orderBy('template_name')->paginate(15)->withQueryString();

        return view('tools.email-templates', [
            'templates' => $templates,
            'modules' => $this->modules,
        ]);
    }

    public function create()
    {
        return view('tools.email-templates-form', [
            'template' => null,
            'modules' => $this->modules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'module_name' => 'nullable|string|max:128',
            'body' => 'nullable|string',
        ]);

        EmailTemplate::create($validated);

        return redirect()->route('tools.email-templates')
            ->with('success', 'Email template created successfully.');
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        return view('tools.email-templates-form', [
            'template' => $emailTemplate,
            'modules' => $this->modules,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'module_name' => 'nullable|string|max:128',
            'body' => 'nullable|string',
        ]);

        $emailTemplate->update($validated);

        return redirect()->route('tools.email-templates')
            ->with('success', 'Email template updated successfully.');
    }

    public function destroy(EmailTemplate $emailTemplate)
    {
        $emailTemplate->delete();

        return redirect()->route('tools.email-templates')
            ->with('success', 'Email template deleted.');
    }
}
