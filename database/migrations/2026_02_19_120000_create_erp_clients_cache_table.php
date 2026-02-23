<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_clients_cache', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 64)->nullable()->index();
            $table->string('product', 255)->nullable();
            $table->string('pol_prepared_by', 255)->nullable();
            $table->string('intermediary', 255)->nullable();
            $table->string('status', 64)->nullable();
            $table->string('kra_pin', 64)->nullable();
            $table->date('prp_dob')->nullable();
            $table->date('maturity')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['policy_number', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_clients_cache');
    }
};
