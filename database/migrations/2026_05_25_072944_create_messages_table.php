<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('conversation_id');
            $table->foreignId('sender_id');

            $table->longText('message')->nullable();

            $table->enum('message_type', [
                'text',
                'image',
                'video',
                'audio',
                'file'
            ]);

            $table->text('attachment_url')->nullable();

            $table->enum('status', [
                'sent',
                'delivered',
                'read'
            ])->default('sent');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('conversation_id');
            $table->index('sender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
