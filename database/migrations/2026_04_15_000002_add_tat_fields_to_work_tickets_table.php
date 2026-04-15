<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('work_tickets', 'tat_hours')) {
                $table->unsignedInteger('tat_hours')->default(24)->after('due_date');
            }
            if (!Schema::hasColumn('work_tickets', 'tat_due_at')) {
                $table->timestamp('tat_due_at')->nullable()->after('tat_hours')->index();
            }
            if (!Schema::hasColumn('work_tickets', 'tat_breached_at')) {
                $table->timestamp('tat_breached_at')->nullable()->after('tat_due_at')->index();
            }
        });

        DB::table('work_tickets')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $priority = strtolower((string) ($row->priority ?? 'medium'));
                    $tatHours = match ($priority) {
                        'urgent' => 8,
                        'high' => 24,
                        'low' => 72,
                        default => 48, // medium
                    };

                    $createdAt = !empty($row->created_at) ? Carbon::parse($row->created_at) : now();
                    $tatDueAt = $createdAt->copy()->addHours($tatHours);
                    $status = (string) ($row->status ?? '');
                    $completedAt = !empty($row->completed_at) ? Carbon::parse($row->completed_at) : null;
                    $breachedAt = null;

                    if ($status === 'Done' && $completedAt && $completedAt->gt($tatDueAt)) {
                        $breachedAt = $completedAt;
                    } elseif ($status !== 'Done' && now()->gt($tatDueAt)) {
                        $breachedAt = now();
                    }

                    DB::table('work_tickets')
                        ->where('id', $row->id)
                        ->update([
                            'tat_hours' => !empty($row->tat_hours) ? (int) $row->tat_hours : $tatHours,
                            'tat_due_at' => $row->tat_due_at ?? $tatDueAt,
                            'tat_breached_at' => $row->tat_breached_at ?? $breachedAt,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('work_tickets', 'tat_breached_at')) {
                $table->dropColumn('tat_breached_at');
            }
            if (Schema::hasColumn('work_tickets', 'tat_due_at')) {
                $table->dropColumn('tat_due_at');
            }
            if (Schema::hasColumn('work_tickets', 'tat_hours')) {
                $table->dropColumn('tat_hours');
            }
        });
    }
};
