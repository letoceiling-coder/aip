<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBotSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Преобразуем массивы в строки для полей messages
        if ($this->has('settings.messages')) {
            $settings = $this->input('settings', []);
            $messages = $settings['messages'] ?? [];
            
            // Рекурсивно обрабатываем все поля messages
            $messages = $this->normalizeMessages($messages);
            
            $settings['messages'] = $messages;
            $this->merge(['settings' => $settings]);
        }
    }

    /**
     * Нормализовать значения messages (преобразовать массивы в строки)
     */
    protected function normalizeMessages(array $messages): array
    {
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                // Если массив содержит только индексы 0 и 1 с null - это массив из null
                if ($this->isArrayOfNulls($value)) {
                    $messages[$key] = '';
                } elseif ($this->isNumericArray($value)) {
                    // Если это числовой массив (например, [null, null] или ['text', 'text'])
                    // Берем первое не-null строковое значение
                    $stringValue = null;
                    foreach ($value as $item) {
                        if (is_string($item) && $item !== '') {
                            $stringValue = $item;
                            break;
                        }
                    }
                    $messages[$key] = $stringValue ?? '';
                } else {
                    // Если это ассоциативный массив, обрабатываем рекурсивно
                    $messages[$key] = $this->normalizeMessages($value);
                }
            } elseif (is_null($value)) {
                $messages[$key] = '';
            }
        }
        
        return $messages;
    }

    /**
     * Проверить, является ли массив массивом из null значений
     */
    protected function isArrayOfNulls(array $array): bool
    {
        foreach ($array as $item) {
            if ($item !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Проверить, является ли массив числовым (индексированным)
     */
    protected function isNumericArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Канал для подписки
            'required_channel_id' => [
                'nullable',
                'integer',
                'min:-999999999999999',
                'max:999999999999999',
            ],
            'required_channel_username' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            
            // Администраторы
            'admin_telegram_ids' => [
                'nullable',
                'array',
            ],
            'admin_telegram_ids.*' => [
                'required',
                'integer',
                'min:1',
            ],
            
            // Яндекс Карты
            'yandex_maps_url' => [
                'nullable',
                'url',
                'max:500',
                'regex:/^https:\/\/yandex\.ru\/maps/',
            ],
            
            // Приветственное сообщение
            'welcome_message' => [
                'nullable',
                'string',
                'max:4096',
            ],
            
            // Settings (обертка для messages и other_settings)
            'settings' => [
                'nullable',
                'array',
            ],
            'settings.messages' => [
                'nullable',
                'array',
            ],
            'settings.messages.subscription' => ['nullable', 'array'],
            'settings.messages.subscription.required_text' => ['nullable', 'string', 'max:1000'],
            'settings.messages.subscription.subscribe_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.subscription.check_button' => ['nullable', 'string', 'max:100'],
            
            'settings.messages.consultation' => ['nullable', 'array'],
            'settings.messages.consultation.description' => ['nullable', 'string', 'max:2000'],
            'settings.messages.consultation.form_name_label' => ['nullable', 'string', 'max:200'],
            'settings.messages.consultation.form_phone_label' => ['nullable', 'string', 'max:200'],
            'settings.messages.consultation.form_description_label' => ['nullable', 'string', 'max:300'],
            'settings.messages.consultation.thank_you' => ['nullable', 'string', 'max:500'],
            'settings.messages.consultation.start_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.consultation.skip_description_button' => ['nullable', 'string', 'max:100'],
            
            'settings.messages.materials' => ['nullable', 'array'],
            'settings.messages.materials.list_description' => ['nullable', 'string', 'max:2000'],
            'settings.messages.materials.download_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.materials.back_to_list' => ['nullable', 'string', 'max:100'],
            
            'settings.messages.menu' => ['nullable', 'array'],
            'settings.messages.menu.materials_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.menu.consultation_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.menu.review_button' => ['nullable', 'string', 'max:100'],
            'settings.messages.menu.back_to_menu' => ['nullable', 'string', 'max:100'],
            
            'settings.messages.notifications' => ['nullable', 'array'],
            'settings.messages.notifications.consultation_template' => ['nullable', 'string', 'max:2000'],
            
            // Welcome media
            'settings.welcome_media' => ['nullable', 'array'],
            'settings.welcome_media.type' => ['nullable', 'string', 'in:photo,video,gallery'],
            'settings.welcome_media.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'settings.welcome_media.gallery' => ['nullable', 'array'],
            'settings.welcome_media.gallery.*' => ['nullable', 'integer', 'exists:media,id'],
            
            // Presentation
            'settings.presentation' => ['nullable', 'array'],
            'settings.presentation.media_id' => ['nullable', 'integer', 'exists:media,id'],
            
            // Reply buttons
            'settings.reply_buttons' => ['nullable', 'array'],
            'settings.reply_buttons.materials_button_text' => ['nullable', 'string', 'max:100'],
            'settings.reply_buttons.materials_files' => ['nullable', 'array'],
            'settings.reply_buttons.materials_files.*' => ['nullable', 'integer', 'exists:media,id'],
            'settings.reply_buttons.consultation_button_text' => ['nullable', 'string', 'max:100'],
            'settings.reply_buttons.office_button_text' => ['nullable', 'string', 'max:100'],
            
            // Office location
            'settings.office_location' => ['nullable', 'array'],
            'settings.office_location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'settings.office_location.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'settings.office_location.address' => ['nullable', 'string', 'max:500'],
            
            // Дополнительные настройки
            'settings.other_settings' => ['nullable', 'array'],
            'settings.other_settings.phone_validation_strict' => ['nullable', 'boolean'],
            'settings.other_settings.max_description_length' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'settings.other_settings.subscription_check_timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'required_channel_id.integer' => 'ID канала должен быть числом',
            'required_channel_id.min' => 'ID канала некорректный',
            'required_channel_username.regex' => 'Username канала может содержать только латинские буквы, цифры и подчёркивание (без символа @)',
            'admin_telegram_ids.array' => 'ID администраторов должны быть в виде массива',
            'admin_telegram_ids.*.integer' => 'ID администратора должен быть числом',
            'yandex_maps_url.url' => 'Некорректный URL',
            'yandex_maps_url.regex' => 'Ссылка должна вести на Яндекс Карты',
            'welcome_message.max' => 'Приветственное сообщение не должно превышать 4096 символов',
            'settings.messages.*.*.max' => 'Текст сообщения слишком длинный',
            'settings.other_settings.max_description_length.min' => 'Минимальная длина описания: 10 символов',
            'settings.other_settings.max_description_length.max' => 'Максимальная длина описания: 5000 символов',
            'settings.other_settings.subscription_check_timeout.min' => 'Минимальный таймаут: 1 секунда',
            'settings.other_settings.subscription_check_timeout.max' => 'Максимальный таймаут: 30 секунд',
        ];
    }
}
