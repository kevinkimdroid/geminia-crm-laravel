<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 40)->unique();
            $table->string('customer_name', 160);
            $table->string('policy_number', 80)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('KES');
            $table->string('payment_method', 50)->default('Bank Transfer')->index();
            $table->string('status', 30)->default('Pending')->index();
            $table->date('expected_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->dateTime('tat_due_at')->nullable();
            $table->dateTime('tat_breached_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};
