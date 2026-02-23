<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vtiger')->create('mail_manager_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_uid', 100)->unique();
            $table->string('folder', 255)->default('INBOX');
            $table->string('from_address', 255);
            $table->string('from_name')->nullable();
            $table->string('to_addresses')->nullable();
            $table->string('cc_addresses')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('date')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->timestamps();

            $table->index(['from_address', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::connection('vtiger')->dropIfExists('mail_manager_emails');
    }
};
