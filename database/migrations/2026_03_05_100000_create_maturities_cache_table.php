<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maturities_cache', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 64)->nullable()->index();
            $table->string('life_assured', 255)->nullable();
            $table->string('product', 255)->nullable()->index();
            $table->date('maturity')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['maturity', 'product']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maturities_cache');
    }
};
