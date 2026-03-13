<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->table('scheduled_social_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_social_posts', function (Blueprint $table) {
            $table->dropColumn('campaign_id');
        });
    }
};
