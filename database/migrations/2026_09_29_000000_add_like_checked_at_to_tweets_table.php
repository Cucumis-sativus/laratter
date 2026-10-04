<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tweets', function (Blueprint $table) {
            // いいねチェックが済んだ日時（null = まだチェックしていない）
            $table->timestamp('like_checked_at')->nullable()->after('tweet');
        });

        // 既存の Tweet は削除対象にしないよう，チェック済みにしておく
        DB::table('tweets')->update(['like_checked_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tweets', function (Blueprint $table) {
            $table->dropColumn('like_checked_at');
        });
    }
};
