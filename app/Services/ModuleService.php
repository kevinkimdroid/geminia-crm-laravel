<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Manages global module enable/disable state (Module Management).
 */
class ModuleService
{
    protected string $cacheKey = 'geminia_enabled_modules_v2';

    /**
     * Default module definitions (matches Vtiger-style module list).
     */
    public function getDefinitions(): array
    {
        return [
            ['key' => 'deals', 'label' => 'Opportunities', 'icon' => 'bi-briefcase-fill', 'sort' => 1],
            ['key' => 'contacts', 'label' => 'Customers', 'icon' => 'bi-person-lines-fill', 'sort' => 2],
            ['key' => 'leads', 'label' => 'Leads', 'icon' => 'bi-people-fill', 'sort' => 3],
            ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'bi-calendar3', 'sort' => 4],
            ['key' => 'tickets', 'label' => 'Tickets', 'icon' => 'bi-ticket-perforated-fill', 'sort' => 5],
            ['key' => 'work-tickets', 'label' => 'Work Tickets', 'icon' => 'bi-kanban-fill', 'sort' => 6],
            ['key' => 'support.faq', 'label' => 'FAQ', 'icon' => 'bi-question-circle', 'sort' => 7],
            ['key' => 'tools.email-templates', 'label' => 'Emails', 'icon' => 'bi-envelope', 'sort' => 8],
            ['key' => 'tools.mail-manager', 'label' => 'Mail Manager', 'icon' => 'bi-envelope-at', 'sort' => 9],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-file-text', 'sort' => 10],
            ['key' => 'marketing.campaigns', 'label' => 'Campaigns', 'icon' => 'bi-megaphone', 'sort' => 11],
            ['key' => 'marketing.broadcast', 'label' => 'Email & SMS broadcast', 'icon' => 'bi-broadcast', 'sort' => 12],
            ['key' => 'marketing.social-media', 'label' => 'Webmails / Social', 'icon' => 'bi-globe', 'sort' => 13],
            ['key' => 'support.customers', 'label' => 'Clients', 'icon' => 'bi-people', 'sort' => 14],
            ['key' => 'tools.pbx-manager', 'label' => 'PBX Manager', 'icon' => 'bi-telephone', 'sort' => 15],
            ['key' => 'tools.pdf-maker', 'label' => 'PDF Maker', 'icon' => 'bi-file-pdf', 'sort' => 16],
            ['key' => 'tools.recycle-bin', 'label' => 'Recycle Bin', 'icon' => 'bi-trash', 'sort' => 17],
            ['key' => 'support.sms-notifier', 'label' => 'SMS Notifier', 'icon' => 'bi-chat-dots', 'sort' => 18],
            ['key' => 'compliance.complaints', 'label' => 'Complaint Register', 'icon' => 'bi-clipboard2-data', 'sort' => 19],
        ];
    }

    /**
     * Get all modules with their enabled state from DB (or defaults).
     */
    public function getAllModules(): array
    {
        $definitions = $this->getDefinitions();
        $dbRows = DB::table('app_modules')->pluck('enabled', 'module_key')->toArray();

        $result = [];
        foreach ($definitions as $def) {
            $key = $def['key'];
            $result[] = [
                'key' => $key,
                'label' => $def['label'],
                'icon' => $def['icon'] ?? 'bi-box',
                'sort' => $def['sort'],
                'enabled' => array_key_exists($key, $dbRows) ? (bool) $dbRows[$key] : true,
            ];
        }
        usort($result, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        return $result;
    }

    /**
     * Get list of enabled module keys (for sidebar filtering).
     * When DB is empty, all modules are enabled. Otherwise DB overrides defaults.
     */
    public function getEnabledModuleKeys(): array
    {
        return Cache::remember($this->cacheKey, 300, function () {
            $allKeys = array_keys(config('modules.app_to_vtiger', []));
            $rows = DB::table('app_modules')->pluck('enabled', 'module_key')->toArray();
            if (empty($rows)) {
                return $allKeys;
            }
            $alwaysOn = ['dashboard', 'marketing', 'support', 'tools', 'settings', 'settings.crm', 'settings.manage-users', 'compliance.complaints'];
            $enabled = [];
            foreach ($allKeys as $key) {
                if (in_array($key, $alwaysOn, true)) {
                    $enabled[] = $key;
                } elseif (array_key_exists($key, $rows)) {
                    if ($rows[$key]) {
                        $enabled[] = $key;
                    }
                } else {
                    $enabled[] = $key; // not in DB = default on
                }
            }
            return array_values(array_unique($enabled));
        });
    }

    /**
     * Toggle a module's enabled state.
     */
    public function toggle(string $moduleKey, bool $enabled): bool
    {
        $def = collect($this->getDefinitions())->firstWhere('key', $moduleKey);
        if (!$def) {
            return false;
        }

        $exists = DB::table('app_modules')->where('module_key', $moduleKey)->exists();
        if ($exists) {
            DB::table('app_modules')->where('module_key', $moduleKey)->update(['enabled' => $enabled]);
        } else {
            DB::table('app_modules')->insert([
                'module_key' => $moduleKey,
                'label' => $def['label'],
                'icon' => $def['icon'] ?? null,
                'enabled' => $enabled,
                'sort_order' => $def['sort'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget($this->cacheKey);
        return true;
    }

    /**
     * Seed default modules if table is empty.
     */
    public function seedIfEmpty(): void
    {
        if (DB::table('app_modules')->exists()) {
            return;
        }
        foreach ($this->getDefinitions() as $def) {
            DB::table('app_modules')->insert([
                'module_key' => $def['key'],
                'label' => $def['label'],
                'icon' => $def['icon'] ?? null,
                'enabled' => true,
                'sort_order' => $def['sort'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
