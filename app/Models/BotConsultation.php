<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotConsultation extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_user_id',
        'name',
        'phone',
        'description',
        'status',
        'admin_notes',
        'telegram_notified',
        'telegram_notified_at',
    ];

    protected $casts = [
        'telegram_notified' => 'boolean',
        'telegram_notified_at' => 'datetime',
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
