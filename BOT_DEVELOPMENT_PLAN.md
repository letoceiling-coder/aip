# План разработки Telegram-бота для АИП

## 📋 Оглавление

1. [Обзор проекта](#обзор-проекта)
2. [Архитектура решения](#архитектура-решения)
3. [Структура базы данных](#структура-базы-данных)
4. [Карта бота (FSM)](#карта-бота-fsm)
5. [Пошаговый план разработки](#пошаговый-план-разработки)
6. [API для админ-панели](#api-для-админ-панели)
7. [Система логирования](#система-логирования)
8. [Сбор и запись данных](#сбор-и-запись-данных)

---

## 🎯 Обзор проекта

### Цель
Разработка Telegram-чат-бота для Аудиторско-консалтинговой группы «АИП» (бета-версия):
- Сбор заявок на консультации
- Выдача полезных материалов (чек-листы)
- Проверка подписки на Telegram-канал
- Тестирование пользовательских сценариев

### Технологический стек
- **Backend**: Laravel 11, PHP 8.2+
- **Database**: MySQL/PostgreSQL
- **Telegram SDK**: `letoceiling-coder/telegram` (уже установлен)
- **Admin Panel**: Vue 3 + TypeScript
- **API**: RESTful API с версионированием

### Основные требования из bot.md
1. Обязательная подписка на Telegram-канал перед доступом
2. Главное меню с разделами:
   - Полезные материалы и договоры (8 направлений)
   - Запись на консультацию (форма с 3 полями)
   - Ссылка на Яндекс Карты для отзывов
3. Админ-панель для:
   - Просмотра заявок
   - Фильтрации по дате
   - Управления статусами
   - Telegram-уведомлений администраторам

---

## 🏗 Архитектура решения

### Общая структура

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── BotController.php (существующий - управление ботами)
│           └── BotInteractionController.php (новый - обработка взаимодействий)
├── Models/
│   ├── Bot.php (существующий)
│   ├── BotUser.php (новый - пользователи бота)
│   ├── BotSubscription.php (новый - проверка подписок)
│   ├── BotConsultation.php (новый - заявки на консультацию)
│   ├── BotMaterial.php (новый - материалы для скачивания)
│   └── BotMaterialCategory.php (новый - категории материалов)
├── Services/
│   ├── TelegramService.php (существующий - базовые методы)
│   ├── BotHandlerService.php (новый - основная логика бота)
│   ├── BotSubscriptionService.php (новый - проверка подписок)
│   ├── BotMenuService.php (новый - генерация меню)
│   ├── BotFormService.php (новый - обработка форм)
│   ├── BotMaterialService.php (новый - работа с материалами)
│   └── BotLoggerService.php (новый - логирование)
└── Console/
    └── Commands/
        └── BotWebhookCommand.php (опционально - для long polling)

database/migrations/
├── 2025_XX_XX_create_bot_users_table.php
├── 2025_XX_XX_create_bot_subscriptions_table.php
├── 2025_XX_XX_create_bot_material_categories_table.php
├── 2025_XX_XX_create_bot_materials_table.php
└── 2025_XX_XX_create_bot_consultations_table.php

resources/js/pages/admin/
└── BotManagement.vue (новый - управление настройками бота и заявками)
```

### Принципы архитектуры

1. **Модульность**: Каждый сервис отвечает за свою область
2. **Гибкость**: Настройки бота хранятся в JSON (поле `settings` таблицы `bots`)
3. **Расширяемость**: Легко добавлять новые команды и разделы
4. **Масштабируемость**: Поддержка нескольких ботов через таблицу `bots`
5. **Логирование**: Все действия логируются для анализа

---

## 💾 Структура базы данных

### 1. bot_users (Пользователи бота)

```sql
CREATE TABLE bot_users (
    id BIGINT UNSIGNED PRIMARY KEY,  -- Telegram user ID
    bot_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(255) NULLABLE,
    first_name VARCHAR(255) NULLABLE,
    last_name VARCHAR(255) NULLABLE,
    language_code VARCHAR(10) NULLABLE,
    is_subscribed BOOLEAN DEFAULT FALSE,  -- Подписан на обязательный канал
    subscription_checked_at TIMESTAMP NULLABLE,
    current_state VARCHAR(255) NULLABLE,  -- Текущее состояние FSM
    state_data JSON NULLABLE,  -- Данные состояния (например, заполняемая форма)
    last_interaction_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    INDEX idx_bot_user (bot_id, id),
    INDEX idx_subscription (bot_id, is_subscribed)
);
```

### 2. bot_subscriptions (История проверок подписок)

```sql
CREATE TABLE bot_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT NOT NULL,  -- Telegram channel ID (может быть отрицательным)
    channel_username VARCHAR(255) NULLABLE,
    is_subscribed BOOLEAN DEFAULT FALSE,
    checked_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES bot_users(id) ON DELETE CASCADE,
    INDEX idx_user_check (bot_id, user_id, checked_at)
);
```

### 3. bot_material_categories (Категории материалов)

```sql
CREATE TABLE bot_material_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,  -- Название: "Структурирование", "Партнёрство", etc.
    description TEXT NULLABLE,  -- Описание категории
    order_index INT DEFAULT 0,  -- Порядок отображения
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    INDEX idx_bot_order (bot_id, order_index)
);
```

### 4. bot_materials (Материалы для скачивания)

```sql
CREATE TABLE bot_materials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULLABLE,
    file_type ENUM('file', 'url', 'document') DEFAULT 'file',
    file_path VARCHAR(500) NULLABLE,  -- Путь к файлу в storage
    file_url VARCHAR(500) NULLABLE,  -- Внешняя ссылка
    file_id VARCHAR(255) NULLABLE,  -- Telegram file_id (если файл отправлен в Telegram)
    order_index INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES bot_material_categories(id) ON DELETE CASCADE,
    INDEX idx_category_order (category_id, order_index)
);
```

### 5. bot_consultations (Заявки на консультацию)

```sql
CREATE TABLE bot_consultations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    description TEXT NULLABLE,  -- Краткое описание запроса
    status ENUM('new', 'in_progress', 'closed') DEFAULT 'new',
    admin_notes TEXT NULLABLE,
    telegram_notified BOOLEAN DEFAULT FALSE,  -- Уведомление отправлено админу
    telegram_notified_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES bot_users(id) ON DELETE CASCADE,
    INDEX idx_bot_status (bot_id, status, created_at),
    INDEX idx_user (user_id, created_at)
);
```

### 6. bot_materials_media (Связь материалов с медиа-библиотекой) - опционально

**Примечание**: Вместо отдельной таблицы используется связь через `bot_materials.media_id` → `media.id`

### 7. bot_logs (Логи взаимодействий) - опционально для детального анализа

```sql
CREATE TABLE bot_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULLABLE,
    update_id BIGINT NULLABLE,  -- Telegram update_id
    event_type VARCHAR(50) NOT NULL,  -- 'message', 'callback_query', 'subscription_check', etc.
    action VARCHAR(100) NULLABLE,  -- 'start', 'menu_main', 'consultation_form', etc.
    data JSON NULLABLE,  -- Дополнительные данные события
    response_status VARCHAR(50) NULLABLE,  -- 'success', 'error', 'skipped'
    error_message TEXT NULLABLE,
    created_at TIMESTAMP,
    
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES bot_users(id) ON DELETE SET NULL,
    INDEX idx_bot_event (bot_id, event_type, created_at),
    INDEX idx_user (user_id, created_at)
);
```

### Обновление таблицы bots

Добавить поля в таблицу `bots` (через миграцию):

```sql
ALTER TABLE bots ADD COLUMN required_channel_id BIGINT NULLABLE;
ALTER TABLE bots ADD COLUMN required_channel_username VARCHAR(255) NULLABLE;
ALTER TABLE bots ADD COLUMN admin_telegram_ids JSON NULLABLE;  -- [123456789, 987654321]
ALTER TABLE bots ADD COLUMN yandex_maps_url VARCHAR(500) NULLABLE;
```

**Примечание**: Текстовые сообщения бота хранятся в JSON поле `settings` под ключом `messages` для гибкой настройки всех текстов через админ-панель.

### Структура settings JSON

```json
{
    "messages": {
        "subscription": {
            "required_text": "Для доступа к бета-версии необходимо подписаться на наш официальный Telegram-канал.",
            "subscribe_button": "🔔 Подписаться на Telegram",
            "check_button": "✅ Я подписался"
        },
        "consultation": {
            "description": "Если вашему бизнесу нужна профессиональная юридическая поддержка,\nАИП возьмёт на себя все правовые вопросы.\n\nОбращаясь к нам, вы сосредотачиваетесь на развитии бизнеса,\nа не на юридических рисках.",
            "form_name_label": "Введите ваше имя:",
            "form_phone_label": "Введите ваш телефон:",
            "form_description_label": "Краткое описание запроса (опционально, можете пропустить):",
            "thank_you": "Спасибо.\nМы свяжемся с вами в ближайшее время.",
            "start_button": "📝 Записаться на консультацию",
            "skip_description_button": "Пропустить"
        },
        "materials": {
            "list_description": "Мы подготовили материалы по ключевым направлениям нашей работы,\nсобранные многолетним опытом.\n\nОни помогут увидеть риски и понять,\nгде бизнес уязвим.",
            "download_button": "⬇️ Скачать материалы",
            "back_to_list": "⬅️ Назад"
        },
        "menu": {
            "materials_button": "📂 Полезные материалы и договоры",
            "consultation_button": "📞 Записаться на консультацию",
            "review_button": "⭐ Оставить отзыв на Яндекс Картах",
            "back_to_menu": "⬅️ Назад в меню"
        },
        "notifications": {
            "consultation_template": "Новая заявка на консультацию\n\nИмя: {name}\nТелефон: {phone}\nОписание: {description}\nДата: {date}"
        }
    },
    "webhook": {
        "allowed_updates": ["message", "callback_query"],
        "max_connections": 40,
        "secret_token": null
    },
    "other_settings": {
        "phone_validation_strict": false,
        "subscription_check_timeout": 5,
        "max_description_length": 1000
    }
}
```

**Преимущества такого подхода:**
- Все тексты настраиваются через админ-панель
- Не нужно изменять код для изменения текстов
- Легко добавлять новые текстовые настройки
- Поддержка шаблонов с переменными (для уведомлений)

---

## 🗺 Карта бота (FSM - Finite State Machine)

### Состояния пользователя

```
┌─────────────────┐
│   START/IDLE    │ ← Начальное состояние
└────────┬────────┘
         │
         ├─ /start → CHECK_SUBSCRIPTION
         │
         ▼
┌─────────────────────┐
│ CHECK_SUBSCRIPTION  │ ← Проверка подписки на канал
└────────┬────────────┘
         │
         ├─ не подписан → SHOW_SUBSCRIBE_SCREEN
         ├─ подписан → MAIN_MENU
         │
         ▼
┌─────────────────────┐
│ SHOW_SUBSCRIBE_     │ ← Экран с требованием подписки
│      SCREEN         │
└────────┬────────────┘
         │
         ├─ "Я подписался" → CHECK_SUBSCRIPTION (повторная проверка)
         │
┌────────▼────────────┐
│    MAIN_MENU        │ ← Главное меню
└────────┬────────────┘
         │
         ├─ "Полезные материалы" → MATERIALS_LIST
         ├─ "Записаться на консультацию" → CONSULTATION_DESCRIPTION
         ├─ "Оставить отзыв" → Открывает ссылку Яндекс Карт
         │
         ▼
┌─────────────────────┐
│  MATERIALS_LIST     │ ← Список направлений (8 категорий)
└────────┬────────────┘
         │
         ├─ Выбор категории → MATERIAL_CATEGORY
         ├─ "Назад" → MAIN_MENU
         │
         ▼
┌─────────────────────┐
│ MATERIAL_CATEGORY   │ ← Карточка материала
└────────┬────────────┘
         │
         ├─ "Скачать материалы" → Отправка файла/ссылки
         ├─ "Назад" → MATERIALS_LIST
         │
┌────────▼────────────┐
│ CONSULTATION_       │ ← Описание услуги
│   DESCRIPTION       │
└────────┬────────────┘
         │
         ├─ "Записаться на консультацию" → CONSULTATION_FORM_NAME
         ├─ "Назад" → MAIN_MENU
         │
         ▼
┌─────────────────────┐
│ CONSULTATION_FORM_  │ ← Ввод имени
│       NAME          │
└────────┬────────────┘
         │
         ├─ Ввод имени → CONSULTATION_FORM_PHONE
         │
         ▼
┌─────────────────────┐
│ CONSULTATION_FORM_  │ ← Ввод телефона
│      PHONE          │
└────────┬────────────┘
         │
         ├─ Ввод телефона → CONSULTATION_FORM_DESCRIPTION
         │
         ▼
┌─────────────────────┐
│ CONSULTATION_FORM_  │ ← Ввод описания (опционально)
│    DESCRIPTION      │
└────────┬────────────┘
         │
         ├─ Ввод описания → CONSULTATION_SUBMIT
         ├─ Пропуск → CONSULTATION_SUBMIT
         │
         ▼
┌─────────────────────┐
│ CONSULTATION_SUBMIT │ ← Подтверждение и отправка
└────────┬────────────┘
         │
         ├─ Отправка → MAIN_MENU (с сообщением "Спасибо...")
```

### Коды состояний (константы)

```php
// app/Constants/BotStates.php
class BotStates
{
    const IDLE = 'idle';
    const CHECK_SUBSCRIPTION = 'check_subscription';
    const SHOW_SUBSCRIBE_SCREEN = 'show_subscribe_screen';
    const MAIN_MENU = 'main_menu';
    const MATERIALS_LIST = 'materials_list';
    const MATERIAL_CATEGORY = 'material_category';
    const CONSULTATION_DESCRIPTION = 'consultation_description';
    const CONSULTATION_FORM_NAME = 'consultation_form_name';
    const CONSULTATION_FORM_PHONE = 'consultation_form_phone';
    const CONSULTATION_FORM_DESCRIPTION = 'consultation_form_description';
    const CONSULTATION_SUBMIT = 'consultation_submit';
}
```

---

## 📝 Пошаговый план разработки

### Этап 1: Подготовка инфраструктуры (1-2 дня)

#### 1.1. Создание миграций
- [ ] Миграция для `bot_users`
- [ ] Миграция для `bot_subscriptions`
- [ ] Миграция для `bot_material_categories`
- [ ] Миграция для `bot_materials` (с полем `media_id` для связи с медиа-библиотекой)
- [ ] Миграция для `bot_consultations`
- [ ] Миграция для `bot_logs` (опционально)
- [ ] Миграция для обновления таблицы `bots` (required_channel_id, admin_telegram_ids, etc.)

#### 1.2. Создание моделей
- [ ] `app/Models/BotUser.php`
- [ ] `app/Models/BotSubscription.php`
- [ ] `app/Models/BotMaterialCategory.php`
- [ ] `app/Models/BotMaterial.php`
- [ ] `app/Models/BotConsultation.php`
- [ ] `app/Models/BotLog.php` (опционально)
- [ ] Обновить `app/Models/Bot.php` (добавить relationships)

#### 1.3. Создание констант и конфигурации
- [ ] `app/Constants/BotStates.php`
- [ ] `app/Constants/BotActions.php`
- [ ] Обновить `config/telegram.php` (добавить настройки по умолчанию)

#### 1.4. Создание Request классов для валидации
- [ ] `app/Http/Requests/UpdateBotSettingsRequest.php` - валидация настроек бота
- [ ] `app/Http/Requests/StoreConsultationRequest.php` - валидация формы консультации
- [ ] `app/Http/Requests/StoreMaterialRequest.php` - валидация создания материала
- [ ] `app/Http/Requests/UpdateMaterialRequest.php` - валидация обновления материала
- [ ] `app/Http/Requests/StoreMaterialCategoryRequest.php` - валидация категории материалов

**Детали валидации**: См. `BOT_VALIDATION_GUIDE.md` для полного описания всех правил валидации.

### Этап 2: Базовые сервисы (2-3 дня)

#### 2.1. BotSubscriptionService
**Назначение**: Проверка подписки пользователя на Telegram-канал

**Методы**:
- `checkSubscription(int $botId, int $userId, int $channelId): bool`
- `getChannelMember(int $botId, int $userId, int $channelId): array`
- `saveSubscriptionCheck(int $botId, int $userId, int $channelId, bool $isSubscribed): void`

**Логика**:
```php
// Использует Telegram API: getChatMember
// Сохраняет результат в bot_subscriptions
// Кэширует результат на 5 минут для избежания лишних запросов
```

#### 2.2. BotMenuService
**Назначение**: Генерация клавиатур и сообщений меню

**Методы**:
- `getMainMenuKeyboard(): array` - главное меню
- `getMaterialsListKeyboard(int $botId): array` - список категорий материалов
- `getMaterialCategoryKeyboard(int $categoryId): array` - карточка материала
- `getConsultationKeyboard(): array` - кнопки для консультации
- `getBackKeyboard(string $backState): array` - кнопка "Назад"

**Формат клавиатуры** (Inline Keyboard):
```php
[
    [
        ['text' => '📂 Полезные материалы', 'callback_data' => 'menu_materials'],
        ['text' => '📞 Записаться на консультацию', 'callback_data' => 'menu_consultation']
    ],
    [
        ['text' => '⭐ Оставить отзыв', 'url' => 'https://yandex.ru/maps/...']
    ]
]
```

#### 2.3. BotFormService
**Назначение**: Обработка форм (заявка на консультацию)

**Методы**:
- `startConsultationForm(int $botId, int $userId): void` - начало формы
- `validateFormField(string $field, string $value, array $botSettings): array` - валидация поля формы
- `saveFormField(int $userId, string $field, string $value): void` - сохранение поля (с валидацией)
- `submitConsultationForm(int $userId): BotConsultation` - отправка формы (с финальной валидацией)
- `getFormData(int $userId): array` - получение данных формы из state_data
- `sanitizeInput(string $value, string $field): string` - очистка входных данных

**Валидация полей**:
- **Имя**: обязательное, минимум 2 символа, максимум 255, только буквы, пробелы, дефисы, точки
- **Телефон**: обязательное, максимум 50 символов, формат зависит от настройки `phone_validation_strict`
  - Строгая: `+7XXXXXXXXXX` или `8XXXXXXXXXX`
  - Мягкая: наличие хотя бы одной цифры
- **Описание**: опциональное, максимум символов из настройки `max_description_length` (по умолчанию 1000)

**Хранение данных**:
Данные формы хранятся в `state_data` пользователя (JSON):
```json
{
    "consultation": {
        "name": "Иван Иванов",
        "phone": "+79001234567",
        "description": "Нужна консультация по налогам"
    }
}
```

**Обработка ошибок**:
При ошибке валидации бот отправляет пользователю понятное сообщение с описанием ошибки и просьбой ввести данные заново.

#### 2.4. BotMaterialService
**Назначение**: Работа с материалами

**Методы**:
- `getCategories(int $botId): Collection`
- `getCategoryMaterials(int $categoryId): Collection`
- `sendMaterial(int $botId, int $userId, int $materialId): array` - отправка файла/ссылки
- `incrementDownloadCount(int $materialId): void`
- `getMaterialFilePath(int $materialId): ?string` - получение пути к файлу
- `getMaterialFileUrl(int $materialId): ?string` - получение URL файла

**Отправка файлов через Telegram**:
- Если `file_type = 'telegram_file_id'` и `file_id` существует → используем `sendDocument` с `file_id` (самый быстрый способ)
- Если `file_type = 'file'` и `media_id` существует:
  - Получаем файл из таблицы `media`
  - Если файл был ранее отправлен в Telegram и `file_id` сохранен → используем `file_id`
  - Иначе → загружаем файл через `InputFile` по пути из `media.metadata.path`
- Если `file_type = 'file'` и `file_path` существует (старый способ) → загружаем через `InputFile`
- Если `file_type = 'url'` → отправляем сообщение со ссылкой из `file_url`

**Интеграция с медиа-библиотекой**:
- При создании материала с загрузкой файла → файл сохраняется в `media`, связь через `media_id`
- При удалении материала → файл НЕ удаляется из медиа-библиотеки (можно использовать в других местах)
- При выборе файла из медиа-библиотеки → просто указываем `media_id`

#### 2.5. BotLoggerService
**Назначение**: Логирование всех взаимодействий

**Методы**:
- `logMessage(int $botId, int $userId, array $update, string $action, ?string $error = null): void`
- `logCallbackQuery(int $botId, int $userId, array $update, string $action, ?string $error = null): void`
- `logSubscriptionCheck(int $botId, int $userId, bool $isSubscribed): void`
- `logConsultationCreated(int $botId, int $userId, int $consultationId): void`

**Логирование**:
- В таблицу `bot_logs` (если включено)
- В файл логов Laravel: `storage/logs/bot_{bot_id}.log`
- Структурированные логи (JSON) для анализа

### Этап 3: Основной обработчик (BotHandlerService) (3-4 дня)

#### 3.1. Структура класса

```php
class BotHandlerService
{
    protected TelegramService $telegram;
    protected BotSubscriptionService $subscription;
    protected BotMenuService $menu;
    protected BotFormService $form;
    protected BotMaterialService $material;
    protected BotLoggerService $logger;
    
    public function handleUpdate(Bot $bot, array $update): void
    {
        // Определяем тип обновления
        // Получаем или создаем пользователя
        // Направляем в соответствующий обработчик
    }
    
    protected function handleMessage(Bot $bot, BotUser $user, array $message): void
    protected function handleCallbackQuery(Bot $bot, BotUser $user, array $callbackQuery): void
    protected function handleCommand(Bot $bot, BotUser $user, string $command, array $message): void
    protected function handleState(Bot $bot, BotUser $user, string $state, array $message): void
}
```

#### 3.2. Обработка команды /start

```php
protected function handleStartCommand(Bot $bot, BotUser $user): void
{
    // 1. Сохраняем/обновляем пользователя
    // 2. Проверяем подписку
    // 3. Если не подписан → показываем экран подписки
    // 4. Если подписан → показываем главное меню
}
```

#### 3.3. Обработка состояний формы

```php
protected function handleConsultationForm(Bot $bot, BotUser $user, string $field, string $value): void
{
    switch ($user->current_state) {
        case BotStates::CONSULTATION_FORM_NAME:
            $this->form->saveFormField($user->id, 'name', $value);
            $this->askForPhone($bot, $user);
            break;
            
        case BotStates::CONSULTATION_FORM_PHONE:
            $this->form->saveFormField($user->id, 'phone', $value);
            $this->askForDescription($bot, $user);
            break;
            
        case BotStates::CONSULTATION_FORM_DESCRIPTION:
            $this->form->saveFormField($user->id, 'description', $value);
            $this->submitConsultation($bot, $user);
            break;
    }
}
```

#### 3.4. Обработка callback_query (кнопки)

```php
protected function handleCallbackQuery(Bot $bot, BotUser $user, array $callbackQuery): void
{
    $data = $callbackQuery['data'];
    
    // Обработка callback_data
    if (str_starts_with($data, 'menu_')) {
        $this->handleMenuAction($bot, $user, $data);
    } elseif (str_starts_with($data, 'material_category_')) {
        $categoryId = (int) str_replace('material_category_', '', $data);
        $this->showMaterialCategory($bot, $user, $categoryId);
    } elseif (str_starts_with($data, 'material_download_')) {
        $materialId = (int) str_replace('material_download_', '', $data);
        $this->sendMaterial($bot, $user, $materialId);
    } elseif ($data === 'back_main_menu') {
        $this->showMainMenu($bot, $user);
    }
    
    // Подтверждение нажатия кнопки
    $this->telegram->answerCallbackQuery($bot->token, $callbackQuery['id']);
}
```

### Этап 4: Интеграция с webhook (1 день)

#### 4.1. Обновление BotController::handleWebhook

```php
public function handleWebhook(Request $request, string $id): JsonResponse
{
    $bot = Bot::findOrFail($id);
    
    if (!$bot->is_active) {
        return response()->json(['ok' => true]); // Игнорируем неактивных ботов
    }
    
    // Проверка secret_token (если установлен)
    // ...
    
    $update = $request->all();
    
    // Логирование входящего обновления
    Log::channel('bot')->info("Bot {$bot->id} received update", [
        'update_id' => $update['update_id'] ?? null,
        'type' => $this->getUpdateType($update),
    ]);
    
    // Обработка через BotHandlerService
    try {
        $handler = app(BotHandlerService::class);
        $handler->handleUpdate($bot, $update);
    } catch (\Exception $e) {
        Log::channel('bot')->error("Error handling update for bot {$bot->id}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    return response()->json(['ok' => true]);
}
```

### Этап 5: Расширение TelegramService (1 день)

#### 5.1. Добавление методов

```php
// Отправка сообщения с клавиатурой
public function sendMessageWithKeyboard(
    string $token, 
    int $chatId, 
    string $text, 
    array $keyboard = []
): array

// Отправка документа
public function sendDocument(
    string $token, 
    int $chatId, 
    string $filePath, 
    ?string $caption = null
): array

// Проверка участника канала
public function getChatMember(
    string $token, 
    int $chatId, 
    int $userId
): array

// Ответ на callback_query
public function answerCallbackQuery(
    string $token, 
    string $callbackQueryId, 
    ?string $text = null
): array

// Редактирование сообщения
public function editMessageText(
    string $token, 
    int $chatId, 
    int $messageId, 
    string $text, 
    array $keyboard = []
): array
```

### Этап 6: Админ-панель - API (2-3 дня)

#### 6.1. Request классы для валидации

**Создать FormRequest классы**:
- `app/Http/Requests/UpdateBotSettingsRequest.php` - валидация настроек бота
- `app/Http/Requests/StoreConsultationRequest.php` - валидация формы консультации
- `app/Http/Requests/StoreMaterialRequest.php` - валидация создания материала
- `app/Http/Requests/UpdateMaterialRequest.php` - валидация обновления материала
- `app/Http/Requests/StoreMaterialCategoryRequest.php` - валидация категории материалов

**Детали валидации**:
- Все правила валидации описаны в `BOT_VALIDATION_GUIDE.md`
- Сообщения об ошибках на русском языке
- Валидация учитывает настройки бота (например, строгая/мягкая валидация телефона)

#### 6.2. BotManagementController (новый)

**Endpoints**:

```
GET    /api/v1/bot-management/{botId}/consultations        - Список заявок
GET    /api/v1/bot-management/{botId}/consultations/{id}   - Детали заявки
PUT    /api/v1/bot-management/{botId}/consultations/{id}   - Обновление статуса
GET    /api/v1/bot-management/{botId}/settings             - Настройки бота
PUT    /api/v1/bot-management/{botId}/settings             - Обновление настроек
GET    /api/v1/bot-management/{botId}/materials/categories - Список категорий
POST   /api/v1/bot-management/{botId}/materials/categories - Создание категории
PUT    /api/v1/bot-management/{botId}/materials/categories/{id} - Обновление
DELETE /api/v1/bot-management/{botId}/materials/categories/{id} - Удаление
GET    /api/v1/bot-management/{botId}/materials            - Список материалов
POST   /api/v1/bot-management/{botId}/materials            - Создание материала
PUT    /api/v1/bot-management/{botId}/materials/{id}       - Обновление
DELETE /api/v1/bot-management/{botId}/materials/{id}       - Удаление
GET    /api/v1/bot-management/{botId}/statistics           - Статистика бота
GET    /api/v1/bot-management/{botId}/users                - Список пользователей
```

#### 6.2. Структура ответов API

**Список заявок**:
```json
{
    "success": true,
    "data": {
        "consultations": [
            {
                "id": 1,
                "user": {
                    "id": 123456789,
                    "username": "john_doe",
                    "first_name": "John"
                },
                "name": "Иван Иванов",
                "phone": "+79001234567",
                "description": "Нужна консультация",
                "status": "new",
                "created_at": "2025-01-15T10:30:00Z"
            }
        ],
        "total": 10,
        "filters": {
            "status": "new",
            "date_from": "2025-01-01",
            "date_to": "2025-01-31"
        }
    }
}
```

**Статистика**:
```json
{
    "success": true,
    "data": {
        "total_users": 150,
        "active_users_30d": 45,
        "total_consultations": 23,
        "consultations_by_status": {
            "new": 5,
            "in_progress": 10,
            "closed": 8
        },
        "materials_downloads": 120,
        "popular_materials": [
            {
                "id": 1,
                "title": "Чек-лист по структурированию",
                "downloads": 45
            }
        ]
    }
}
```

### Этап 7: Админ-панель - Vue компоненты (3-4 дня)

#### 7.1. BotManagement.vue (главная страница)

**Разделы**:
- Табы: "Заявки", "Материалы", "Настройки", "Статистика"

**Заявки**:
- Таблица с фильтрами (статус, дата от/до)
- Сортировка по дате
- Детальный просмотр заявки (модальное окно)
- Изменение статуса
- Экспорт в CSV/Excel

**Материалы**:
- Древовидный список категорий
- CRUD категорий и материалов
- **Управление файлами материалов**:
  - Загрузка нового файла (drag & drop или выбор файла)
  - Выбор файла из существующей медиа-библиотеки (интеграция с разделом Media)
  - Указание внешней ссылки (для файлов на других серверах)
  - Использование Telegram file_id (если файл уже был отправлен в бот)
- Предпросмотр файлов (изображения, PDF, документы)
- Редактирование материалов (изменение файла, заголовка, описания)
- Управление порядком отображения (order_index)
- Включение/отключение материалов (is_active)

**Настройки**:
- **Обязательный канал для подписки:**
  - ID канала (например: -1001234567890) - можно получить через бота @userinfobot
  - Или username канала (например: @aip_channel) - без символа @
  - Кнопка "Проверить канал" - проверка доступности и прав бота в канале
  - Подсказка: "Бот должен быть администратором канала для проверки подписок"
  - **Текст экрана подписки** - редактируемый текст требования подписки
  - **Текст кнопки подписки** - можно изменить текст кнопки
  - **Текст кнопки проверки** - можно изменить текст "Я подписался"
- **Telegram ID администраторов** (для уведомлений о новых заявках)
  - Список ID администраторов (можно добавить несколько)
  - Каждый ID с отдельным полем ввода
  - Кнопка "Добавить администратора"
  - Подсказка: "ID можно узнать у бота @userinfobot"
  - **Шаблон уведомления** - редактируемый шаблон сообщения о новой заявке
- **Ссылка на Яндекс Карты** (для кнопки "Оставить отзыв")
- **Приветственное сообщение** (из существующего поля `welcome_message`)
  - Текстовое поле с возможностью форматирования
  - Предпросмотр сообщения
- **Тексты сообщений бота** (новый раздел):
  - **Раздел "Консультация":**
    - Текст описания услуги (редактируемый)
    - Тексты полей формы (имя, телефон, описание)
    - Сообщение после отправки заявки ("Спасибо...")
    - Тексты кнопок в форме
  - **Раздел "Материалы":**
    - Описание списка материалов
    - Тексты кнопок скачивания и навигации
  - **Главное меню:**
    - Тексты всех кнопок меню (можно изменить)
- **Дополнительные настройки:**
  - Валидация телефона (строгая/мягкая)
  - Максимальная длина описания в форме
  - Таймаут проверки подписки

**Статистика**:
- Графики (Chart.js или аналоги)
- Количество пользователей
- Количество заявок по статусам
- Популярные материалы

#### 7.2. Компоненты

```
resources/js/components/bot/
├── ConsultationTable.vue
├── ConsultationDetail.vue
├── MaterialCategoryTree.vue
├── MaterialForm.vue
├── BotSettingsForm.vue        # Форма настроек бота
│   ├── Вкладка "Основные настройки"
│   │   ├── Поле "ID канала" (числовое, с кнопкой "Проверить")
│   │   ├── Поле "Username канала" (текстовое, без @, с кнопкой "Проверить")
│   │   ├── Список ID администраторов (динамическое добавление/удаление)
│   │   ├── Поле "Ссылка на Яндекс Карты" (URL, с валидацией)
│   │   └── Текстовое поле "Приветственное сообщение" (textarea, с предпросмотром)
│   ├── Вкладка "Тексты сообщений"
│   │   ├── Раздел "Подписка на канал"
│   │   │   ├── Текст экрана подписки
│   │   │   ├── Текст кнопки подписки
│   │   │   └── Текст кнопки проверки
│   │   ├── Раздел "Консультация"
│   │   │   ├── Описание услуги
│   │   │   ├── Тексты полей формы
│   │   │   ├── Сообщение после отправки
│   │   │   └── Тексты кнопок
│   │   ├── Раздел "Материалы"
│   │   │   ├── Описание списка материалов
│   │   │   └── Тексты кнопок
│   │   └── Раздел "Главное меню"
│   │       └── Тексты кнопок меню
│   ├── Вкладка "Дополнительно"
│   │   ├── Валидация телефона (строгая/мягкая)
│   │   ├── Максимальная длина описания
│   │   └── Таймаут проверки подписки
│   └── Кнопки "Сохранить", "Сбросить к умолчанию", "Предпросмотр"
└── BotStatistics.vue
```

### Этап 8: Уведомления администраторам (1 день)

#### 8.1. Сервис уведомлений

```php
class BotNotificationService
{
    public function notifyNewConsultation(Bot $bot, BotConsultation $consultation): void
    {
        $adminIds = $bot->settings['admin_telegram_ids'] ?? [];
        
        if (empty($adminIds)) {
            return;
        }
        
        $message = $this->formatConsultationMessage($consultation);
        
        foreach ($adminIds as $adminId) {
            $this->telegram->sendMessage(
                $bot->token,
                $adminId,
                $message,
                ['parse_mode' => 'HTML']
            );
        }
        
        // Обновляем флаг уведомления
        $consultation->update([
            'telegram_notified' => true,
            'telegram_notified_at' => now(),
        ]);
    }
    
    protected function formatConsultationMessage(BotConsultation $consultation): string
    {
        return "<b>Новая заявка на консультацию</b>\n\n"
            . "Имя: {$consultation->name}\n"
            . "Телефон: {$consultation->phone}\n"
            . "Описание: {$consultation->description}\n"
            . "Дата: " . $consultation->created_at->format('d.m.Y H:i');
    }
}
```

### Этап 9: Тестирование и отладка (2-3 дня)

#### 9.1. Функциональное тестирование
- [ ] Проверка подписки на канал
- [ ] Навигация по меню
- [ ] Заполнение формы консультации
- [ ] Скачивание материалов
- [ ] Уведомления администраторам

#### 9.2. Интеграционное тестирование
- [ ] Обработка webhook от Telegram
- [ ] Работа с базой данных
- [ ] Логирование событий

#### 9.3. Тестирование админ-панели
- [ ] CRUD операции с материалами
- [ ] Просмотр и фильтрация заявок
- [ ] Изменение статусов
- [ ] Настройки бота

### Этап 10: Оптимизация и документация (1-2 дня)

#### 10.1. Оптимизация
- Кэширование категорий материалов
- Оптимизация запросов к БД
- Индексы в БД

#### 10.2. Документация
- Обновить `PROJECT_DOCUMENTATION.md`
- API документация (Swagger/OpenAPI)
- Руководство для администраторов

---

## 📊 API для админ-панели

### Управление настройками бота

#### GET /api/v1/bot-management/{botId}/settings

**Ответ**:
```json
{
    "success": true,
    "data": {
        "required_channel_id": -1001234567890,
        "required_channel_username": "@aip_channel",
        "admin_telegram_ids": [123456789, 987654321],
        "yandex_maps_url": "https://yandex.ru/maps/org/...",
        "welcome_message": "Добро пожаловать..."
    }
}
```

#### PUT /api/v1/bot-management/{botId}/settings

**Тело запроса**:
```json
{
    "required_channel_id": -1001234567890,
    "required_channel_username": "aip_channel",
    "admin_telegram_ids": [123456789, 987654321],
    "yandex_maps_url": "https://yandex.ru/maps/org/...",
    "welcome_message": "Добро пожаловать...",
    "messages": {
        "subscription": {
            "required_text": "Для доступа к бета-версии необходимо подписаться...",
            "subscribe_button": "🔔 Подписаться на Telegram",
            "check_button": "✅ Я подписался"
        },
        "consultation": {
            "description": "Если вашему бизнесу нужна профессиональная...",
            "form_name_label": "Введите ваше имя:",
            "form_phone_label": "Введите ваш телефон:",
            "form_description_label": "Краткое описание запроса (опционально...):",
            "thank_you": "Спасибо.\nМы свяжемся с вами в ближайшее время.",
            "start_button": "📝 Записаться на консультацию",
            "skip_description_button": "Пропустить"
        },
        "materials": {
            "list_description": "Мы подготовили материалы по ключевым направлениям...",
            "download_button": "⬇️ Скачать материалы",
            "back_to_list": "⬅️ Назад"
        },
        "menu": {
            "materials_button": "📂 Полезные материалы и договоры",
            "consultation_button": "📞 Записаться на консультацию",
            "review_button": "⭐ Оставить отзыв на Яндекс Картах",
            "back_to_menu": "⬅️ Назад в меню"
        },
        "notifications": {
            "consultation_template": "Новая заявка на консультацию\n\nИмя: {name}\nТелефон: {phone}\nОписание: {description}\nДата: {date}"
        }
    },
    "other_settings": {
        "phone_validation_strict": false,
        "max_description_length": 1000,
        "subscription_check_timeout": 5
    }
}
```

**Примечания**:
- `required_channel_id` - ID канала (число, может быть отрицательным для групп/каналов)
- `required_channel_username` - username канала БЕЗ символа @ (например: `aip_channel`, не `@aip_channel`)
- Можно указать либо `required_channel_id`, либо `required_channel_username`, либо оба (приоритет у ID)
- Если оба поля пустые - проверка подписки отключается
- `admin_telegram_ids` - массив ID администраторов (числа)
- `yandex_maps_url` - полный URL страницы на Яндекс Картах
- `welcome_message` - приветственное сообщение (максимум 4096 символов)
- `messages` - объект со всеми текстовыми сообщениями бота (опционально, есть значения по умолчанию)
- `other_settings` - дополнительные настройки (валидация, лимиты, таймауты)

**Валидация** (детали в `BOT_VALIDATION_GUIDE.md`):
- При указании username проверяется формат (без @, только латиница, цифры, подчёркивание)
- При указании channel_id проверяется, что это число в допустимом диапазоне
- Проверка существования канала выполняется отдельным endpoint (GET /api/v1/bot-management/{botId}/check-channel)
- Все текстовые поля в `messages` могут быть пустыми - тогда используются значения по умолчанию из кода
- URL Яндекс Карт проверяется на корректность и домен yandex.ru/maps
- Приветственное сообщение ограничено 4096 символами (лимит Telegram)
- ID администраторов проверяются как массив положительных чисел
- Дополнительные настройки валидируются по диапазонам (таймаут, длина описания)

### Управление заявками

#### GET /api/v1/bot-management/{botId}/consultations

**Query параметры**:
- `status` (optional): new|in_progress|closed
- `date_from` (optional): YYYY-MM-DD
- `date_to` (optional): YYYY-MM-DD
- `page` (optional): номер страницы
- `per_page` (optional): элементов на странице (по умолчанию 20)

#### PUT /api/v1/bot-management/{botId}/consultations/{id}

**Тело запроса**:
```json
{
    "status": "in_progress",
    "admin_notes": "Связался с клиентом"
}
```

### Управление материалами

#### POST /api/v1/bot-management/{botId}/materials/categories

**Тело запроса**:
```json
{
    "name": "Структурирование",
    "description": "Материалы по структурированию бизнеса",
    "order_index": 1
}
```

#### POST /api/v1/bot-management/{botId}/materials

**Вариант 1: Загрузка нового файла (multipart/form-data)**:
```
title: "Чек-лист по структурированию"
category_id: 1
description: "Подробный чек-лист..."
file_type: "file"
file: [File upload]
```

**Вариант 2: Использование файла из медиа-библиотеки**:
```json
{
    "title": "Чек-лист по структурированию",
    "category_id": 1,
    "description": "Подробный чек-лист...",
    "file_type": "file",
    "media_id": 123  // ID файла из медиа-библиотеки
}
```

**Вариант 3: Внешняя ссылка**:
```json
{
    "title": "Полезная статья",
    "category_id": 1,
    "description": "Описание...",
    "file_type": "url",
    "file_url": "https://example.com/article.pdf"
}
```

**Вариант 4: Telegram file_id (если файл уже был отправлен в Telegram)**:
```json
{
    "title": "Документ из Telegram",
    "category_id": 1,
    "file_type": "telegram_file_id",
    "file_id": "BAACAgIAAxkBAAI..."
}
```

**Примечание**: 
- При загрузке нового файла, файл сохраняется в существующую медиа-библиотеку (таблица `media`)
- Связь между `bot_materials` и `media` через поле `media_id` (опционально добавляется в миграцию)
- Если используется `media_id`, файл берется из медиа-библиотеки, что позволяет переиспользовать файлы

---

## 📝 Система логирования

### Уровни логирования

1. **INFO** - Обычные события (сообщения, переходы по меню)
2. **WARNING** - Предупреждения (не подписан на канал, ошибки валидации)
3. **ERROR** - Ошибки (не удалось отправить сообщение, ошибки БД)
4. **DEBUG** - Детальная информация (для отладки)

### Каналы логирования

**config/logging.php**:
```php
'channels' => [
    // ...
    'bot' => [
        'driver' => 'daily',
        'path' => storage_path('logs/bot.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
    
    'bot_errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/bot_errors.log'),
        'level' => 'error',
        'days' => 30,
    ],
],
```

### Формат логов

```json
{
    "timestamp": "2025-01-15T10:30:00.123456Z",
    "level": "info",
    "bot_id": 1,
    "user_id": 123456789,
    "event": "message_received",
    "action": "menu_main",
    "data": {
        "text": "/start",
        "update_id": 12345
    }
}
```

### Логирование в таблице bot_logs

**Преимущества**:
- Быстрый поиск по пользователю/боту
- Аналитика через SQL
- История взаимодействий

**Недостатки**:
- Рост таблицы требует очистки старых логов
- Дополнительная нагрузка на БД

**Рекомендация**: Использовать оба метода (файлы + БД для критичных событий)

---

## 💾 Сбор и запись данных

### Данные пользователя

**Сбор**:
- При первом взаимодействии (`/start`)
- Обновление при каждом сообщении (username может измениться)

**Хранение**: Таблица `bot_users`

### Данные подписки

**Сбор**:
- При каждом запросе доступа к меню
- При нажатии "Я подписался"
- Кэширование результата на 5 минут

**Хранение**: Таблица `bot_subscriptions` (история проверок)

### Данные формы консультации

**Сбор**:
- Пошагово при заполнении формы
- Временное хранение в `state_data` пользователя
- Финальное сохранение в `bot_consultations`

**Хранение**:
- Временное: `bot_users.state_data` (JSON)
- Постоянное: `bot_consultations`

### Данные материалов

**Сбор**:
- Создание/редактирование через админ-панель
- Счетчик скачиваний при каждой отправке материала

**Хранение**: 
- Метаданные материалов: Таблицы `bot_material_categories` и `bot_materials`
- Файлы: Таблица `media` (через связь `bot_materials.media_id`)
- Внешние ссылки: Поле `bot_materials.file_url`
- Telegram file_id: Поле `bot_materials.file_id`

### Логи взаимодействий

**Сбор**:
- Каждое сообщение
- Каждая callback_query
- Проверки подписки
- Создание заявок

**Хранение**:
- Файлы: `storage/logs/bot.log`, `storage/logs/bot_errors.log`
- БД (опционально): `bot_logs`

### Персональные данные

**GDPR/Согласие**:
- При первом использовании бота показывать согласие на обработку персональных данных
- Сохранять флаг согласия в `bot_users.consent_given`
- Возможность удаления данных пользователя (команда `/delete_my_data`)

---

## 🔄 Последовательность разработки (Рекомендуемый порядок)

### Фаза 1: Backend Core (Неделя 1)
1. Миграции и модели
2. BotSubscriptionService
3. BotMenuService
4. BotHandlerService (базовая структура)
5. Интеграция с webhook

### Фаза 2: Логика бота (Неделя 2)
6. Обработка /start и проверка подписки
7. Главное меню и навигация
8. BotFormService и форма консультации
9. BotMaterialService и материалы
10. Расширение TelegramService

### Фаза 3: Админ-панель (Неделя 3)
11. API endpoints для управления
12. Vue компоненты для заявок
13. Vue компоненты для материалов
14. Настройки бота в админке
15. Статистика

### Фаза 4: Полировка (Неделя 4)
16. Уведомления администраторам
17. Логирование
18. Тестирование
19. Оптимизация
20. Документация

---

## 🎯 Критерии готовности

### Бот готов к бета-тестированию, когда:

✅ Все миграции выполнены без ошибок  
✅ Команда /start работает корректно  
✅ Проверка подписки функционирует  
✅ Главное меню отображается  
✅ Навигация по материалам работает  
✅ Форма консультации заполняется и отправляется  
✅ Заявки сохраняются в БД  
✅ Администраторы получают уведомления  
✅ Админ-панель отображает заявки  
✅ Можно создавать/редактировать материалы  
✅ Логи записываются корректно  

---

## 📌 Важные замечания

1. **Безопасность**:
   - Валидация всех входных данных
   - Проверка secret_token для webhook
   - Защита админ-панели через middleware
   - Санитизация данных перед сохранением в БД

2. **Производительность**:
   - Кэширование категорий материалов
   - Кэширование проверок подписки (5 минут)
   - Индексы в БД для частых запросов
   - Асинхронная отправка уведомлений (через очередь)

3. **Масштабируемость**:
   - Поддержка нескольких ботов
   - Изоляция данных по bot_id
   - Очистка старых логов (cron job)

4. **Обработка ошибок**:
   - Try-catch во всех критичных местах
   - Логирование всех ошибок
   - Graceful degradation (если что-то сломалось, бот продолжает работать)

5. **Тестирование**:
   - Тестовый бот для разработки
   - Тестовая группа/канал для проверки подписки
   - Тестовые данные в БД

---

## 📚 Дополнительные ресурсы

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [Laravel Documentation](https://laravel.com/docs)
- [Vue 3 Documentation](https://vuejs.org/)
- ТЗ: `bot.md`
- Существующий код: 
  - `app/Services/TelegramService.php`
  - `app/Http/Controllers/Api/BotController.php`
  - `app/Http/Controllers/Api/v1/MediaController.php` (интеграция с медиа-библиотекой)
  - `app/Models/Media.php` (модель медиа-файлов)

## 📖 Дополнительная документация

- `BOT_MATERIALS_ADMIN_GUIDE.md` - Подробное руководство по управлению материалами в админ-панели
- `BOT_SETTINGS_GUIDE.md` - Подробное руководство по настройке бота (каналы, администраторы, сообщения)
- `BOT_VALIDATION_GUIDE.md` - Подробное руководство по валидации данных (настройки и пользовательские поля)
- `BOT_SETTINGS_CHECKLIST.md` - Чеклист всех настроек согласно ТЗ (для проверки полноты)
- `BOT_STATE_MAP.md` - Карта состояний бота и примеры использования
- `BOT_USER_GUIDE.md` - Руководство для пользователей бота (простыми словами)
- `BOT_QUICK_START.md` - Быстрый старт для пользователей

---

**Дата создания плана**: 2025-01-15  
**Версия плана**: 1.0  
**Статус**: Готов к реализации

