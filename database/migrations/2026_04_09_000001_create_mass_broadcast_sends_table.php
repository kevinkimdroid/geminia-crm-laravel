<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_broadcast_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('channel', 8);
            $table->string('content_digest', 64)->index();
            $table->string('subject_snapshot', 200)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('sent_at')->index();
            $table->timestamps();

            $table->index(['contact_id', 'channel', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_broadcast_sends');
    }
};
