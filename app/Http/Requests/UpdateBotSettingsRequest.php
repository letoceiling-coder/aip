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
            
            // Тексты сообщений
            'messages' => [
                'nullable',
                'array',
            ],
            'messages.subscription' => ['nullable', 'array'],
            'messages.subscription.required_text' => ['nullable', 'string', 'max:1000'],
            'messages.subscription.subscribe_button' => ['nullable', 'string', 'max:100'],
            'messages.subscription.check_button' => ['nullable', 'string', 'max:100'],
            
            'messages.consultation' => ['nullable', 'array'],
            'messages.consultation.description' => ['nullable', 'string', 'max:2000'],
            'messages.consultation.form_name_label' => ['nullable', 'string', 'max:200'],
            'messages.consultation.form_phone_label' => ['nullable', 'string', 'max:200'],
            'messages.consultation.form_description_label' => ['nullable', 'string', 'max:300'],
            'messages.consultation.thank_you' => ['nullable', 'string', 'max:500'],
            'messages.consultation.start_button' => ['nullable', 'string', 'max:100'],
            'messages.consultation.skip_description_button' => ['nullable', 'string', 'max:100'],
            
            'messages.materials' => ['nullable', 'array'],
            'messages.materials.list_description' => ['nullable', 'string', 'max:2000'],
            'messages.materials.download_button' => ['nullable', 'string', 'max:100'],
            'messages.materials.back_to_list' => ['nullable', 'string', 'max:100'],
            
            'messages.menu' => ['nullable', 'array'],
            'messages.menu.materials_button' => ['nullable', 'string', 'max:100'],
            'messages.menu.consultation_button' => ['nullable', 'string', 'max:100'],
            'messages.menu.review_button' => ['nullable', 'string', 'max:100'],
            'messages.menu.back_to_menu' => ['nullable', 'string', 'max:100'],
            
            'messages.notifications' => ['nullable', 'array'],
            'messages.notifications.consultation_template' => ['nullable', 'string', 'max:2000'],
            
            // Дополнительные настройки
            'other_settings' => ['nullable', 'array'],
            'other_settings.phone_validation_strict' => ['nullable', 'boolean'],
            'other_settings.max_description_length' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'other_settings.subscription_check_timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
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
            'messages.*.max' => 'Текст сообщения слишком длинный',
            'other_settings.max_description_length.min' => 'Минимальная длина описания: 10 символов',
            'other_settings.max_description_length.max' => 'Максимальная длина описания: 5000 символов',
            'other_settings.subscription_check_timeout.min' => 'Минимальный таймаут: 1 секунда',
            'other_settings.subscription_check_timeout.max' => 'Максимальный таймаут: 30 секунд',
        ];
    }
}
