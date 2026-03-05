<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_clients_cache', function (Blueprint $table) {
            $table->string('life_assured', 255)->nullable()->after('policy_number');
        });
    }

    public function down(): void
    {
        Schema::table('erp_clients_cache', function (Blueprint $table) {
            $table->dropColumn('life_assured');
        });
    }
};
