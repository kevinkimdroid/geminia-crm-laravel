<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_maturity_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('policy_no', 64);
            $table->date('maturity_date');
            $table->string('recipient_email', 255);
            $table->string('cc_email', 255)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['policy_no', 'maturity_date', 'recipient_email'], 'inv_maturity_notif_unique');
            $table->index(['maturity_date', 'sent_at'], 'inv_maturity_notif_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_maturity_notifications');
    }
};

