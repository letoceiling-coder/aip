<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotUser extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'is_subscribed',
        'subscription_checked_at',
        'current_state',
        'state_data',
        'last_interaction_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'subscription_checked_at' => 'datetime',
        'state_data' => 'array',
        'last_interaction_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Бот
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * История проверок подписок
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(BotSubscription::class, 'telegram_user_id', 'telegram_user_id')
            ->where('bot_subscriptions.bot_id', $this->bot_id)
            ->orderBy('checked_at', 'desc');
    }

    /**
     * Заявки на консультацию
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(BotConsultation::class, 'telegram_user_id', 'telegram_user_id')
            ->where('bot_consultations.bot_id', $this->bot_id)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Логи
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BotLog::class, 'telegram_user_id', 'telegram_user_id')
            ->where('bot_logs.bot_id', $this->bot_id)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Получить полное имя пользователя
     */
    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name ?: ($this->username ?? "User #{$this->telegram_user_id}");
    }
}
