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

        // Проверяем, что значения являются строками, а не массивами, и не пустые
        // ОБЯЗАТЕЛЬНЫЕ кнопки всегда должны иметь текст
        if (is_array($materialsButton) || empty(trim((string) $materialsButton))) {
            $materialsButton = '📂 Полезные материалы и договоры';
        } else {
            $materialsButton = trim((string) $materialsButton);
        }
        
        if (is_array($consultationButton) || empty(trim((string) $consultationButton))) {
            $consultationButton = '📞 Записаться на консультацию';
        } else {
            $consultationButton = trim((string) $consultationButton);
        }
        
        $reviewButton = is_array($reviewButton) ? 'Оставь отзыв на Яндекс Картах' : (string) $reviewButton;

        // ОБЯЗАТЕЛЬНО всегда возвращаем две кнопки:
        // 1. Полезные материалы и договоры
        // 2. Записаться на консультацию
        $keyboard = [
            [
                ['text' => $materialsButton, 'callback_data' => BotActions::MENU_MATERIALS],
            ],
            [
                ['text' => $consultationButton, 'callback_data' => BotActions::MENU_CONSULTATION],
            ],
        ];

        // Добавляем кнопку "Скачать презентацию" если файл выбран
        $presentation = $settings['presentation'] ?? [];
        $presentationMediaId = $presentation['media_id'] ?? null;
        if ($presentationMediaId) {
            $presentationButton = $menu['presentation_button'] ?? '📥 Скачать презентацию';
            $presentationButton = is_array($presentationButton) ? '📥 Скачать презентацию' : (string) $presentationButton;
            
            $keyboard[] = [
                ['text' => $presentationButton, 'callback_data' => BotActions::DOWNLOAD_PRESENTATION],
            ];
        }

        if ($bot->yandex_maps_url) {
            $yandexUrl = is_array($bot->yandex_maps_url) ? null : (string) $bot->yandex_maps_url;
            if ($yandexUrl) {
                $keyboard[] = [
                    ['text' => $reviewButton, 'url' => $yandexUrl],
                ];
            }
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
            // Формируем текст кнопки с иконкой (если есть)
            $icon = $category->icon && !is_array($category->icon) ? (string) $category->icon . ' ' : '';
            $name = is_array($category->name) ? '' : (string) ($category->name ?? '');
            
            if (empty($name)) {
                continue; // Пропускаем категории без названия
            }
            
            $buttonText = $icon . $name;
            
            // Всегда используем callback_data для отправки файла
            $keyboard[] = [
                ['text' => $buttonText, 'callback_data' => BotActions::MATERIAL_CATEGORY . $category->id],
            ];
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
            $title = is_array($material->title) ? '' : (string) ($material->title ?? '');
            
            if (empty($title)) {
                continue; // Пропускаем материалы без названия
            }
            
            $keyboard[] = [
                ['text' => $title, 'callback_data' => BotActions::MATERIAL_DOWNLOAD . $material->id],
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

