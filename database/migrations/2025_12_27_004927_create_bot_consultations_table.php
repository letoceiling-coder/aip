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
        Schema::create('bot_consultations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('name', 255);
            $table->string('phone', 50);
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'in_progress', 'closed'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->boolean('telegram_notified')->default(false);
            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            // Внешний ключ на bot_users через bot_id и user_id не создаем (составной ключ)
            // Связь будет проверяться на уровне приложения
            $table->index(['bot_id', 'status', 'created_at']);
            $table->index(['telegram_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_consultations');
    }
};
