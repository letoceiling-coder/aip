<?php

namespace App\Services;

use App\Constants\BotActions;
use App\Models\Bot;
use App\Models\BotMaterialCategory;

class BotMenuService
{
    /**
     * Получить клавиатуру главного меню
     */
    public function getMainMenuKeyboard(Bot $bot): array
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $menu = $messages['menu'] ?? [];

        $materialsButton = $menu['materials_button'] ?? '📂 Полезные материалы и договоры';
        $consultationButton = $menu['consultation_button'] ?? '📞 Записаться на консультацию';
        $reviewButton = $menu['review_button'] ?? 'Оставь отзыв на Яндекс Картах';

        $keyboard = [
            [
                ['text' => $materialsButton, 'callback_data' => BotActions::MENU_MATERIALS],
            ],
            [
                ['text' => $consultationButton, 'callback_data' => BotActions::MENU_CONSULTATION],
            ],
        ];

        if ($bot->yandex_maps_url) {
            $keyboard[] = [
                ['text' => $reviewButton, 'url' => $bot->yandex_maps_url],
            ];
        }

        return $keyboard;
    }

    /**
     * Получить клавиатуру списка материалов
     */
    public function getMaterialsListKeyboard(int $botId): array
    {
        $categories = BotMaterialCategory::where('bot_id', $botId)
            ->where('is_active', true)
            ->orderBy('order_index', 'asc')
            ->get();

        $keyboard = [];
        foreach ($categories as $category) {
            if ($category->external_url) {
                // Если есть external_url, используем web_app для открытия в Mini App
                $keyboard[] = [
                    ['text' => $category->name, 'web_app' => ['url' => $category->external_url]],
                ];
            } else {
                // Если нет external_url, используем callback_data (старая логика)
                $keyboard[] = [
                    ['text' => $category->name, 'callback_data' => BotActions::MATERIAL_CATEGORY . $category->id],
                ];
            }
        }

        $keyboard[] = [
            ['text' => '⬅️ Назад в меню', 'callback_data' => BotActions::BACK_MAIN_MENU],
        ];

        return $keyboard;
    }

    /**
     * Получить клавиатуру категории материала
     */
    public function getMaterialCategoryKeyboard(int $categoryId): array
    {
        $category = BotMaterialCategory::find($categoryId);
        if (!$category) {
            return [];
        }

        $keyboard = [];
        $materials = $category->materials()->where('is_active', true)->get();
        
        foreach ($materials as $material) {
            $keyboard[] = [
                ['text' => $material->title, 'callback_data' => BotActions::MATERIAL_DOWNLOAD . $material->id],
            ];
        }

        $keyboard[] = [
            ['text' => '⬅️ Назад', 'callback_data' => BotActions::BACK_MATERIALS_LIST],
        ];

        return $keyboard;
    }

    /**
     * Получить клавиатуру для консультации
     */
    public function getConsultationKeyboard(): array
    {
        return [
            [
                ['text' => '📝 Записаться на консультацию', 'callback_data' => BotActions::CONSULTATION_START],
            ],
            [
                ['text' => '⬅️ Назад в меню', 'callback_data' => BotActions::BACK_MAIN_MENU],
            ],
        ];
    }

    /**
     * Получить клавиатуру "Назад"
     */
    public function getBackKeyboard(string $backAction): array
    {
        return [
            [
                ['text' => '⬅️ Назад', 'callback_data' => $backAction],
            ],
        ];
    }
}

