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
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('telegram_user_id'); // Telegram user ID
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code', 10)->nullable();
            $table->boolean('is_subscribed')->default(false);
            $table->timestamp('subscription_checked_at')->nullable();
            $table->string('current_state', 255)->nullable();
            $table->json('state_data')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique(['bot_id', 'telegram_user_id']); // Один пользователь может быть пользователем разных ботов
            $table->index(['bot_id', 'is_subscribed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_users');
    }
};
