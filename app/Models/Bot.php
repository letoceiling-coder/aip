<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'token',
        'username',
        'webhook_url',
        'webhook_registered',
        'welcome_message',
        'settings',
        'is_active',
        'required_channel_id',
        'required_channel_username',
        'admin_telegram_ids',
        'yandex_maps_url',
    ];

    protected $casts = [
        'webhook_registered' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'admin_telegram_ids' => 'array',
    ];

    /**
     * Пользователи бота
     */
    public function botUsers(): HasMany
    {
        return $this->hasMany(BotUser::class, 'bot_id');
    }

    /**
     * Проверки подписок
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(BotSubscription::class, 'bot_id');
    }

    /**
     * Категории материалов
     */
    public function materialCategories(): HasMany
    {
        return $this->hasMany(BotMaterialCategory::class, 'bot_id')
            ->where('is_active', true)
            ->orderBy('order_index', 'asc');
    }

    /**
     * Все категории материалов (включая неактивные)
     */
    public function allMaterialCategories(): HasMany
    {
        return $this->hasMany(BotMaterialCategory::class, 'bot_id')
            ->orderBy('order_index', 'asc');
    }

    /**
     * Заявки на консультацию
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(BotConsultation::class, 'bot_id');
    }

    /**
     * Логи
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BotLog::class, 'bot_id');
    }
}
