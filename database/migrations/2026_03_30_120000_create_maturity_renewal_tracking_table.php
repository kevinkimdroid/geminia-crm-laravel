<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('database.default');

        Schema::connection($connection)->create('maturity_renewal_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 64);
            $table->date('maturity');
            $table->string('renewal_status', 32)->default('pending');
            $table->date('renewal_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['policy_number', 'maturity']);
            $table->index('renewal_status');
        });
    }

    public function down(): void
    {
        Schema::connection((string) config('database.default'))->dropIfExists('maturity_renewal_tracking');
    }
};
