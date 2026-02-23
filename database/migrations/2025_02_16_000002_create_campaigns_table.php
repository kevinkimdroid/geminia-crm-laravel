<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->string('campaign_type')->nullable();
            $table->string('campaign_status')->default('Active');
            $table->decimal('expected_revenue', 15, 2)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('list_name')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('campaigns');
    }
};
