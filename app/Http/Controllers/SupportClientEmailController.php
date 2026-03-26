<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use App\Services\PlainTextMailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportClientEmailController extends Controller
{
    public function index(Request $request, CrmService $crm): View
    {
        $presetContact = null;
        if ($request->filled('contact_id')) {
            $presetContact = $crm->getContact((int) $request->get('contact_id'));
        }
        if (! $presetContact && $request->filled('ticket_id')) {
            $ticket = $crm->getTicket((int) $request->get('ticket_id'));
            if ($ticket && ($ticket->contact_id ?? null)) {
                $presetContact = $crm->getContact((int) $ticket->contact_id);
            }
        }

        $presetEmail = null;
        $presetName = null;
        if ($presetContact) {
            $presetEmail = $this->firstValidEmailOnContact($presetContact);
            $presetName = trim(($presetContact->firstname ?? '') . ' ' . ($presetContact->lastname ?? ''));
            $presetName = $presetName !== '' ? $presetName : null;
        }
        $queryEmail = trim((string) $request->get('email', ''));
        $queryEmailValid = $queryEmail !== '' && filter_var($queryEmail, FILTER_VALIDATE_EMAIL);
        if ($presetEmail === null && $queryEmailValid) {
            $presetEmail = $queryEmail;
        }
        if ($request->filled('client_name')) {
            $presetName = trim((string) $request->get('client_name')) ?: $presetName;
        }
        $presetPolicy = $request->filled('policy') ? trim((string) $request->get('policy')) : null;

        $hideCrmPicker = ($presetContact && $presetEmail) || ($presetContact === null && $queryEmailValid);
        $customers = $hideCrmPicker
            ? collect()
            : collect($crm->getCustomers(100, 0, null, crm_owner_filter()))
                ->filter(fn ($c) => $this->firstValidEmailOnContact($c) !== null);

        $composeContext = array_filter([
            'contact_id' => $request->filled('contact_id') ? (int) $request->get('contact_id') : null,
            'ticket_id' => $request->filled('ticket_id') ? (int) $request->get('ticket_id') : null,
            'email' => $queryEmailValid ? $queryEmail : null,
            'client_name' => $request->filled('client_name') ? trim((string) $request->get('client_name')) : null,
            'policy' => $presetPolicy,
        ], fn ($v) => $v !== null && $v !== '');

        return view('support.client-email', [
            'customers' => $customers,
            'presetContact' => $presetContact,
            'presetEmail' => $presetEmail,
            'presetName' => $presetName,
            'presetPolicy' => $presetPolicy,
            'composeContext' => $composeContext,
        ]);
    }

    public function send(Request $request, PlainTextMailSender $mail): RedirectResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email|max:255',
            'to_name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:50000',
        ]);

        $ok = $mail->send(
            $validated['to_email'],
            $validated['to_name'] !== '' && $validated['to_name'] !== null ? $validated['to_name'] : null,
            $validated['subject'],
            $validated['body']
        );

        $redirectQuery = array_filter([
            'contact_id' => $request->filled('compose_contact_id') ? (int) $request->get('compose_contact_id') : null,
            'ticket_id' => $request->filled('compose_ticket_id') ? (int) $request->get('compose_ticket_id') : null,
            'email' => $request->filled('compose_email') ? trim((string) $request->get('compose_email')) : null,
            'client_name' => $request->filled('compose_client_name') ? trim((string) $request->get('compose_client_name')) : null,
            'policy' => $request->filled('compose_policy') ? trim((string) $request->get('compose_policy')) : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! $ok) {
            return redirect()->route('support.email-client', $redirectQuery)
                ->withInput()
                ->with('error', 'Could not send the email. Check mail configuration (Microsoft Graph or SMTP) and logs.');
        }

        return redirect()->route('support.email-client', $redirectQuery)
            ->with('success', 'Email sent to ' . $validated['to_email'] . '.');
    }

    private function firstValidEmailOnContact(object $contact): ?string
    {
        foreach (['email', 'email1', 'secondaryemail', 'otheremail', 'email2'] as $field) {
            $v = trim((string) ($contact->{$field} ?? ''));
            if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return $v;
            }
        }

        return null;
    }
}
