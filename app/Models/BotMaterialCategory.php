<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotMaterialCategory extends Model
{
    protected $fillable = [
        'bot_id',
        'name',
        'description',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'is_active' => 'boolean',
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
     * Материалы категории
     */
    public function materials(): HasMany
    {
        return $this->hasMany(BotMaterial::class, 'category_id')
            ->where('is_active', true)
            ->orderBy('order_index', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Все материалы категории (включая неактивные)
     */
    public function allMaterials(): HasMany
    {
        return $this->hasMany(BotMaterial::class, 'category_id')
            ->orderBy('order_index', 'asc')
            ->orderBy('id', 'asc');
    }
}
