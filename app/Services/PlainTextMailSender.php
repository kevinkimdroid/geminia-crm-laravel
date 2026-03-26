<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends plain-text outbound mail (Graph when configured, else Laravel mail).
 */
class PlainTextMailSender
{
    public function send(string $to, ?string $toName, string $subject, string $body): bool
    {
        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            if ($graph->sendMail($to, $toName, $subject, $body, false)) {
                return true;
            }
            Log::warning('PlainTextMailSender: Graph send failed, falling back to Laravel Mail', ['to' => $to]);
        }

        $mailer = config('mail.default');
        if ($mailer === 'log') {
            Log::warning('PlainTextMailSender: MAIL_MAILER=log – email will not be delivered. Set MAIL_MAILER=smtp (or configure Graph) for actual delivery.');
        }

        try {
            $from = config('mail.from.address', config('email-service.sender', 'life@geminialife.co.ke'));
            $fromName = config('mail.from.name', config('app.name'));
            Mail::raw($body, function ($message) use ($to, $toName, $subject, $from, $fromName) {
                $message->to($to, $toName)->from($from, $fromName)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('PlainTextMailSender: send failed', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
