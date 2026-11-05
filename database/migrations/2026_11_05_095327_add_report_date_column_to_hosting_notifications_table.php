<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->date('report_date')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_notifications', function (Blueprint $table) {
            $table->dropUnique(['report_date']);
            $table->dropColumn('report_date');
        });
    }
};
