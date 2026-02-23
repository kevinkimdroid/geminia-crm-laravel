<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('pdf_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64)->index();
            $table->string('name')->default('Default');
            $table->text('header_content')->nullable();
            $table->text('footer_content')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('body_template')->nullable();
            $table->json('field_layout')->nullable();
            $table->json('styles')->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('pdf_templates');
    }
};
