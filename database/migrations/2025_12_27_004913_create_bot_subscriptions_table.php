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
        Schema::create('bot_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('telegram_user_id');
            $table->bigInteger('channel_id'); // Может быть отрицательным для групп/каналов
            $table->string('channel_username', 255)->nullable();
            $table->boolean('is_subscribed')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            // Внешний ключ на bot_users через bot_id и user_id не создаем (составной ключ)
            // Связь будет проверяться на уровне приложения
            $table->index(['bot_id', 'telegram_user_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_subscriptions');
    }
};
