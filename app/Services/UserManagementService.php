<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Handles admin-initiated password resets and user management.
 */
class UserManagementService
{
    protected int $tokenExpiryMinutes = 60;

    protected function tokensConnection(): string
    {
        return config('services.password_reset.connection') ?? config('database.default');
    }

    /**
     * Generate a password reset token for a user and send the reset email.
     */
    public function sendPasswordResetEmail(object $user): bool
    {
        $email = trim($user->email1 ?? '');
        if ($email === '') {
            Log::warning('UserManagementService: user has no email', ['user_id' => $user->id]);
            return false;
        }

        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);

        try {
            DB::connection($this->tokensConnection())
                ->table('password_reset_tokens')
                ->updateOrInsert(
                    ['email' => $email],
                    [
                        'token' => $hashedToken,
                        'created_at' => now(),
                    ]
                );
        } catch (\Throwable $e) {
            Log::error('UserManagementService: failed to store reset token', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name;
        $resetUrl = rtrim(config('app.url', ''), '/') . '/password/reset?token=' . urlencode($token) . '&email=' . urlencode($email);
        $appName = config('app.name', 'Geminia Life');

        $subject = 'Reset your password — ' . $appName;
        $body = "Hello {$userName},\n\n"
            . "A password reset was requested for your account. Click the link below to set a new password:\n\n"
            . "{$resetUrl}\n\n"
            . "This link expires in {$this->tokenExpiryMinutes} minutes. If you did not request this, please ignore this email.\n\n"
            . "Kind regards,\n{$appName}";

        return $this->send($email, $userName, $subject, $body);
    }

    /**
     * Verify a password reset token and return the email if valid.
     */
    public function verifyToken(string $token, string $email): bool
    {
        $conn = $this->tokensConnection();
        $record = DB::connection($conn)
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || ! hash_equals($record->token, hash('sha256', $token))) {
            return false;
        }

        $createdAt = $record->created_at ? \Carbon\Carbon::parse($record->created_at) : null;
        if ($createdAt && $createdAt->addMinutes($this->tokenExpiryMinutes)->isPast()) {
            DB::connection($conn)->table('password_reset_tokens')->where('email', $email)->delete();
            return false;
        }

        return true;
    }

    /**
     * Update user password and delete reset token.
     */
    public function resetPassword(string $email, string $password): bool
    {
        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('email1', $email)
            ->update([
                'user_password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

        DB::connection($this->tokensConnection())
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
        return true;
    }

    protected function send(string $to, ?string $toName, string $subject, string $body): bool
    {
        $graph = app(MicrosoftGraphMailService::class);
        if ($graph->isConfigured()) {
            if ($graph->sendMail($to, $toName, $subject, $body, false)) {
                return true;
            }
            Log::warning('UserManagementService: Graph send failed, falling back to Laravel Mail', ['to' => $to]);
        }

        try {
            $from = config('mail.from.address', config('email-service.sender', 'life@geminialife.co.ke'));
            $fromName = config('mail.from.name', config('app.name'));
            Mail::raw($body, function ($message) use ($to, $toName, $subject, $from, $fromName) {
                $message->to($to, $toName)->from($from, $fromName)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('UserManagementService: send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
