<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_logs', 'advanta_message_id')) {
                $table->string('advanta_message_id', 32)->nullable()->index()->after('erp_message_id');
            }
            if (! Schema::hasColumn('sms_logs', 'advanta_status')) {
                $table->string('advanta_status', 64)->nullable()->after('delivery_status');
            }
            if (! Schema::hasColumn('sms_logs', 'advanta_delivery_tat')) {
                $table->string('advanta_delivery_tat', 32)->nullable()->after('advanta_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $cols = ['advanta_message_id', 'advanta_status', 'advanta_delivery_tat'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sms_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
