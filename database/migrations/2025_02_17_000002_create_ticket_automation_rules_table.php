<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vtiger')->create('ticket_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('keywords'); // comma-separated or JSON
            $table->unsignedInteger('assign_to_user_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('priority')->default(0); // higher = checked first
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::connection('vtiger')->dropIfExists('ticket_automation_rules');
    }
};
