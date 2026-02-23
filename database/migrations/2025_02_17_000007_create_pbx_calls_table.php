<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('pbx_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_status', 64)->nullable()->index();
            $table->string('direction', 32)->nullable();
            $table->string('customer_number')->nullable();
            $table->string('reason_for_calling')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('user_name')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('recording_path')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->dateTime('start_time')->nullable();
            $table->string('external_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('pbx_calls');
    }
};
