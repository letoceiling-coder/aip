<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
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
        $rules = [
            'category_id' => [
                'required',
                'integer',
                'exists:bot_material_categories,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'file_type' => [
                'required',
                'string',
                Rule::in(['file', 'url', 'document', 'telegram_file_id']),
            ],
            'order_index' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];

        // Валидация в зависимости от типа файла
        $fileType = $this->input('file_type');
        
        if ($fileType === 'file') {
            // Можно загрузить файл ИЛИ указать media_id
            $rules['file'] = ['nullable', 'file', 'max:51200']; // 50MB
            $rules['media_id'] = ['nullable', 'integer', 'exists:media,id'];
        } elseif ($fileType === 'url') {
            $rules['file_url'] = ['required', 'url', 'max:500'];
        } elseif ($fileType === 'telegram_file_id') {
            $rules['file_id'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Категория обязательна',
            'category_id.exists' => 'Категория не найдена',
            'title.required' => 'Название материала обязательно',
            'title.max' => 'Название материала не должно превышать 255 символов',
            'file_type.required' => 'Тип файла обязателен',
            'file_type.in' => 'Некорректный тип файла',
            'file.required' => 'Файл обязателен для загрузки',
            'file.max' => 'Размер файла не должен превышать 50 МБ',
            'media_id.exists' => 'Файл из медиа-библиотеки не найден',
            'file_url.required' => 'URL файла обязателен',
            'file_url.url' => 'Некорректный URL',
            'file_id.required' => 'Telegram file_id обязателен',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fileType = $this->input('file_type');
            
            // Для типа 'file' должен быть указан либо file, либо media_id
            if ($fileType === 'file') {
                $hasFile = $this->hasFile('file');
                $hasMediaId = $this->filled('media_id');
                
                if (!$hasFile && !$hasMediaId) {
                    $validator->errors()->add('file', 'Необходимо загрузить файл или указать файл из медиа-библиотеки');
                }
            }
        });
    }
}
