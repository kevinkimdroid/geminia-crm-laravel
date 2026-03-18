<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $departments = [
        'Customer Service',
        'Underwriting',
        'Operations',
        'Executive',
        'Control Functions',
        'Finance',
        'IT',
        'Business Development',
    ];

    public function up(): void
    {
        $tableExists = \Schema::hasTable('departments');
        if (!$tableExists) {
            return;
        }

        foreach ($this->departments as $i => $name) {
            DB::table('departments')->insertOrIgnore([
                'name' => $name,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: seed data can stay
    }
};
