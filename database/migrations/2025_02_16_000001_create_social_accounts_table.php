<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // facebook, instagram, twitter, youtube, tiktok
            $table->string('account_id')->nullable();
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('social_accounts');
    }
};
