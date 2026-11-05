<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->unsignedInteger('telegram_attempts')->default(0);
            $table->timestamp('telegram_next_attempt_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->dropIndex(['telegram_next_attempt_at']);
            $table->dropColumn([
                'telegram_attempts',
                'telegram_next_attempt_at',
            ]);
        });
    }
};
