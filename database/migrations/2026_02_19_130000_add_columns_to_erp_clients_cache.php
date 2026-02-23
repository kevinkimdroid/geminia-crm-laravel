<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_clients_cache', function (Blueprint $table) {
            $table->decimal('paid_mat_amt', 18, 2)->nullable()->after('kra_pin');
            $table->string('checkoff', 64)->nullable()->after('paid_mat_amt');
            $table->date('effective_date')->nullable()->after('checkoff');
        });
    }

    public function down(): void
    {
        Schema::table('erp_clients_cache', function (Blueprint $table) {
            $table->dropColumn(['paid_mat_amt', 'checkoff', 'effective_date']);
        });
    }
};
