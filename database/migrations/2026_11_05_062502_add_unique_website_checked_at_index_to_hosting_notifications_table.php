<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX hosting_notifications_website_checked_unique
            ON hosting_notifications (website_id, checked_at)
            WHERE website_id IS NOT NULL
              AND checked_at IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            DROP INDEX IF EXISTS hosting_notifications_website_checked_unique
        ');
    }
};
