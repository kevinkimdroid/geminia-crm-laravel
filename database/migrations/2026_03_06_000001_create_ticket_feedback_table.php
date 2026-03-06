<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('rating', 20); // happy, not_happy, or 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->unique(['ticket_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_feedback');
    }
};
