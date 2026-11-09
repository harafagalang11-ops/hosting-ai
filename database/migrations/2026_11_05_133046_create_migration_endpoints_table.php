<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_endpoints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->nullable();

            $table->string('connection_type', 30);

            $table->string('username')->nullable();
            $table->text('credential')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'connection_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_endpoints');
    }
};
