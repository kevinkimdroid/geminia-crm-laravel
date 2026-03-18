<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_departments', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary()->comment('Vtiger user id');
            $table->string('department', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_departments');
    }
};
