<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_reassignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->string('from_user_name', 100)->nullable();
            $table->unsignedBigInteger('to_user_id');
            $table->string('to_user_name', 100)->nullable();
            $table->unsignedBigInteger('reassigned_by_user_id')->nullable();
            $table->string('reassigned_by_name', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reassignments');
    }
};
