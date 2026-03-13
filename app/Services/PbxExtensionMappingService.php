<?php

namespace App\Services;

use App\Models\PbxExtensionMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maps PBX extensions to vTiger users for the Users column in PBX Manager.
 * Use when vTiger's phone_crm_extension mapping is not configured.
 */
class PbxExtensionMappingService
{
    /**
     * Resolve user name from PBX user/extension value.
     * When vTiger's join returns empty, p.user may contain extension (if connector stores it).
     *
     * @param  string|null  $userName  From vtiger_users join
     * @param  mixed  $pbxUser  Raw p.user from vtiger_pbxmanager
     */
    public function resolveUserName(?string $userName, $pbxUser): ?string
    {
        if (trim($userName ?? '') !== '') {
            return trim($userName);
        }

        if ($pbxUser === null || $pbxUser === '') {
            return null;
        }

        $extension = (string) $pbxUser;
        $mapping = $this->getMappingByExtension($extension);

        return $mapping?->user_name;
    }

    public function getMappingByExtension(string $extension): ?PbxExtensionMapping
    {
        $extension = preg_replace('/\D/', '', $extension);
        if ($extension === '') {
            return null;
        }

        $cacheKey = 'pbx_ext_map:' . $extension;

        return Cache::remember($cacheKey, 300, function () use ($extension) {
            return PbxExtensionMapping::where('extension', $extension)->first();
        });
    }

    /**
     * Sync mappings from vTiger users who have phone_crm_extension set.
     */
    public function syncFromVtiger(): int
    {
        $count = 0;

        try {
            $users = DB::connection('vtiger')
                ->table('vtiger_users as u')
                ->leftJoin('vtiger_user_preferences as up', function ($j) {
                    $j->on('u.id', '=', 'up.userid')
                        ->where('up.key', '=', 'phone_crm_extension');
                })
                ->whereNotNull('up.value')
                ->where('up.value', '!=', '')
                ->select('u.id', 'u.first_name', 'u.last_name', 'u.user_name', 'up.value as extension')
                ->get();

            foreach ($users as $u) {
                $ext = preg_replace('/\D/', '', trim($u->extension ?? ''));
                if ($ext === '') {
                    continue;
                }

                $userName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->user_name ?? '';

                PbxExtensionMapping::updateOrCreate(
                    ['extension' => $ext],
                    [
                        'vtiger_user_id' => $u->id,
                        'user_name' => $userName,
                    ]
                );
                $count++;
            }

            Cache::flush();
        } catch (\Throwable $e) {
            // vtiger_user_preferences may not exist or have different structure
        }

        return $count;
    }

    /**
     * Get all mappings for admin UI.
     */
    public function getAll(): \Illuminate\Support\Collection
    {
        return PbxExtensionMapping::orderBy('extension')->get();
    }
}
