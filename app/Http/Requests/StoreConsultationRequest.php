<?php

namespace App\Http\Requests;

use App\Models\Bot;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
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
        $botId = $this->route('botId');
        $bot = Bot::find($botId);
        
        $settings = $bot?->settings ?? [];
        $otherSettings = $settings['other_settings'] ?? [];
        
        $maxDescriptionLength = $otherSettings['max_description_length'] ?? 1000;
        $phoneValidationStrict = $otherSettings['phone_validation_strict'] ?? false;
        
        $rules = [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[а-яА-ЯёЁa-zA-Z\s\-\.]+$/u',
            ],
            'phone' => [
                'required',
                'string',
                'max:50',
            ],
            'description' => [
                'nullable',
                'string',
                "max:{$maxDescriptionLength}",
            ],
        ];
        
        // Строгая валидация телефона (если включена)
        if ($phoneValidationStrict) {
            $rules['phone'][] = 'regex:/^(\+7|8)[0-9]{10}$/';
        } else {
            // Мягкая валидация - проверяем наличие цифр
            $rules['phone'][] = 'regex:/[0-9]/';
        }
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $botId = $this->route('botId');
        $bot = Bot::find($botId);
        
        $settings = $bot?->settings ?? [];
        $otherSettings = $settings['other_settings'] ?? [];
        $maxDescriptionLength = $otherSettings['max_description_length'] ?? 1000;
        $phoneValidationStrict = $otherSettings['phone_validation_strict'] ?? false;
        
        $messages = [
            'name.required' => 'Поле "Имя" обязательно для заполнения',
            'name.min' => 'Имя должно содержать минимум 2 символа',
            'name.max' => 'Имя не должно превышать 255 символов',
            'name.regex' => 'Имя может содержать только буквы, пробелы, дефисы и точки',
            'phone.required' => 'Поле "Телефон" обязательно для заполнения',
            'phone.max' => 'Телефон не должен превышать 50 символов',
            'description.max' => "Описание не должно превышать {$maxDescriptionLength} символов",
        ];
        
        if ($phoneValidationStrict) {
            $messages['phone.regex'] = 'Телефон должен быть в формате: +7XXXXXXXXXX или 8XXXXXXXXXX';
        } else {
            $messages['phone.regex'] = 'Телефон должен содержать хотя бы одну цифру';
        }
        
        return $messages;
    }
}
