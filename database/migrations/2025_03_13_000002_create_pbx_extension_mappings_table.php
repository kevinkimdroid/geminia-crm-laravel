<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbx_extension_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 32)->unique();
            $table->unsignedBigInteger('vtiger_user_id');
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index('extension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbx_extension_mappings');
    }
};
