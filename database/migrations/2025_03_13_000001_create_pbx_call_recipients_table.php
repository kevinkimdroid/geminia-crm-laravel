<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbx_call_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('call_source', 16); // 'vtiger' | 'local'
            $table->unsignedBigInteger('call_id');
            $table->unsignedBigInteger('received_by_user_id');
            $table->string('received_by_user_name')->nullable();
            $table->timestamps();

            $table->unique(['call_source', 'call_id'], 'pbx_call_recipients_call_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbx_call_recipients');
    }
};
