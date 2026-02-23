<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('scheduled_social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // twitter, youtube, facebook, instagram, tiktok
            $table->text('content');
            $table->json('media_urls')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status')->default('scheduled'); // scheduled, published, failed, cancelled
            $table->string('external_id')->nullable(); // id from platform after publish
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('scheduled_social_posts');
    }
};
