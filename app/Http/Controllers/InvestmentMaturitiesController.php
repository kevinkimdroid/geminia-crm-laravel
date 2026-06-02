<?php

namespace App\Http\Controllers;

use App\Services\InvestmentMaturityService;
use App\Services\MicrosoftGraphMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InvestmentMaturitiesController extends Controller
{
    public function __construct(protected InvestmentMaturityService $service) {}

    public function index(Request $request): View
    {
        $days = max(1, min(30, (int) $request->get('days', 14)));
        $to = (string) config('maturities.investment_notifications.to', 'douglas.nyakwara@geminialife.co.ke');
        $cc = $this->ccRecipients();

        $error = null;
        $rows = collect();
        try {
            $rows = $this->service->dueWithinDays($days);
            $rows = $this->service->withNotificationStatus($rows, $to);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('Investment maturities load failed', ['error' => $e->getMessage()]);
        }

        return view('support.investment-maturities', [
            'rows' => $rows,
            'days' => $days,
            'to' => $to,
            'cc' => $cc,
            'error' => $error,
            'trackingEnabled' => $this->service->notificationsTableExists(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'resend' => 'nullable|boolean',
        ]);
        $days = (int) ($validated['days'] ?? 14);
        $resend = (bool) ($validated['resend'] ?? false);
        $to = trim((string) config('maturities.investment_notifications.to', 'douglas.nyakwara@geminialife.co.ke'));
        $cc = $this->ccRecipients();

        if (! $this->service->notificationsTableExists()) {
            return redirect()->route('support.investment-maturities', ['days' => $days])
                ->with('error', 'Notification tracking table missing. Run php artisan migrate.');
        }

        try {
            $due = $this->service->dueWithinDays($days);
            $unsent = $this->service->unsentRows($due, $to);
            $targetRows = $resend ? $due : $unsent;

            if ($targetRows->isEmpty()) {
                return redirect()->route('support.investment-maturities', ['days' => $days])
                    ->with('success', 'No new investment maturities to email. Already-sent rows were skipped.');
            }

            $subject = '[Maturities] Investment policies due in next ' . $days . ' days (' . $targetRows->count() . ')';
            $html = view('emails.investment-maturities-notification', [
                'rows' => $targetRows,
                'days' => $days,
                'generatedAt' => now(),
                'resend' => $resend,
            ])->render();

            if (! $this->sendNotificationEmail($to, $cc, $subject, $html)) {
                return redirect()->route('support.investment-maturities', ['days' => $days])
                    ->with('error', 'Failed to send email. Check mail connectivity (Graph/SMTP).');
            }

            $this->service->markAsSent($targetRows, $to, $cc !== [] ? implode(',', $cc) : null);

            return redirect()->route('support.investment-maturities', ['days' => $days])
                ->with('success', 'Email sent to ' . $this->recipientLabel($to, $cc) . ' (' . $targetRows->count() . ' row(s)).');
        } catch (\Throwable $e) {
            Log::error('Investment maturities email send failed', ['error' => $e->getMessage()]);

            return redirect()->route('support.investment-maturities', ['days' => $days])
                ->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    private function ccRecipients(): array
    {
        $cc = config('maturities.investment_notifications.cc', []);
        if (is_string($cc)) {
            $cc = explode(',', $cc);
        }

        return array_values(array_filter(array_map(fn ($value) => trim((string) $value), (array) $cc)));
    }

    /**
     * @param  array<int, string>  $cc
     */
    private function recipientLabel(string $to, array $cc): string
    {
        if ($cc === []) {
            return $to;
        }

        return $to . ' (cc: ' . implode(', ', $cc) . ')';
    }

    /**
     * @param  array<int, string>  $cc
     */
    private function sendNotificationEmail(string $to, array $cc, string $subject, string $html): bool
    {
        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            $ok = $graph->sendMail($to, null, $subject, $html, true);
            if (! $ok) {
                Log::warning('Investment maturities mail: Graph send failed for primary recipient', ['to' => $to]);
            } else {
                foreach ($cc as $ccAddress) {
                    $ccOk = $graph->sendMail($ccAddress, null, $subject, $html, true);
                    if (! $ccOk) {
                        Log::warning('Investment maturities mail: Graph send failed for CC recipient', ['cc' => $ccAddress]);
                    }
                }

                return true;
            }
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Mail::send([], [], function ($message) use ($to, $cc, $subject, $html) {
                    $message->to($to)->subject($subject)->setBody($html, 'text/html');
                    if ($cc !== []) {
                        $message->cc($cc);
                    }
                });

                return true;
            } catch (\Throwable $e) {
                Log::warning('Investment maturities mail: SMTP send failed', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(800 * 1000);
                }
            }
        }

        return false;
    }
}

