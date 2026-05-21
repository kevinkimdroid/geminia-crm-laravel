<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('erp_message_id', 64)->nullable()->index()->after('id');
            $table->string('erp_policy_no', 64)->nullable()->index()->after('erp_message_id');
            $table->string('delivery_status', 32)->nullable()->index()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->longText('provider_response')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['erp_message_id']);
            $table->dropIndex(['erp_policy_no']);
            $table->dropIndex(['delivery_status']);
            $table->dropColumn([
                'erp_message_id',
                'erp_policy_no',
                'delivery_status',
                'delivered_at',
                'read_at',
                'provider_response',
            ]);
        });
    }
};
