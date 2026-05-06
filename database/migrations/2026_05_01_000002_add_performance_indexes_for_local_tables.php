<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'finance_payments_status_created_idx');
            $table->index(['payment_method', 'created_at'], 'finance_payments_method_created_idx');
            $table->index(['recorded_by', 'created_at'], 'finance_payments_recorded_created_idx');
        });

        Schema::table('work_tickets', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'work_tickets_status_updated_idx');
            $table->index(['assignee_id', 'status'], 'work_tickets_assignee_status_idx');
        });

        Schema::table('work_ticket_updates', function (Blueprint $table) {
            $table->index(['work_ticket_id', 'created_at'], 'work_ticket_updates_ticket_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_ticket_updates', function (Blueprint $table) {
            $table->dropIndex('work_ticket_updates_ticket_created_idx');
        });

        Schema::table('work_tickets', function (Blueprint $table) {
            $table->dropIndex('work_tickets_status_updated_idx');
            $table->dropIndex('work_tickets_assignee_status_idx');
        });

        Schema::table('finance_payments', function (Blueprint $table) {
            $table->dropIndex('finance_payments_status_created_idx');
            $table->dropIndex('finance_payments_method_created_idx');
            $table->dropIndex('finance_payments_recorded_created_idx');
        });
    }
};
