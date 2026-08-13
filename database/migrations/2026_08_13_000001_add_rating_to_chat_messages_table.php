<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'rating')) {
                // 1 = thumbs up, -1 = thumbs down, null = no feedback given
                $table->tinyInteger('rating')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'rating')) {
                $table->dropColumn('rating');
            }
        });
    }
};
