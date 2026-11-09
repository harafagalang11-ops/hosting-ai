<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
        });

        DB::statement('
            UPDATE websites
            SET user_id = (
                SELECT migration_endpoints.user_id
                FROM migration_endpoints
                WHERE migration_endpoints.id = websites.migration_endpoint_id
            )
            WHERE migration_endpoint_id IS NOT NULL
        ');

        if (DB::table('users')->count() === 1) {
            $userId = DB::table('users')->value('id');

            DB::table('websites')
                ->whereNull('user_id')
                ->update(['user_id' => $userId]);
        }
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
