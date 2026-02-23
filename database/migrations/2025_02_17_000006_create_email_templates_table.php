<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('module_name')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('email_templates');
    }
};
