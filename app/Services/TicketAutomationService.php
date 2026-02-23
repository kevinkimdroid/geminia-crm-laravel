<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketAutomationService
{
    /**
     * Resolve assign_to user ID based on ticket title/description keywords.
     */
    public function resolveAssignee(string $title, ?string $description = null): ?int
    {
        $text = $title . ' ' . ($description ?? '');
        $text = strtolower($text);

        $rules = DB::connection('vtiger')
            ->table('ticket_automation_rules')
            ->where('is_active', 1)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            $keywords = $this->parseKeywords($rule->keywords);
            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower(trim($keyword))) !== false) {
                    return (int) $rule->assign_to_user_id;
                }
            }
        }

        return null;
    }

    private function parseKeywords(string $keywords): array
    {
        $list = array_map('trim', explode(',', $keywords));
        return array_filter($list);
    }

    public function getRules(): array
    {
        return DB::connection('vtiger')
            ->table('ticket_automation_rules as r')
            ->leftJoin('vtiger_users as u', 'r.assign_to_user_id', '=', 'u.id')
            ->select('r.*', 'u.first_name', 'u.last_name', 'u.user_name')
            ->orderByDesc('r.priority')
            ->get()
            ->all();
    }

    public function createRule(array $data): int
    {
        return DB::connection('vtiger')->table('ticket_automation_rules')->insertGetId([
            'name' => $data['name'],
            'keywords' => $data['keywords'],
            'assign_to_user_id' => $data['assign_to_user_id'],
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateRule(int $id, array $data): bool
    {
        return DB::connection('vtiger')
            ->table('ticket_automation_rules')
            ->where('id', $id)
            ->update([
                'name' => $data['name'],
                'keywords' => $data['keywords'],
                'assign_to_user_id' => $data['assign_to_user_id'],
                'is_active' => $data['is_active'] ?? true,
                'priority' => $data['priority'] ?? 0,
                'updated_at' => now(),
            ]) > 0;
    }

    public function deleteRule(int $id): bool
    {
        return DB::connection('vtiger')
            ->table('ticket_automation_rules')
            ->where('id', $id)
            ->delete() > 0;
    }

    public function getRule(int $id): ?object
    {
        return DB::connection('vtiger')
            ->table('ticket_automation_rules')
            ->where('id', $id)
            ->first();
    }
}
