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
        Schema::table('users', function (Blueprint $table) {

            $table->string('username')
                ->unique()
                ->nullable()
                ->after('name');

            $table->text('bio')
                ->nullable()
                ->after('photo');

            $table->boolean('is_online')
                ->default(false);

            $table->timestamp('last_seen')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'username',
                'bio',
                'is_online',
                'last_seen'
            ]);
        });
    }
};
