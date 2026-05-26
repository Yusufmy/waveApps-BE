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
        Schema::table('conversations', function (Blueprint $table) {

            $table->text('last_message')
                ->nullable()
                ->after('type');

            $table->timestamp('last_message_at')
                ->nullable()
                ->after('last_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {

            $table->dropColumn([
                'last_message',
                'last_message_at'
            ]);
        });
    }
};
