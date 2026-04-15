<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_reporting_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('manager_id')->index();
            $table->timestamps();
        });

        Schema::create('work_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 40)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 40)->default('Open');
            $table->string('priority', 20)->default('Medium');
            $table->unsignedBigInteger('assignee_id')->index();
            $table->unsignedBigInteger('reporting_manager_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('work_ticket_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_ticket_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('update_text');
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->unsignedInteger('time_spent_minutes')->nullable();
            $table->boolean('is_blocked')->default(false)->index();
            $table->string('work_mode', 20)->nullable();
            $table->string('status_after_update', 40)->nullable();
            $table->text('blocker_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_ticket_updates');
        Schema::dropIfExists('work_tickets');
        Schema::dropIfExists('user_reporting_lines');
    }
};
