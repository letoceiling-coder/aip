# Руководство: Валидация данных в боте

## 📋 Обзор

Валидация данных выполняется на двух уровнях:
1. **Backend (Laravel)** - серверная валидация через FormRequest классы
2. **Frontend (Vue)** - клиентская валидация для улучшения UX
3. **Telegram Bot** - валидация данных от пользователей бота

---

## 🔧 Валидация настроек бота (Админ-панель)

### Request класс: `UpdateBotSettingsRequest`

**Файл**: `app/Http/Requests/UpdateBotSettingsRequest.php`

#### Правила валидации:

```php
public function rules(): array
{
    return [
        // Канал для подписки
        'required_channel_id' => [
            'nullable',
            'integer',
            'min:-999999999999999', // Минимальное значение для Telegram ID
            'max:999999999999999',  // Максимальное значение
        ],
        'required_channel_username' => [
            'nullable',
            'string',
            'max:255',
            'regex:/^[a-zA-Z0-9_]+$/', // Только латиница, цифры, подчёркивание, без @
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
            'regex:/^https:\/\/yandex\.ru\/maps/', // Должна начинаться с yandex.ru/maps
        ],
        
        // Приветственное сообщение
        'welcome_message' => [
            'nullable',
            'string',
            'max:4096', // Лимит Telegram
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
```

#### Сообщения об ошибках:

```php
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
```

---

## 👤 Валидация пользовательских полей (Форма консультации)

### Request класс: `StoreConsultationRequest`

**Файл**: `app/Http/Requests/StoreConsultationRequest.php`

#### Правила валидации:

```php
public function rules(): array
{
    $bot = $this->route('bot'); // Получаем бота из роута
    $settings = $bot->settings ?? [];
    $otherSettings = $settings['other_settings'] ?? [];
    
    $maxDescriptionLength = $otherSettings['max_description_length'] ?? 1000;
    $phoneValidationStrict = $otherSettings['phone_validation_strict'] ?? false;
    
    $rules = [
        'name' => [
            'required',
            'string',
            'min:2',
            'max:255',
            'regex:/^[а-яА-ЯёЁa-zA-Z\s\-\.]+$/u', // Только буквы, пробелы, дефисы, точки
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
        $rules['phone'][] = 'regex:/^(\+7|8)[0-9]{10}$/'; // +7XXXXXXXXXX или 8XXXXXXXXXX
    } else {
        // Мягкая валидация - проверяем наличие цифр
        $rules['phone'][] = 'regex:/[0-9]/';
    }
    
    return $rules;
}
```

#### Сообщения об ошибках:

```php
public function messages(): array
{
    $bot = $this->route('bot');
    $settings = $bot->settings ?? [];
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
```

---

## 🤖 Валидация в Telegram боте

### Валидация данных от пользователя

Валидация выполняется в `BotFormService` перед сохранением данных:

```php
class BotFormService
{
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
                    if (!preg_match('/^(\+7|8)[0-9]{10}$/', preg_replace('/[\s\-\(\)]/', '', $value))) {
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
}
```

### Обработка ошибок валидации в боте

При ошибке валидации бот отправляет пользователю понятное сообщение:

```php
$validation = $this->formService->validateFormField('name', $userInput, $bot->settings);

if (!$validation['valid']) {
    $errorMessage = "❌ " . implode("\n", $validation['errors']);
    $this->telegram->sendMessage(
        $bot->token,
        $userId,
        $errorMessage . "\n\nПожалуйста, введите данные заново."
    );
    return; // Не переходим к следующему шагу
}
```

---

## 🎨 Валидация на Frontend (Vue)

### Компонент формы настроек

**Файл**: `resources/js/components/bot/BotSettingsForm.vue`

```javascript
const validateSettings = () => {
    const errors = {}
    
    // Валидация ID канала
    if (form.value.required_channel_id) {
        const channelId = parseInt(form.value.required_channel_id)
        if (isNaN(channelId) || channelId < -999999999999999 || channelId > 999999999999999) {
            errors.required_channel_id = 'ID канала должен быть числом от -999999999999999 до 999999999999999'
        }
    }
    
    // Валидация username канала
    if (form.value.required_channel_username) {
        if (!/^[a-zA-Z0-9_]+$/.test(form.value.required_channel_username)) {
            errors.required_channel_username = 'Username может содержать только латинские буквы, цифры и подчёркивание (без @)'
        }
    }
    
    // Валидация URL Яндекс Карт
    if (form.value.yandex_maps_url) {
        try {
            const url = new URL(form.value.yandex_maps_url)
            if (!url.hostname.includes('yandex.ru') || !url.pathname.includes('/maps')) {
                errors.yandex_maps_url = 'Ссылка должна вести на Яндекс Карты'
            }
        } catch (e) {
            errors.yandex_maps_url = 'Некорректный URL'
        }
    }
    
    // Валидация приветственного сообщения
    if (form.value.welcome_message && form.value.welcome_message.length > 4096) {
        errors.welcome_message = 'Приветственное сообщение не должно превышать 4096 символов'
    }
    
    // Валидация ID администраторов
    if (form.value.admin_telegram_ids && Array.isArray(form.value.admin_telegram_ids)) {
        form.value.admin_telegram_ids.forEach((id, index) => {
            const numId = parseInt(id)
            if (isNaN(numId) || numId < 1) {
                errors[`admin_telegram_ids.${index}`] = 'ID администратора должен быть положительным числом'
            }
        })
    }
    
    return errors
}
```

