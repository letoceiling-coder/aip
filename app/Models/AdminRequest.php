<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRequest extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'status',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
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
        return $this->belongsTo(BotUser::class, 'telegram_user_id', 'telegram_user_id')
            ->where('bot_id', $this->bot_id);
    }

    /**
     * Администратор, который одобрил заявку
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
