<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('website_name');
            $table->string('website_url');
            $table->string('event_type'); // gangguan atau pulih
            $table->string('title');
            $table->text('message');
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('telegram_error')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['website_name', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_notifications');
    }
};