### Компонент формы консультации (в боте)

Валидация выполняется перед отправкой каждого поля:

```javascript
// Валидация имени
const validateName = (name) => {
    if (!name || name.trim().length < 2) {
        return 'Имя должно содержать минимум 2 символа'
    }
    if (name.length > 255) {
        return 'Имя слишком длинное'
    }
    if (!/^[а-яА-ЯёЁa-zA-Z\s\-\.]+$/u.test(name)) {
        return 'Имя может содержать только буквы, пробелы, дефисы и точки'
    }
    return null
}

// Валидация телефона
const validatePhone = (phone, strict = false) => {
    if (!phone || !phone.trim()) {
        return 'Телефон обязателен для заполнения'
    }
    if (phone.length > 50) {
        return 'Телефон слишком длинный'
    }
    
    if (strict) {
        const cleaned = phone.replace(/[\s\-\(\)]/g, '')
        if (!/^(\+7|8)[0-9]{10}$/.test(cleaned)) {
            return 'Телефон должен быть в формате: +7XXXXXXXXXX или 8XXXXXXXXXX'
        }
    } else {
        if (!/[0-9]/.test(phone)) {
            return 'Телефон должен содержать хотя бы одну цифру'
        }
    }
    
    return null
}

// Валидация описания
const validateDescription = (description, maxLength = 1000) => {
    if (description && description.length > maxLength) {
        return `Описание не должно превышать ${maxLength} символов`
    }
    return null
}
```

---

## ✅ Чеклист валидации

### Настройки бота:
- [x] ID канала - число, диапазон значений
- [x] Username канала - формат (латиница, цифры, подчёркивание)
- [x] ID администраторов - массив чисел
- [x] URL Яндекс Карт - валидный URL, домен yandex.ru/maps
- [x] Приветственное сообщение - максимум 4096 символов
- [x] Тексты сообщений - максимум длины для каждого поля
- [x] Дополнительные настройки - диапазоны значений

### Форма консультации:
- [x] Имя - обязательное, минимум 2 символа, максимум 255, только буквы
- [x] Телефон - обязательное, максимум 50 символов, формат (строгий/мягкий)
- [x] Описание - опциональное, максимум настраиваемый

---

## 🔍 Дополнительные проверки

### Проверка канала

Отдельный endpoint для проверки доступности канала:

```php
public function checkChannel(Request $request, string $botId)
{
    $bot = Bot::findOrFail($botId);
    $channelId = $request->input('channel_id');
    $channelUsername = $request->input('channel_username');
    
    // Валидация входных данных
    $validator = Validator::make($request->all(), [
        'channel_id' => 'nullable|integer',
        'channel_username' => 'nullable|string|regex:/^[a-zA-Z0-9_]+$/',
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }
    
    // Проверка через Telegram API
    // ...
}
```

### Санитизация данных

Перед сохранением все данные санитизируются:

```php
// Очистка имени от лишних пробелов
$name = trim($name);
$name = preg_replace('/\s+/', ' ', $name); // Множественные пробелы в один

// Очистка телефона
$phone = preg_replace('/[^\d\+\-\(\)\s]/', '', $phone); // Только цифры и допустимые символы

// Очистка описания
$description = trim($description);
$description = strip_tags($description); // Удаление HTML тегов
```

---

## 📝 Примеры сообщений об ошибках для пользователя

### Валидация имени:
- "❌ Имя должно содержать минимум 2 символа"
- "❌ Имя может содержать только буквы, пробелы, дефисы и точки"

### Валидация телефона (мягкая):
- "❌ Телефон должен содержать хотя бы одну цифру"
- "❌ Телефон слишком длинный (максимум 50 символов)"

### Валидация телефона (строгая):
- "❌ Телефон должен быть в формате: +7XXXXXXXXXX или 8XXXXXXXXXX"

### Валидация описания:
- "❌ Описание не должно превышать 1000 символов"

---

**Дата создания**: 2025-01-15  
**Версия**: 1.0


