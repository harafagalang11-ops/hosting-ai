<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->foreignId('website_id')
                ->nullable()
                ->after('id')
                ->constrained('websites')
                ->nullOnDelete();

            $table->index('website_id');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->dropForeign(['website_id']);
            $table->dropIndex(['website_id']);
            $table->dropColumn('website_id');
        });
    }
};
