<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('erp_clients_cache', 'email_adr')) {
            Schema::table('erp_clients_cache', function (Blueprint $table) {
                $table->string('email_adr', 255)->nullable()->after('policy_number')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('erp_clients_cache', function (Blueprint $table) {
            $table->dropColumn('email_adr');
        });
    }
};
