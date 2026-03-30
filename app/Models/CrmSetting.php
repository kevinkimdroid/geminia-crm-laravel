<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmSetting
{
    public static function tableExists(): bool
    {
        return Schema::hasTable('crm_settings');
    }

    public static function get(string $key): ?string
    {
        if (! self::tableExists()) {
            return null;
        }

        $row = DB::table('crm_settings')->where('key', $key)->first();

        return $row && isset($row->value) ? (string) $row->value : null;
    }

    public static function set(string $key, ?string $value): void
    {
        if (! self::tableExists()) {
            return;
        }

        DB::table('crm_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value ?? '',
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @return list<string>
     */
    public static function parsedLines(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }
}
