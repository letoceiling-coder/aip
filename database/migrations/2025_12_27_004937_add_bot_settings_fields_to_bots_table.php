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
        Schema::table('bots', function (Blueprint $table) {
            if (!Schema::hasColumn('bots', 'required_channel_id')) {
                $table->bigInteger('required_channel_id')->nullable();
            }
            if (!Schema::hasColumn('bots', 'required_channel_username')) {
                $table->string('required_channel_username', 255)->nullable();
            }
            if (!Schema::hasColumn('bots', 'admin_telegram_ids')) {
                $table->json('admin_telegram_ids')->nullable();
            }
            if (!Schema::hasColumn('bots', 'yandex_maps_url')) {
                $table->string('yandex_maps_url', 500)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (Schema::hasColumn('bots', 'required_channel_id')) {
                $table->dropColumn('required_channel_id');
            }
            if (Schema::hasColumn('bots', 'required_channel_username')) {
                $table->dropColumn('required_channel_username');
            }
            if (Schema::hasColumn('bots', 'admin_telegram_ids')) {
                $table->dropColumn('admin_telegram_ids');
            }
            if (Schema::hasColumn('bots', 'yandex_maps_url')) {
                $table->dropColumn('yandex_maps_url');
            }
        });
    }
};
