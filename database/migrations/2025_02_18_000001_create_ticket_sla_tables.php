<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_department_tat')) {
            return;
        }
        Schema::create('ticket_department_tat', function (Blueprint $table) {
            $table->id();
            $table->string('department', 100)->unique();
            $table->unsignedInteger('tat_hours')->default(24);
            $table->timestamps();
        });

        if (!Schema::hasTable('ticket_sla_settings')) {
            Schema::create('ticket_sla_settings', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            ['department' => 'General', 'tat_hours' => 24, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Support', 'tat_hours' => 24, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Bug', 'tat_hours' => 48, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'Feature', 'tat_hours' => 72, 'created_at' => now(), 'updated_at' => now()],
        ];
        foreach ($defaults as $row) {
            DB::table('ticket_department_tat')->insertOrIgnore($row);
        }
        if (!DB::table('ticket_sla_settings')->where('key', 'roles_can_close')->exists()) {
            DB::table('ticket_sla_settings')->insert([
                'key' => 'roles_can_close', 'value' => json_encode(['Administrator']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_department_tat');
        Schema::dropIfExists('ticket_sla_settings');
    }
};
