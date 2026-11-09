<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('target');
            $table->string('status', 20);
            $table->unsignedInteger('file_count')->nullable();
            $table->string('database_integrity')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('backup_file')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_migrations');
    }
};
