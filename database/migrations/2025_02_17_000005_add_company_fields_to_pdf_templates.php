<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->table('pdf_templates', function (Blueprint $table) {
            $table->string('company_address')->nullable()->after('tagline');
            $table->string('company_zip')->nullable()->after('company_address');
            $table->string('company_city')->nullable()->after('company_zip');
            $table->string('company_country')->nullable()->after('company_city');
            $table->string('company_phone')->nullable()->after('company_country');
            $table->string('company_fax')->nullable()->after('company_phone');
            $table->string('company_website')->nullable()->after('company_fax');
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->table('pdf_templates', function (Blueprint $table) {
            $table->dropColumn([
                'company_address', 'company_zip', 'company_city', 'company_country',
                'company_phone', 'company_fax', 'company_website',
            ]);
        });
    }
};
