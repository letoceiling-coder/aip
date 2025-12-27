<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSubscription extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_user_id',
        'channel_id',
        'channel_username',
        'is_subscribed',
        'checked_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'checked_at' => 'datetime',
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
     * Пользователь бота
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class, 'telegram_user_id', 'telegram_user_id');
    }
}
