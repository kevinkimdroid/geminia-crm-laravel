<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Services\AdvantaSmsService;
use App\Services\CrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsNotifierController extends Controller
{
    public function index(Request $request, AdvantaSmsService $sms, CrmService $crm): View
    {
        $contactId = $request->filled('contact_id') ? (int) $request->get('contact_id') : null;
        $ticketId = $request->filled('ticket_id') ? (int) $request->get('ticket_id') : null;
        $presetPhone = $request->filled('phone') ? preg_replace('/\D/', '', trim($request->get('phone'))) : null;
        if ($presetPhone !== null && strlen($presetPhone) < 9) {
            $presetPhone = null;
        }

        $presetContact = null;
        if ($contactId) {
            $presetContact = $crm->getContact($contactId);
        } elseif ($ticketId) {
            $ticket = $crm->getTicket($ticketId);
            if ($ticket && ($ticket->contact_id ?? null)) {
                $presetContact = $crm->getContact($ticket->contact_id);
            }
        }

        $customers = $presetContact ? collect([$presetContact]) : $crm->getCustomers(100, 0, null, crm_owner_filter());
        return view('support.sms-notifier', [
            'customers' => $customers,
            'smsConfigured' => $sms->isConfigured(),
            'presetContact' => $presetContact,
            'presetPhone' => $presetPhone,
            'presetPhoneDisplay' => $request->filled('phone') ? trim($request->get('phone')) : null,
        ]);
    }

    public function send(Request $request, AdvantaSmsService $sms, CrmService $crm): RedirectResponse
    {
        $request->validate([
            'message' => 'required|string|max:1600',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string|max:20',
        ]);

        $recipients = $request->input('recipients');
        $message = $request->input('message');
        $results = $sms->sendBulk($recipients, $message);
        $successCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));
        $failCount = count($results) - $successCount;

        $userId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id();
        foreach ($results as $r) {
            $phone = $r['mobile'] ?? '';
            if ($phone === '') {
                continue;
            }
            $contact = $crm->findContactByPhoneOrEmail($phone, null);
            SmsLog::create([
                'contact_id' => $contact ? $contact->contactid : null,
                'phone' => $phone,
                'message' => $message,
                'status' => ($r['success'] ?? false) ? 'sent' : 'failed',
                'error_message' => ($r['success'] ?? false) ? null : ($r['error'] ?? null),
                'user_id' => $userId,
                'sent_at' => now(),
            ]);
        }

        if ($failCount === 0) {
            return redirect()->route('support.sms-notifier')
                ->with('success', "SMS sent successfully to {$successCount} recipient(s).");
        }

        if ($successCount > 0) {
            return redirect()->route('support.sms-notifier')
                ->with('warning', "Sent to {$successCount} recipient(s). {$failCount} failed.");
        }

        $firstError = $results[0]['error'] ?? 'Unknown error';
        return redirect()->route('support.sms-notifier')
            ->withInput()
            ->with('error', 'SMS failed: ' . $firstError);
    }
}
