<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complaint Register - IRA/compliance requirement for insurance.
     */
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_ref', 32)->unique();
            $table->date('date_received');
            $table->string('complainant_name');
            $table->string('complainant_phone', 50)->nullable();
            $table->string('complainant_email', 255)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('policy_number', 64)->nullable();
            $table->string('nature', 100)->nullable();
            $table->text('description');
            $table->string('source', 50)->nullable();
            $table->string('status', 50)->default('Received');
            $table->string('priority', 20)->default('Medium');
            $table->string('assigned_to', 255)->nullable();
            $table->date('date_resolved')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('complaints');
    }
};
