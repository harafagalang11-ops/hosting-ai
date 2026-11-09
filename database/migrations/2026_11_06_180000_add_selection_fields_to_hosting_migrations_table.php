<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_migrations', function (Blueprint $table) {
            $table->foreignId('source_endpoint_id')
                ->nullable()
                ->constrained('migration_endpoints')
                ->nullOnDelete();

            $table->foreignId('target_endpoint_id')
                ->nullable()
                ->constrained('migration_endpoints')
                ->nullOnDelete();

            $table->foreignId('website_id')
                ->nullable()
                ->constrained('websites')
                ->nullOnDelete();

            $table->string('website_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_migrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_endpoint_id');
            $table->dropConstrainedForeignId('target_endpoint_id');
            $table->dropConstrainedForeignId('website_id');
            $table->dropColumn('website_name');
        });
    }
};
