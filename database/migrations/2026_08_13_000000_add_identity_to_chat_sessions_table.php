<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_sessions', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('chat_sessions', 'guest_token')) {
                $table->string('guest_token', 64)->nullable()->after('user_id')->index();
            }
            if (!Schema::hasColumn('chat_sessions', 'provider')) {
                $table->string('provider', 40)->nullable()->after('title');
            }
            if (!Schema::hasColumn('chat_sessions', 'profile')) {
                $table->string('profile', 60)->nullable()->after('provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            foreach (['user_id', 'guest_token', 'provider', 'profile'] as $col) {
                if (Schema::hasColumn('chat_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
