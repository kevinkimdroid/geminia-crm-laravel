<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->table('pdf_templates', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('tagline')->nullable()->after('company_name');
            $table->string('footer_text')->nullable()->after('footer_content');
            $table->boolean('show_page_numbers')->default(false)->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->table('pdf_templates', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'tagline', 'footer_text', 'show_page_numbers']);
        });
    }
};
