<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vtiger')->table('mail_manager_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('complaint_id')->nullable()->after('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::connection('vtiger')->table('mail_manager_emails', function (Blueprint $table) {
            $table->dropColumn('complaint_id');
        });
    }
};
