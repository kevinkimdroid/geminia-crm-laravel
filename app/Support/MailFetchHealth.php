<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MailFetchHealth
{
    public const CACHE_KEY = 'mail_fetch_health';

    public static function get(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'last_attempt_at' => null,
            'last_success_at' => null,
            'status' => 'unknown',
            'trigger' => null,
            'fetched' => 0,
            'stored' => 0,
            'error' => null,
        ]);
    }

    public static function markSuccess(array $result, string $trigger): void
    {
        Cache::forever(self::CACHE_KEY, [
            'last_attempt_at' => now()->toIso8601String(),
            'last_success_at' => now()->toIso8601String(),
            'status' => 'ok',
            'trigger' => $trigger,
            'fetched' => (int) ($result['fetched'] ?? 0),
            'stored' => (int) ($result['stored'] ?? 0),
            'error' => null,
        ]);
    }

    public static function markFailure(string $error, string $trigger): void
    {
        $current = self::get();

        Cache::forever(self::CACHE_KEY, [
            'last_attempt_at' => now()->toIso8601String(),
            'last_success_at' => $current['last_success_at'] ?? null,
            'status' => 'error',
            'trigger' => $trigger,
            'fetched' => (int) ($current['fetched'] ?? 0),
            'stored' => (int) ($current['stored'] ?? 0),
            'error' => $error,
        ]);
    }
}
