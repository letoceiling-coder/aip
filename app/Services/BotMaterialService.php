<?php

namespace App\Services;

use App\Models\BotMaterial;
use App\Models\BotMaterialCategory;
use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class BotMaterialService
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Получить категории материалов
     */
    public function getCategories(int $botId): Collection
    {
        return BotMaterialCategory::where('bot_id', $botId)
            ->where('is_active', true)
            ->orderBy('order_index', 'asc')
            ->get();
    }

    /**
     * Получить материалы категории
     */
    public function getCategoryMaterials(int $categoryId): Collection
    {
        $category = BotMaterialCategory::find($categoryId);
        
        if (!$category) {
            return collect([]);
        }

        return $category->materials;
    }

    /**
     * Отправить материал пользователю
     */
    public function sendMaterial(string $token, int $chatId, int $materialId): array
    {
        $material = BotMaterial::find($materialId);
        
        if (!$material || !$material->is_active) {
            return [
                'success' => false,
                'message' => 'Материал не найден',
            ];
        }

        $result = null;

        // Обработка в зависимости от типа файла
        switch ($material->file_type) {
            case 'telegram_file_id':
                // Используем file_id (самый быстрый способ)
                if ($material->file_id) {
                    $result = $this->telegram->sendDocumentByFileId(
                        $token,
                        $chatId,
                        $material->file_id,
                        $material->description
                    );
                }
                break;

            case 'file':
                $filePath = $this->getMaterialFilePath($materialId);
                
                if ($filePath && file_exists($filePath)) {
                    $result = $this->telegram->sendDocument(
                        $token,
                        $chatId,
                        $filePath,
                        $material->description
                    );
                    
                    // Сохраняем file_id для будущих отправок (если успешно отправлено)
                    if ($result['success'] && isset($result['data']['document']['file_id'])) {
                        $material->file_id = $result['data']['document']['file_id'];
                        $material->save();
                    }
                } else {
                    return [
                        'success' => false,
                        'message' => 'Файл не найден',
                    ];
                }
                break;

            case 'url':
                // Отправляем сообщение со ссылкой
                $message = $material->description ?: $material->title;
                if ($material->file_url) {
                    $message .= "\n\n📎 " . $material->file_url;
                }
                
                $result = $this->telegram->sendMessage($token, $chatId, $message);
                break;

            default:
                return [
                    'success' => false,
                    'message' => 'Неподдерживаемый тип файла',
                ];
        }

        // Увеличиваем счетчик скачиваний при успешной отправке
        if ($result && ($result['success'] ?? false)) {
            $this->incrementDownloadCount($materialId);
        }

        return $result ?? [
            'success' => false,
            'message' => 'Не удалось отправить материал',
        ];
    }

    /**
     * Увеличить счетчик скачиваний
     */
    public function incrementDownloadCount(int $materialId): void
    {
        BotMaterial::where('id', $materialId)->increment('download_count');
    }

    /**
     * Получить путь к файлу материала
     */
    public function getMaterialFilePath(int $materialId): ?string
    {
        $material = BotMaterial::find($materialId);
        
        if (!$material) {
            return null;
        }

        // Если есть связь с медиа-библиотекой
        if ($material->media_id) {
            $media = Media::find($material->media_id);
            
            if ($media) {
                $metadata = is_string($media->metadata) 
                    ? json_decode($media->metadata, true) 
                    : $media->metadata;
                
                $path = $metadata['path'] ?? ($media->disk . '/' . $media->name);
                $fullPath = public_path($path);
                
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        }

        // Старый способ - через file_path
        if ($material->file_path) {
            $fullPath = storage_path('app/' . $material->file_path);
            
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Получить URL файла материала
     */
    public function getMaterialFileUrl(int $materialId): ?string
    {
        $material = BotMaterial::find($materialId);
        
        if (!$material) {
            return null;
        }

        // Если есть связь с медиа-библиотекой
        if ($material->media_id) {
            $media = Media::find($material->media_id);
            
            if ($media) {
                return $media->url;
            }
        }

        // Старый способ
        if ($material->file_path) {
            return Storage::url($material->file_path);
        }

        // Внешняя ссылка
        if ($material->file_url) {
            return $material->file_url;
        }

        return null;
    }
}

