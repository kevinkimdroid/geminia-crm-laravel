<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('contact_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('note');
            $table->date('followup_date')->nullable();
            $table->string('status', 50)->default('pending'); // pending, completed, cancelled
            $table->timestamps();

            $table->index(['contact_id', 'followup_date']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('contact_followups');
    }
};
