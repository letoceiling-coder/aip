<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->unsignedBigInteger('update_id')->nullable();
            $table->string('event_type', 50);
            $table->string('action', 100)->nullable();
            $table->json('data')->nullable();
            $table->string('response_status', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            // Внешний ключ на bot_users не создаем, так как user_id может быть из разных ботов
            $table->index(['bot_id', 'event_type', 'created_at']);
            $table->index(['telegram_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
