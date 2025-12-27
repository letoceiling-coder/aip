<?php

namespace App\Services;

use App\Constants\BotStates;
use App\Models\Bot;
use App\Models\BotConsultation;
use App\Models\BotUser;
use Illuminate\Support\Facades\Log;

class BotFormService
{
    /**
     * Начать заполнение формы консультации
     */
    public function startConsultationForm(int $botId, int $telegramUserId): void
    {
        $botUser = BotUser::where('bot_id', $botId)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($botUser) {
            $stateData = $botUser->state_data ?? [];
            $stateData['consultation'] = [];
            
            $botUser->update([
                'current_state' => BotStates::CONSULTATION_FORM_NAME,
                'state_data' => $stateData,
            ]);
        }
    }

    /**
     * Валидация поля формы
     */
    public function validateFormField(string $field, string $value, array $botSettings): array
    {
        $errors = [];
        $otherSettings = $botSettings['other_settings'] ?? [];
        
        switch ($field) {
            case 'name':
                if (empty(trim($value))) {
                    $errors[] = 'Имя не может быть пустым';
                } elseif (strlen(trim($value)) < 2) {
                    $errors[] = 'Имя должно содержать минимум 2 символа';
                } elseif (strlen($value) > 255) {
                    $errors[] = 'Имя слишком длинное (максимум 255 символов)';
                } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-\.]+$/u', $value)) {
                    $errors[] = 'Имя может содержать только буквы, пробелы, дефисы и точки';
                }
                break;
                
            case 'phone':
                $phoneValidationStrict = $otherSettings['phone_validation_strict'] ?? false;
                
                if (empty(trim($value))) {
                    $errors[] = 'Телефон не может быть пустым';
                } elseif (strlen($value) > 50) {
                    $errors[] = 'Телефон слишком длинный';
                } elseif ($phoneValidationStrict) {
                    // Строгая валидация
                    $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);
                    if (!preg_match('/^(\+7|8)[0-9]{10}$/', $cleaned)) {
                        $errors[] = 'Телефон должен быть в формате: +7XXXXXXXXXX или 8XXXXXXXXXX';
                    }
                } else {
                    // Мягкая валидация - проверяем наличие цифр
                    if (!preg_match('/[0-9]/', $value)) {
                        $errors[] = 'Телефон должен содержать хотя бы одну цифру';
                    }
                }
                break;
                
            case 'description':
                $maxLength = $otherSettings['max_description_length'] ?? 1000;
                
                if (strlen($value) > $maxLength) {
                    $errors[] = "Описание не должно превышать {$maxLength} символов";
                }
                // Описание опционально, поэтому пустое значение допустимо
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Сохранить поле формы
     */
    public function saveFormField(int $botId, int $telegramUserId, string $field, string $value): void
    {
        $botUser = BotUser::where('bot_id', $botId)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($botUser) {
            $stateData = $botUser->state_data ?? [];
            if (!isset($stateData['consultation'])) {
                $stateData['consultation'] = [];
            }
            
            $stateData['consultation'][$field] = $this->sanitizeInput($value, $field);
            
            $botUser->update([
                'state_data' => $stateData,
            ]);
        }
    }

    /**
     * Получить данные формы
     */
    public function getFormData(int $botId, int $telegramUserId): array
    {
        $botUser = BotUser::where('bot_id', $botId)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($botUser && isset($botUser->state_data['consultation'])) {
            return $botUser->state_data['consultation'];
        }

        return [];
    }

    /**
     * Отправить форму консультации
     */
    public function submitConsultationForm(int $botId, int $telegramUserId): BotConsultation
    {
        $formData = $this->getFormData($botId, $telegramUserId);
        
        $consultation = BotConsultation::create([
            'bot_id' => $botId,
            'telegram_user_id' => $telegramUserId,
            'name' => $formData['name'] ?? '',
            'phone' => $formData['phone'] ?? '',
            'description' => $formData['description'] ?? null,
            'status' => 'new',
        ]);

        // Очищаем данные формы из state_data
        $botUser = BotUser::where('bot_id', $botId)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($botUser) {
            $stateData = $botUser->state_data ?? [];
            unset($stateData['consultation']);
            
            $botUser->update([
                'state_data' => $stateData,
            ]);
        }

        return $consultation;
    }

    /**
     * Очистка входных данных
     */
    public function sanitizeInput(string $value, string $field): string
    {
        $value = trim($value);
        
        switch ($field) {
            case 'name':
                // Удаляем множественные пробелы
                $value = preg_replace('/\s+/', ' ', $value);
                break;
                
            case 'phone':
                // Удаляем все кроме цифр, +, -, (, )
                $value = preg_replace('/[^\d\+\-\(\)\s]/', '', $value);
                break;
                
            case 'description':
                // Удаляем HTML теги
                $value = strip_tags($value);
                break;
        }
        
        return $value;
    }
}

