<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('websites')->whereNull('user_id')->exists()) {
            throw new RuntimeException(
                'Migrasi dibatalkan: masih ada website tanpa pemilik.'
            );
        }

        $hasInvalidOwner = DB::table('websites')
            ->leftJoin('users', 'users.id', '=', 'websites.user_id')
            ->whereNull('users.id')
            ->exists();

        if ($hasInvalidOwner) {
            throw new RuntimeException(
                'Migrasi dibatalkan: ditemukan user_id website yang tidak valid.'
            );
        }

        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable(false)
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);

            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->change();
        });
    }
};
