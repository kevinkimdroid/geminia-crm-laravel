<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('database.default'))->create('social_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('post_external_id')->nullable(); // tweet id, video id, etc.
            $table->string('external_id')->nullable(); // comment/reply id from platform
            $table->string('type'); // comment, reply, like, mention, dm
            $table->string('author_name')->nullable();
            $table->string('author_handle')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_phone')->nullable();
            $table->text('content')->nullable();
            $table->string('post_url')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable(); // vtiger lead id if converted
            $table->json('metadata')->nullable();
            $table->timestamp('interaction_at');
            $table->timestamps();

            $table->index(['platform', 'type']);
            $table->index(['social_account_id', 'interaction_at']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.default'))->dropIfExists('social_interactions');
    }
};
