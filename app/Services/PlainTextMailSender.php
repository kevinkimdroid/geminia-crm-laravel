<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends plain-text outbound mail (Graph when configured, else Laravel mail).
 */
class PlainTextMailSender
{
    protected ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param  array<int, array{name: string, contentType?: string, content: string}>  $attachments
     */
    public function send(string $to, ?string $toName, string $subject, string $body, array $attachments = []): bool
    {
        $this->lastError = null;

        $sendViaLaravel = function () use ($to, $toName, $subject, $body, $attachments): bool {
            $mailer = config('mail.default');
            if ($mailer === 'log') {
                Log::warning('PlainTextMailSender: MAIL_MAILER=log – email will not be delivered. Set MAIL_MAILER=smtp (or configure Graph) for actual delivery.');
            }

            $maxAttempts = max(1, (int) config('mass_broadcast.smtp_retry_attempts', 3));
            $retryDelayMs = max(100, (int) config('mass_broadcast.smtp_retry_delay_ms', 800));
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $from = config('mail.from.address', config('email-service.sender', 'life@geminialife.co.ke'));
                    $fromName = config('mail.from.name', config('app.name'));
                    Mail::raw($body, function ($message) use ($to, $toName, $subject, $from, $fromName, $attachments) {
                        $message->to($to, $toName)->from($from, $fromName)->subject($subject);
                        foreach ($attachments as $attachment) {
                            if (empty($attachment['name']) || ! isset($attachment['content'])) {
                                continue;
                            }
                            $message->attachData(
                                $attachment['content'],
                                (string) $attachment['name'],
                                ['mime' => (string) ($attachment['contentType'] ?? 'application/octet-stream')]
                            );
                        }
                    });

                    return true;
                } catch (\Throwable $e) {
                    $msg = (string) $e->getMessage();
                    $this->lastError = 'SMTP: ' . $msg;
                    $transient = str_contains($msg, 'errno=10054')
                        || str_contains($msg, 'timed out')
                        || str_contains($msg, 'Connection could not be established');
                    Log::warning('PlainTextMailSender: send failed', [
                        'to' => $to,
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'transient' => $transient,
                        'error' => $msg,
                    ]);
                    if ($attempt < $maxAttempts && $transient) {
                        usleep($retryDelayMs * 1000);
                        continue;
                    }

                    return false;
                }
            }

            return false;
        };

        $graph = app(MicrosoftGraphMailService::class);
        $graphConfigured = $graph->isConfigured();

        if ($attachments !== []) {
            // For Office 365 environments, Graph attachment sends are usually more reliable than SMTP auth.
            $graphAttachmentMaxBytes = max(1024 * 1024, (int) config('mass_broadcast.graph_attachment_max_bytes', 3 * 1024 * 1024));
            $totalAttachmentBytes = 0;
            foreach ($attachments as $attachment) {
                $totalAttachmentBytes += strlen((string) ($attachment['content'] ?? ''));
            }

            if ($graphConfigured && $totalAttachmentBytes <= $graphAttachmentMaxBytes) {
                if ($graph->sendMail($to, $toName, $subject, $body, false, $attachments)) {
                    return true;
                }
                $this->lastError = 'Graph: sendMail failed for attachment send.';
                Log::warning('PlainTextMailSender: Graph attachment send failed, falling back to Laravel Mail', [
                    'to' => $to,
                    'attachment_bytes' => $totalAttachmentBytes,
                ]);
            } elseif ($graphConfigured && $totalAttachmentBytes > $graphAttachmentMaxBytes) {
                $this->lastError = 'Graph: attachment too large for direct Graph send (' . $totalAttachmentBytes . ' bytes).';
                Log::warning('PlainTextMailSender: Graph skipped for large attachment', [
                    'to' => $to,
                    'attachment_bytes' => $totalAttachmentBytes,
                    'graph_attachment_max_bytes' => $graphAttachmentMaxBytes,
                ]);
            }

            return $sendViaLaravel();
        }

        if ($graphConfigured) {
            if ($graph->sendMail($to, $toName, $subject, $body, false, [])) {
                return true;
            }
            $this->lastError = 'Graph: sendMail failed.';
            Log::warning('PlainTextMailSender: Graph send failed, falling back to Laravel Mail', ['to' => $to]);
        }

        return $sendViaLaravel();
    }

    /**
     * Send plain-text email with a single PDF attachment (Graph when configured, else Laravel Mail).
     *
     * @param  string  $pdfBinary  Raw PDF bytes
     */
    public function sendWithPdfAttachment(
        string $to,
        ?string $toName,
        string $subject,
        string $body,
        string $pdfFilename,
        string $pdfBinary
    ): bool {
        return $this->send($to, $toName, $subject, $body, [
            ['name' => $pdfFilename, 'contentType' => 'application/pdf', 'content' => $pdfBinary],
        ]);
    }
}
