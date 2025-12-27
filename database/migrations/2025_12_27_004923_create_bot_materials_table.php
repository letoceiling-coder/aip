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
        Schema::create('bot_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('file_type', ['file', 'url', 'document', 'telegram_file_id'])->default('file');
            $table->string('file_path', 500)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->string('file_id', 255)->nullable(); // Telegram file_id
            $table->unsignedBigInteger('media_id')->nullable(); // Связь с медиа-библиотекой
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('bot_material_categories')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('set null');
            $table->index(['category_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_materials');
    }
};
