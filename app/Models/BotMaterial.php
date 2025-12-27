<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotMaterial extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'file_type',
        'file_path',
        'file_url',
        'file_id',
        'media_id',
        'order_index',
        'is_active',
        'download_count',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'is_active' => 'boolean',
        'download_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Категория
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BotMaterialCategory::class, 'category_id');
    }

    /**
     * Файл из медиа-библиотеки
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Увеличить счетчик скачиваний
     */
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }
}
