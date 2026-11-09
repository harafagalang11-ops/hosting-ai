<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->foreignId('migration_endpoint_id')
                ->nullable()
                ->after('id')
                ->constrained('migration_endpoints')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('migration_endpoint_id');
        });
    }
};
