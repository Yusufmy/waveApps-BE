<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->integer('duration')->nullable()->after('attachment_url');
            $table->unsignedBigInteger('file_size')->nullable()->after('duration');
            $table->string('file_name')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'duration',
                'file_size',
                'file_name',
            ]);
        });
    }
};
