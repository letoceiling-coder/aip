<?php

namespace App\Services;

use App\Constants\BotActions;
use App\Constants\BotStates;
use App\Models\Bot;
use App\Models\BotUser;
use App\Models\AdminRequest;
use Illuminate\Support\Facades\Log;

class BotHandlerService
{
    protected TelegramService $telegram;
    protected BotSubscriptionService $subscription;
    protected BotMenuService $menu;
    protected BotFormService $form;
    protected BotMaterialService $material;
    protected BotLoggerService $logger;
    protected BotNotificationService $notification;

    public function __construct(
        TelegramService $telegram,
        BotSubscriptionService $subscription,
        BotMenuService $menu,
        BotFormService $form,
        BotMaterialService $material,
        BotLoggerService $logger,
        BotNotificationService $notification
    ) {
        $this->telegram = $telegram;
        $this->subscription = $subscription;
        $this->menu = $menu;
        $this->form = $form;
        $this->material = $material;
        $this->logger = $logger;
        $this->notification = $notification;
    }

    /**
     * Обработать обновление от Telegram
     */
    public function handleUpdate(Bot $bot, array $update): void
    {
        try {
            // Определяем тип обновления
            if (isset($update['message'])) {
                $this->handleMessage($bot, $update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query']);
            }
        } catch (\Exception $e) {
            Log::error("Error handling update for bot {$bot->id}: " . $e->getMessage(), [
                'update_id' => $update['update_id'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Обработать сообщение
     */
    protected function handleMessage(Bot $bot, array $message): void
    {
        $from = $message['from'] ?? null;
        if (!$from) {
            return;
        }

        $telegramUserId = $from['id'];
        $chatId = $message['chat']['id'] ?? $telegramUserId;
        $text = $message['text'] ?? null;

        // Получаем или создаем пользователя
        $user = $this->getOrCreateUser($bot, $telegramUserId, $from);

        // Обновляем последнее взаимодействие
        $user->update(['last_interaction_at' => now()]);

        // Логирование
        $this->logger->logMessage($bot->id, $telegramUserId, $message, 'message_received');

        // Обработка команды /start
        if ($text && (str_starts_with($text, '/start') || $text === '/start')) {
            $this->handleStartCommand($bot, $user);
            return;
        }

        // Обработка команды /admin
        if ($text && (str_starts_with($text, '/admin') || $text === '/admin')) {
            $this->handleAdminCommand($bot, $user);
            return;
        }

        // Проверяем, является ли сообщение текстом reply кнопки
        if ($text && $this->handleReplyButton($bot, $user, $text)) {
            return; // Обработано как reply кнопка
        }

        // Обработка текстовых сообщений в зависимости от состояния
        if ($text && $user->current_state) {
            $this->handleState($bot, $user, $text, $message);
        } else {
            // Неизвестная команда
            $this->telegram->sendMessage($bot->token, $chatId, 
                "Не понимаю эту команду. Используйте кнопки меню для навигации.");
        }
    }

    /**
     * Обработать callback_query
     */
    protected function handleCallbackQuery(Bot $bot, array $callbackQuery): void
    {
        $from = $callbackQuery['from'] ?? null;
        if (!$from) {
            return;
        }

        $telegramUserId = $from['id'];
        $chatId = $callbackQuery['message']['chat']['id'] ?? $telegramUserId;
        $data = $callbackQuery['data'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (!$data) {
            return;
        }

        // Получаем или создаем пользователя
        $user = $this->getOrCreateUser($bot, $telegramUserId, $from);
        $user->update(['last_interaction_at' => now()]);

        // Логирование
        $this->logger->logCallbackQuery($bot->id, $telegramUserId, $callbackQuery, $data);

        // Подтверждаем получение callback
        $this->telegram->answerCallbackQuery($bot->token, $callbackQueryId);

        // Обработка callback_data
        if (str_starts_with($data, BotActions::MENU_MATERIALS)) {
            $this->showMaterialsList($bot, $user);
        } elseif (str_starts_with($data, BotActions::MENU_CONSULTATION)) {
            $this->showConsultationDescription($bot, $user);
        } elseif (str_starts_with($data, BotActions::MATERIAL_CATEGORY)) {
            $categoryId = (int) str_replace(BotActions::MATERIAL_CATEGORY, '', $data);
            $this->showMaterialCategory($bot, $user, $categoryId);
        } elseif (str_starts_with($data, BotActions::MATERIAL_DOWNLOAD)) {
            $materialId = (int) str_replace(BotActions::MATERIAL_DOWNLOAD, '', $data);
            $this->sendMaterial($bot, $user, $materialId);
        } elseif ($data === BotActions::CONSULTATION_START) {
            $this->startConsultationForm($bot, $user);
        } elseif ($data === BotActions::CONSULTATION_SKIP_DESCRIPTION) {
            $this->submitConsultation($bot, $user);
        } elseif ($data === BotActions::DOWNLOAD_PRESENTATION) {
            $this->sendPresentation($bot, $user);
        } elseif ($data === BotActions::BACK_MAIN_MENU || $data === BotActions::BACK_MATERIALS_LIST) {
            $this->showMainMenu($bot, $user);
        } elseif ($data === BotActions::CHECK_SUBSCRIPTION) {
            $this->checkSubscriptionAndProceed($bot, $user);
        }
    }

    /**
     * Получить или создать пользователя
     */
    protected function getOrCreateUser(Bot $bot, int $telegramUserId, array $from): BotUser
    {
        $user = BotUser::where('bot_id', $bot->id)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if (!$user) {
            $user = BotUser::create([
                'bot_id' => $bot->id,
                'telegram_user_id' => $telegramUserId,
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'language_code' => $from['language_code'] ?? null,
                'current_state' => BotStates::IDLE,
            ]);
        } else {
            // Обновляем информацию о пользователе
            $user->update([
                'username' => $from['username'] ?? $user->username,
                'first_name' => $from['first_name'] ?? $user->first_name,
                'last_name' => $from['last_name'] ?? $user->last_name,
                'language_code' => $from['language_code'] ?? $user->language_code,
            ]);
        }

        return $user;
    }

    /**
     * Обработать команду /start
     */
    protected function handleStartCommand(Bot $bot, BotUser $user): void
    {
        // Сбрасываем состояние
        $user->update(['current_state' => BotStates::CHECK_SUBSCRIPTION]);

        // Проверяем подписку
        $this->checkSubscriptionAndProceed($bot, $user);
    }

    /**
     * Обработать команду /admin
     */
    protected function handleAdminCommand(Bot $bot, BotUser $user): void
    {
        // Проверяем, есть ли уже активная заявка
        $existingRequest = AdminRequest::where('bot_id', $bot->id)
            ->where('telegram_user_id', $user->telegram_user_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                "⏳ У вас уже есть активная заявка на назначение администратором. Пожалуйста, дождитесь рассмотрения."
            );
            return;
        }

        // Создаем новую заявку
        $request = AdminRequest::create([
            'bot_id' => $bot->id,
            'telegram_user_id' => $user->telegram_user_id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'status' => 'pending',
        ]);

        // Отправляем подтверждение пользователю
        $this->telegram->sendMessage(
            $bot->token,
            $user->telegram_user_id,
            "✅ Заявка на назначение администратором успешно создана!\n\n" .
            "Ваша заявка будет рассмотрена администраторами. Вы получите уведомление о результате."
        );

        // Логируем создание заявки
        $this->logger->logMessage(
            $bot->id,
            $user->telegram_user_id,
            ['text' => '/admin', 'request_id' => $request->id],
            'admin_request_created'
        );
    }

    /**
     * Проверить подписку и продолжить
     */
    protected function checkSubscriptionAndProceed(Bot $bot, BotUser $user): void
    {
        $isSubscribed = $this->subscription->checkSubscription($bot->id, $user->telegram_user_id);
        
        $this->logger->logSubscriptionCheck($bot->id, $user->telegram_user_id, $isSubscribed);

        if ($isSubscribed) {
            $user->update(['current_state' => BotStates::MAIN_MENU]);
            $this->showMainMenu($bot, $user);
        } else {
            $user->update(['current_state' => BotStates::SHOW_SUBSCRIBE_SCREEN]);
            $this->showSubscribeScreen($bot, $user);
        }
    }

    /**
     * Показать экран подписки
     */
    protected function showSubscribeScreen(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $subscription = $messages['subscription'] ?? [];

        $requiredText = $subscription['required_text'] ?? 
            'Для доступа к бета-версии необходимо подписаться на наш официальный Telegram-канал.';
        $subscribeButton = $subscription['subscribe_button'] ?? '🔔 Подписаться на Telegram';
        $checkButton = $subscription['check_button'] ?? '✅ Я подписался';

        // Проверяем, что значения являются строками, а не массивами
        $text = is_array($requiredText) 
            ? 'Для доступа к бета-версии необходимо подписаться на наш официальный Telegram-канал.'
            : (string) $requiredText;
        $subscribeButton = is_array($subscribeButton) ? '🔔 Подписаться на Telegram' : (string) $subscribeButton;
        $checkButton = is_array($checkButton) ? '✅ Я подписался' : (string) $checkButton;

        $channelId = $bot->required_channel_id;
        $channelUsername = $bot->required_channel_username;
        $channelUrl = null;

        if ($channelUsername) {
            $channelUsername = is_array($channelUsername) ? null : (string) $channelUsername;
            if ($channelUsername) {
                $channelUrl = 'https://t.me/' . ltrim($channelUsername, '@');
            }
        } elseif ($channelId) {
            // Для ID канала нельзя создать прямую ссылку, используем username если есть
        }

        $keyboard = [];
        if ($channelUrl) {
            $keyboard[] = [['text' => $subscribeButton, 'url' => $channelUrl]];
        }
        $keyboard[] = [['text' => $checkButton, 'callback_data' => BotActions::CHECK_SUBSCRIPTION]];

        $this->telegram->sendMessageWithKeyboard(
            $bot->token,
            $user->telegram_user_id,
            $text,
            $keyboard
        );
    }

    /**
     * Показать главное меню
     */
    protected function showMainMenu(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $welcomeMedia = $settings['welcome_media'] ?? [];
        
        // Отправляем медиа перед сообщением, если оно настроено
        if (!empty($welcomeMedia['type'])) {
            $this->sendWelcomeMedia($bot, $user, $welcomeMedia);
        }
        
        $welcomeMessage = $bot->welcome_message ?? $this->getDefaultWelcomeMessage();
        
        // Проверяем, что welcome_message является строкой, а не массивом, и не пустое
        if (is_array($welcomeMessage) || empty(trim((string) $welcomeMessage))) {
            $welcomeMessage = $this->getDefaultWelcomeMessage();
        } else {
            $welcomeMessage = (string) $welcomeMessage;
        }

        // ОБЯЗАТЕЛЬНО отправляем inline клавиатуру с двумя обязательными кнопками:
        // 1. Полезные материалы и договоры
        // 2. Записаться на консультацию
        // Эти кнопки всегда присутствуют, независимо от настроек
        $keyboard = $this->menu->getMainMenuKeyboard($bot);
        $this->telegram->sendMessageWithKeyboard(
            $bot->token,
            $user->telegram_user_id,
            $welcomeMessage,
            $keyboard
        );

        $user->update(['current_state' => BotStates::MAIN_MENU]);
    }

    /**
     * Построить reply клавиатуру
     * ОБЯЗАТЕЛЬНО включает две кнопки: Полезные материалы и Записаться на консультацию
     */
    protected function buildReplyKeyboard(Bot $bot): array
    {
        $settings = $bot->settings ?? [];
        $replyButtons = $settings['reply_buttons'] ?? [];
        
        $keyboard = [];
        
        // ОБЯЗАТЕЛЬНАЯ Кнопка 1: Полезные материалы и договора, презентации
        // Всегда добавляется, независимо от настроек
        $materialsButtonText = '📂 Полезные материалы и договора, презентации';
        if (!empty($replyButtons['materials_button_text'])) {
            $materialsButtonText = is_array($replyButtons['materials_button_text']) 
                ? '📂 Полезные материалы и договора, презентации'
                : trim((string) $replyButtons['materials_button_text']);
            if (empty($materialsButtonText)) {
                $materialsButtonText = '📂 Полезные материалы и договора, презентации';
            }
        }
        $keyboard[] = [['text' => $materialsButtonText]];
        
        // ОБЯЗАТЕЛЬНАЯ Кнопка 2: Записаться на консультацию
        // Всегда добавляется, независимо от настроек
        $consultationButtonText = '📞 Записаться на консультацию';
        if (!empty($replyButtons['consultation_button_text'])) {
            $consultationButtonText = is_array($replyButtons['consultation_button_text']) 
                ? '📞 Записаться на консультацию'
                : trim((string) $replyButtons['consultation_button_text']);
            if (empty($consultationButtonText)) {
                $consultationButtonText = '📞 Записаться на консультацию';
            }
        }
        $keyboard[] = [['text' => $consultationButtonText]];
        
        // Дополнительная Кнопка 3: Наш офис на Яндекс Картах (опциональная)
        if (!empty($replyButtons['office_button_text'])) {
            $buttonText = is_array($replyButtons['office_button_text']) 
                ? '📍 Наш офис на Яндекс Картах'
                : trim((string) $replyButtons['office_button_text']);
            if (!empty($buttonText)) {
                $keyboard[] = [['text' => $buttonText]];
            }
        }
        
        return $keyboard;
    }

    /**
     * Отправить медиа перед приветственным сообщением
     */
    protected function sendWelcomeMedia(Bot $bot, BotUser $user, array $welcomeMedia): void
    {
        try {
            $mediaType = $welcomeMedia['type'] ?? null;
            
            if ($mediaType === 'photo' || $mediaType === 'video') {
                // Одно фото или видео
                $mediaId = $welcomeMedia['media_id'] ?? null;
                if (!$mediaId) {
                    return;
                }
                
                $media = \App\Models\Media::find($mediaId);
                if (!$media || !$media->fileExists()) {
                    Log::warning("Welcome media file not found", [
                        'bot_id' => $bot->id,
                        'media_id' => $mediaId,
                    ]);
                    return;
                }
                
                $filePath = $media->fullPath;
                
                if ($mediaType === 'photo') {
                    // Используем file_id если есть, иначе отправляем файл
                    if ($media->telegram_file_id) {
                        $this->telegram->sendPhotoByFileId(
                            $bot->token,
                            $user->telegram_user_id,
                            $media->telegram_file_id
                        );
                    } else {
                        $result = $this->telegram->sendPhoto(
                            $bot->token,
                            $user->telegram_user_id,
                            $filePath
                        );
                        
                        // Сохраняем file_id для будущих отправок
                        if ($result['success'] && isset($result['data']['photo'])) {
                            $photos = $result['data']['photo'];
                            $largestPhoto = end($photos); // Берем самое большое фото
                            if (isset($largestPhoto['file_id'])) {
                                $media->telegram_file_id = $largestPhoto['file_id'];
                                $media->save();
                            }
                        }
                    }
                } else {
                    // Видео
                    if ($media->telegram_file_id) {
                        $this->telegram->sendVideoByFileId(
                            $bot->token,
                            $user->telegram_user_id,
                            $media->telegram_file_id
                        );
                    } else {
                        $result = $this->telegram->sendVideo(
                            $bot->token,
                            $user->telegram_user_id,
                            $filePath
                        );
                        
                        // Сохраняем file_id для будущих отправок
                        if ($result['success'] && isset($result['data']['video']['file_id'])) {
                            $media->telegram_file_id = $result['data']['video']['file_id'];
                            $media->save();
                        }
                    }
                }
            } elseif ($mediaType === 'gallery') {
                // Галерея фото (до 10)
                $galleryIds = $welcomeMedia['gallery'] ?? [];
                if (empty($galleryIds)) {
                    return;
                }
                
                // Ограничиваем до 10 фото
                $galleryIds = array_slice($galleryIds, 0, 10);
                
                $mediaItems = \App\Models\Media::whereIn('id', $galleryIds)
                    ->where('type', 'photo')
                    ->get();
                
                if ($mediaItems->isEmpty()) {
                    return;
                }
                
                // Если только одно фото, отправляем его отдельно
                if ($mediaItems->count() === 1) {
                    $media = $mediaItems->first();
                    if (!$media->fileExists()) {
                        return;
                    }
                    
                    if ($media->telegram_file_id) {
                        $this->telegram->sendPhotoByFileId(
                            $bot->token,
                            $user->telegram_user_id,
                            $media->telegram_file_id
                        );
                    } else {
                        $result = $this->telegram->sendPhoto(
                            $bot->token,
                            $user->telegram_user_id,
                            $media->fullPath
                        );
                        
                        // Сохраняем file_id для будущих отправок
                        if ($result['success'] && isset($result['data']['photo'])) {
                            $photos = $result['data']['photo'];
                            $largestPhoto = end($photos);
                            if (isset($largestPhoto['file_id'])) {
                                $media->telegram_file_id = $largestPhoto['file_id'];
                                $media->save();
                            }
                        }
                    }
                    return;
                }
                
                // Для нескольких фото формируем медиа-группу
                // Сначала получаем file_id для всех фото, которые его не имеют
                foreach ($mediaItems as $media) {
                    if (!$media->fileExists()) {
                        continue;
                    }
                    
                    // Если нет file_id, отправляем фото отдельно, чтобы получить его
                    if (!$media->telegram_file_id) {
                        $result = $this->telegram->sendPhoto(
                            $bot->token,
                            $user->telegram_user_id,
                            $media->fullPath
                        );
                        
                        if ($result['success'] && isset($result['data']['photo'])) {
                            $photos = $result['data']['photo'];
                            $largestPhoto = end($photos);
                            if (isset($largestPhoto['file_id'])) {
                                $media->telegram_file_id = $largestPhoto['file_id'];
                                $media->save();
                            }
                        }
                    }
                }
                
                // Формируем массив медиа для отправки медиа-группы
                $mediaGroup = [];
                foreach ($mediaItems as $index => $media) {
                    if (!$media->telegram_file_id) {
                        continue; // Пропускаем если нет file_id
                    }
                    
                    $mediaGroup[] = [
                        'type' => 'photo',
                        'media' => $media->telegram_file_id,
                    ];
                }
                
                // Отправляем медиа-группу только если есть хотя бы 2 фото
                if (count($mediaGroup) > 1) {
                    $this->telegram->sendMediaGroup(
                        $bot->token,
                        $user->telegram_user_id,
                        $mediaGroup
                    );
                } elseif (count($mediaGroup) === 1) {
                    // Если осталось только одно фото, отправляем его отдельно
                    $this->telegram->sendPhotoByFileId(
                        $bot->token,
                        $user->telegram_user_id,
                        $mediaGroup[0]['media']
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("Error sending welcome media: " . $e->getMessage(), [
                'bot_id' => $bot->id,
                'user_id' => $user->telegram_user_id,
                'exception' => $e,
            ]);
            // Продолжаем отправку сообщения даже если медиа не отправилось
        }
    }

    /**
     * Отправить презентацию пользователю
     */
    protected function sendPresentation(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $presentation = $settings['presentation'] ?? [];
        $presentationMediaId = $presentation['media_id'] ?? null;
        
        if (!$presentationMediaId) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Презентация не найдена'
            );
            return;
        }
        
        $media = \App\Models\Media::find($presentationMediaId);
        if (!$media || !$media->fileExists()) {
            Log::warning("Presentation file not found", [
                'bot_id' => $bot->id,
                'media_id' => $presentationMediaId,
            ]);
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Файл презентации не найден'
            );
            return;
        }
        
        $filePath = $media->fullPath;
        
        // Отправляем документ
        $result = $this->telegram->sendDocument(
            $bot->token,
            $user->telegram_user_id,
            $filePath,
            '📥 Презентация'
        );
        
        if (!$result['success']) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                $result['message'] ?? 'Не удалось отправить презентацию'
            );
        } else {
            // Сохраняем file_id для будущих отправок
            if (isset($result['data']['document']['file_id'])) {
                $media->telegram_file_id = $result['data']['document']['file_id'];
                $media->save();
            }
        }
    }

    /**
     * Обработать reply кнопку
     */
    protected function handleReplyButton(Bot $bot, BotUser $user, string $text): bool
    {
        $settings = $bot->settings ?? [];
        $replyButtons = $settings['reply_buttons'] ?? [];
        
        // Кнопка 1: Полезные материалы и договора, презентации
        $materialsButtonText = $replyButtons['materials_button_text'] ?? '';
        if (!empty($materialsButtonText)) {
            $materialsButtonText = is_array($materialsButtonText) ? '' : trim((string) $materialsButtonText);
            if ($text === $materialsButtonText) {
                $this->sendMaterialsFiles($bot, $user);
                return true;
            }
        }
        
        // Кнопка 2: Записаться на консультацию
        $consultationButtonText = $replyButtons['consultation_button_text'] ?? '';
        if (!empty($consultationButtonText)) {
            $consultationButtonText = is_array($consultationButtonText) ? '' : trim((string) $consultationButtonText);
            // Сравниваем с удалением пробелов для надежности
            if (trim($text) === $consultationButtonText) {
                Log::info("Reply button consultation clicked", [
                    'bot_id' => $bot->id,
                    'user_id' => $user->telegram_user_id,
                    'button_text' => $consultationButtonText,
                    'received_text' => $text,
                ]);
                // Сразу начинаем форму записи на консультацию
                $this->startConsultationForm($bot, $user);
                return true;
            }
        }
        
        // Кнопка 3: Наш офис на Яндекс Картах
        $officeButtonText = $replyButtons['office_button_text'] ?? '';
        if (!empty($officeButtonText)) {
            $officeButtonText = is_array($officeButtonText) ? '' : trim((string) $officeButtonText);
            if (trim($text) === $officeButtonText) {
                $this->sendOfficeLocation($bot, $user);
                return true;
            }
        }
        
        return false; // Не является reply кнопкой
    }

    /**
     * Отправить файлы материалов
     */
    protected function sendMaterialsFiles(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $replyButtons = $settings['reply_buttons'] ?? [];
        $materialsFiles = $replyButtons['materials_files'] ?? [];
        
        if (empty($materialsFiles)) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Файлы не найдены'
            );
            return;
        }
        
        $mediaItems = \App\Models\Media::whereIn('id', $materialsFiles)->get();
        
        if ($mediaItems->isEmpty()) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Файлы не найдены'
            );
            return;
        }
        
        foreach ($mediaItems as $media) {
            if (!$media->fileExists()) {
                continue;
            }
            
            $filePath = $media->fullPath;
            
            // Определяем тип файла и отправляем соответствующим методом
            if ($media->type === 'photo') {
                if ($media->telegram_file_id) {
                    $this->telegram->sendPhotoByFileId(
                        $bot->token,
                        $user->telegram_user_id,
                        $media->telegram_file_id
                    );
                } else {
                    $result = $this->telegram->sendPhoto(
                        $bot->token,
                        $user->telegram_user_id,
                        $filePath
                    );
                    if ($result['success'] && isset($result['data']['photo'])) {
                        $photos = $result['data']['photo'];
                        $largestPhoto = end($photos);
                        if (isset($largestPhoto['file_id'])) {
                            $media->telegram_file_id = $largestPhoto['file_id'];
                            $media->save();
                        }
                    }
                }
            } elseif ($media->type === 'video') {
                if ($media->telegram_file_id) {
                    $this->telegram->sendVideoByFileId(
                        $bot->token,
                        $user->telegram_user_id,
                        $media->telegram_file_id
                    );
                } else {
                    $result = $this->telegram->sendVideo(
                        $bot->token,
                        $user->telegram_user_id,
                        $filePath
                    );
                    if ($result['success'] && isset($result['data']['video']['file_id'])) {
                        $media->telegram_file_id = $result['data']['video']['file_id'];
                        $media->save();
                    }
                }
            } else {
                // Отправляем как документ
                $result = $this->telegram->sendDocument(
                    $bot->token,
                    $user->telegram_user_id,
                    $filePath,
                    $media->original_name ?? 'Файл'
                );
                
                if ($result['success'] && isset($result['data']['document']['file_id'])) {
                    $media->telegram_file_id = $result['data']['document']['file_id'];
                    $media->save();
                }
            }
            
            // Небольшая задержка между отправками
            usleep(500000); // 0.5 секунды
        }
    }

    /**
     * Отправить локацию офиса
     */
    protected function sendOfficeLocation(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $officeLocation = $settings['office_location'] ?? [];
        
        $latitude = $officeLocation['latitude'] ?? null;
        $longitude = $officeLocation['longitude'] ?? null;
        $address = $officeLocation['address'] ?? '';
        
        if ($latitude && $longitude) {
            // Отправляем карту
            $this->telegram->sendLocation(
                $bot->token,
                $user->telegram_user_id,
                (float) $latitude,
                (float) $longitude
            );
        }
        
        // Отправляем адрес текстом
        if (!empty($address)) {
            $addressText = is_array($address) ? '' : (string) $address;
            if ($addressText) {
                $this->telegram->sendMessage(
                    $bot->token,
                    $user->telegram_user_id,
                    "📍 " . $addressText
                );
            }
        } else {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Адрес офиса не указан'
            );
        }
    }

    /**
     * Получить приветственное сообщение по умолчанию
     */
    protected function getDefaultWelcomeMessage(): string
    {
        return "Добро пожаловать в Аудиторско-консалтинговую группу «АИП» - одна из ведущих консалтинговых компаний России в области аудиторских, налоговых и юридических услуг!\n\n" .
               "Уже более 25 лет мы помогаем бизнесу успешно выходить из сложных ситуаций, выстраивать надёжную финансово-правовую систему и заранее предотвращать риски.\n\n" .
               "Компания входит в топ-30 в своей сфере по рейтингам ведущих аудиторско-консалтинговых агентств: «Эксперт РА», «ПРАВО-300», EuraAudit International, а также занимает 15-е место среди международных аудиторских организаций.\n\n" .
               "Являемся участником международной ассоциации EuraAudit International, что подтверждает соответствие нашей работы высоким международным стандартам качества и профессиональной этики.\n\n" .
               "Выберите, чем можем быть полезны 👇";
    }

    /**
     * Показать список материалов
     */
    protected function showMaterialsList(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $materials = $messages['materials'] ?? [];

        $keyboard = $this->menu->getMaterialsListKeyboard($bot->id);

        // Проверяем, есть ли категории (кроме кнопки "Назад")
        $hasCategories = count($keyboard) > 1;

        if (!$hasCategories) {
            $text = 'К сожалению, материалы пока не добавлены. Пожалуйста, попробуйте позже.';
        } else {
            $listDescription = $materials['list_description'] ?? 
                'Мы подготовили материалы по ключевым направлениям нашей работы.';
            // Проверяем, что значение является строкой, а не массивом
            $text = is_array($listDescription) 
                ? 'Мы подготовили материалы по ключевым направлениям нашей работы.'
                : (string) $listDescription;
        }

        $this->telegram->sendMessageWithKeyboard(
            $bot->token,
            $user->telegram_user_id,
            $text,
            $keyboard
        );

        $user->update(['current_state' => BotStates::MATERIALS_LIST]);
    }

    /**
     * Показать категорию материала
     */
    protected function showMaterialCategory(Bot $bot, BotUser $user, int $categoryId): void
    {
        $category = \App\Models\BotMaterialCategory::with('media')->find($categoryId);
        if (!$category || $category->bot_id != $bot->id) {
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, 'Категория не найдена');
            return;
        }

        // Если у категории есть файл из медиа-библиотеки, отправляем его
        if ($category->media_id && $category->media) {
            $this->sendCategoryFile($bot, $user, $category);
            return;
        }

        // Иначе показываем список материалов категории (старая логика)
        $categoryName = is_array($category->name) ? '' : (string) ($category->name ?? '');
        $categoryDescription = is_array($category->description) ? '' : (string) ($category->description ?? '');
        
        $text = $categoryName;
        if ($categoryDescription) {
            $text .= "\n\n" . $categoryDescription;
        }
        
        if (empty($text)) {
            $text = 'Категория материалов';
        }

        $keyboard = $this->menu->getMaterialCategoryKeyboard($categoryId);

        $this->telegram->sendMessageWithKeyboard(
            $bot->token,
            $user->telegram_user_id,
            $text,
            $keyboard
        );

        $user->update(['current_state' => BotStates::MATERIAL_CATEGORY]);
    }

    /**
     * Отправить файл категории
     */
    protected function sendCategoryFile(Bot $bot, BotUser $user, \App\Models\BotMaterialCategory $category): void
    {
        $media = $category->media;
        if (!$media) {
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, 'Файл категории не найден');
            return;
        }

        // Получаем путь к файлу
        $filePath = $this->getMediaFilePath($media);
        if (!$filePath || !file_exists($filePath)) {
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, 'Файл не найден на сервере');
            return;
        }

        // Формируем подпись
        $caption = $category->name;
        if ($category->description) {
            $caption .= "\n\n" . $category->description;
        }

        // Отправляем файл
        $result = $this->telegram->sendDocument(
            $bot->token,
            $user->telegram_user_id,
            $filePath,
            $caption
        );

        if (!$result['success']) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                $result['message'] ?? 'Не удалось отправить файл'
            );
        }

        // Возвращаем в главное меню
        $this->showMainMenu($bot, $user);
    }

    /**
     * Получить путь к файлу из медиа-библиотеки
     */
    protected function getMediaFilePath(\App\Models\Media $media): ?string
    {
        // Используем атрибут fullPath из модели Media
        $fullPath = $media->fullPath;
        
        if ($fullPath && file_exists($fullPath)) {
            return $fullPath;
        }

        // Альтернативный способ - через storage
        $metadata = is_string($media->metadata) 
            ? json_decode($media->metadata, true) 
            : $media->metadata;
        
        if (isset($metadata['path'])) {
            $storagePath = storage_path('app/public/' . ltrim($metadata['path'], '/'));
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        // Пытаемся через disk и name
        if ($media->disk && $media->name) {
            $storagePath = storage_path('app/public/' . ltrim($media->disk . '/' . $media->name, '/'));
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return null;
    }

    /**
     * Отправить материал
     */
    protected function sendMaterial(Bot $bot, BotUser $user, int $materialId): void
    {
        $result = $this->material->sendMaterial(
            $bot->token,
            $user->telegram_user_id,
            $materialId
        );

        if (!$result['success']) {
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                $result['message'] ?? 'Не удалось отправить материал'
            );
        }
    }

    /**
     * Показать описание консультации
     */
    protected function showConsultationDescription(Bot $bot, BotUser $user): void
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $consultation = $messages['consultation'] ?? [];

        $consultationDescription = $consultation['description'] ?? 
            "Если вашему бизнесу нужна профессиональная юридическая поддержка, АИП возьмёт на себя все правовые вопросы.\n\n" .
            "Обращаясь к нам, вы избавляетесь на развитии бизнеса, а не на юридических рисках.";
        
        // Проверяем, что значение является строкой, а не массивом
        $text = is_array($consultationDescription)
            ? "Если вашему бизнесу нужна профессиональная юридическая поддержка, АИП возьмёт на себя все правовые вопросы.\n\n" .
              "Обращаясь к нам, вы избавляетесь на развитии бизнеса, а не на юридических рисках."
            : (string) $consultationDescription;

        $keyboard = $this->menu->getConsultationKeyboard();

        $this->telegram->sendMessageWithKeyboard(
            $bot->token,
            $user->telegram_user_id,
            $text,
            $keyboard
        );

        $user->update(['current_state' => BotStates::CONSULTATION_DESCRIPTION]);
    }

    /**
     * Начать форму консультации
     */
    protected function startConsultationForm(Bot $bot, BotUser $user): void
    {
        $this->form->startConsultationForm($bot->id, $user->telegram_user_id);
        
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $consultation = $messages['consultation'] ?? [];

        $formNameLabel = $consultation['form_name_label'] ?? 'Введите ваше имя:';
        // Проверяем, что значение является строкой, а не массивом, и не пустое
        if (is_array($formNameLabel) || empty(trim((string) $formNameLabel))) {
            $text = 'Введите ваше имя:';
        } else {
            $text = trim((string) $formNameLabel);
        }
        
        // Дополнительная проверка на пустоту
        if (empty($text)) {
            $text = 'Введите ваше имя:';
        }
        
        Log::info('📝 Starting consultation form', [
            'bot_id' => $bot->id,
            'user_id' => $user->telegram_user_id,
            'form_name_label' => $formNameLabel,
            'text' => $text,
            'text_length' => strlen($text),
        ]);

        // Проверяем, есть ли reply кнопки, чтобы сохранить их во время заполнения формы
        $replyButtons = $settings['reply_buttons'] ?? [];
        $hasReplyButtons = !empty($replyButtons['materials_button_text']) 
            || !empty($replyButtons['consultation_button_text'])
            || !empty($replyButtons['office_button_text']);
        
        if ($hasReplyButtons) {
            // Отправляем с reply клавиатурой (сохраняем кнопки)
            $replyKeyboard = $this->buildReplyKeyboard($bot);
            $this->telegram->sendMessageWithReplyKeyboard(
                $bot->token,
                $user->telegram_user_id,
                $text,
                $replyKeyboard
            );
        } else {
            // Отправляем без клавиатуры
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $text);
        }

        $user->update(['current_state' => BotStates::CONSULTATION_FORM_NAME]);
    }

    /**
     * Обработать состояние пользователя
     */
    protected function handleState(Bot $bot, BotUser $user, string $text, array $message): void
    {
        $chatId = $message['chat']['id'] ?? $user->telegram_user_id;

        switch ($user->current_state) {
            case BotStates::CONSULTATION_FORM_NAME:
                $this->handleConsultationFormName($bot, $user, $text);
                break;

            case BotStates::CONSULTATION_FORM_PHONE:
                $this->handleConsultationFormPhone($bot, $user, $text);
                break;

            case BotStates::CONSULTATION_FORM_DESCRIPTION:
                $this->handleConsultationFormDescription($bot, $user, $text);
                break;

            default:
                // Неизвестное состояние
                $this->showMainMenu($bot, $user);
        }
    }

    /**
     * Обработать ввод имени
     */
    protected function handleConsultationFormName(Bot $bot, BotUser $user, string $text): void
    {
        $validation = $this->form->validateFormField('name', $text, $bot->settings ?? []);

        if (!$validation['valid']) {
            $errorMessage = "❌ " . implode("\n", $validation['errors']) . "\n\nПожалуйста, введите имя заново.";
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $errorMessage);
            return;
        }

        $this->form->saveFormField($bot->id, $user->telegram_user_id, 'name', $text);

        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $consultation = $messages['consultation'] ?? [];

        $formPhoneLabel = $consultation['form_phone_label'] ?? 'Введите ваш телефон:';
        // Проверяем, что значение является строкой, а не массивом, и не пустое
        if (is_array($formPhoneLabel) || empty(trim((string) $formPhoneLabel))) {
            $text = 'Введите ваш телефон:';
        } else {
            $text = trim((string) $formPhoneLabel);
        }
        $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $text);

        $user->update(['current_state' => BotStates::CONSULTATION_FORM_PHONE]);
    }

    /**
     * Обработать ввод телефона
     */
    protected function handleConsultationFormPhone(Bot $bot, BotUser $user, string $text): void
    {
        $validation = $this->form->validateFormField('phone', $text, $bot->settings ?? []);

        if (!$validation['valid']) {
            $errorMessage = "❌ " . implode("\n", $validation['errors']) . "\n\nПожалуйста, введите телефон заново.";
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $errorMessage);
            return;
        }

        $this->form->saveFormField($bot->id, $user->telegram_user_id, 'phone', $text);

        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $consultation = $messages['consultation'] ?? [];

        $skipButton = $consultation['skip_description_button'] ?? 'Пропустить';
        $formDescriptionLabel = $consultation['form_description_label'] ?? 'Краткое описание запроса (опционально, можете пропустить):';
        
        // Проверяем, что значения являются строками, а не массивами, и не пустые
        if (is_array($skipButton) || empty(trim((string) $skipButton))) {
            $skipButton = 'Пропустить';
        } else {
            $skipButton = trim((string) $skipButton);
        }
        
        if (is_array($formDescriptionLabel) || empty(trim((string) $formDescriptionLabel))) {
            $text = 'Краткое описание запроса (опционально, можете пропустить):';
        } else {
            $text = trim((string) $formDescriptionLabel);
        }

        // Отправляем сообщение с inline кнопкой "Пропустить"
        // Reply клавиатура остается активной (Telegram сохраняет ее до явного удаления)
        $keyboard = [
            [['text' => $skipButton, 'callback_data' => BotActions::CONSULTATION_SKIP_DESCRIPTION]]
        ];
        
        $this->telegram->sendMessageWithKeyboard($bot->token, $user->telegram_user_id, $text, $keyboard);

        $user->update(['current_state' => BotStates::CONSULTATION_FORM_DESCRIPTION]);
    }

    /**
     * Обработать ввод описания
     */
    protected function handleConsultationFormDescription(Bot $bot, BotUser $user, string $text): void
    {
        $validation = $this->form->validateFormField('description', $text, $bot->settings ?? []);

        if (!$validation['valid']) {
            $errorMessage = "❌ " . implode("\n", $validation['errors']) . "\n\nПожалуйста, введите описание заново.";
            $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $errorMessage);
            return;
        }

        $this->form->saveFormField($bot->id, $user->telegram_user_id, 'description', $text);
        $this->submitConsultation($bot, $user);
    }

    /**
     * Отправить заявку на консультацию
     */
    protected function submitConsultation(Bot $bot, BotUser $user): void
    {
        try {
            $consultation = $this->form->submitConsultationForm($bot->id, $user->telegram_user_id);

            $this->logger->logConsultationCreated($bot->id, $user->telegram_user_id, $consultation->id);

            // Уведомляем администраторов
            $this->notification->notifyNewConsultation($bot, $consultation);

            $settings = $bot->settings ?? [];
            $messages = $settings['messages'] ?? [];
            $consultationMsgs = $messages['consultation'] ?? [];

            $thankYouMessage = $consultationMsgs['thank_you'] ?? 
                'Спасибо. Мы свяжемся с вами в ближайшее время.';
            
            // Проверяем, что значение является строкой, а не массивом, и не пустое
            if (is_array($thankYouMessage) || empty(trim((string) $thankYouMessage))) {
                $thankYouMessage = 'Спасибо. Мы свяжемся с вами в ближайшее время.';
            } else {
                $thankYouMessage = trim((string) $thankYouMessage);
            }
            
            Log::info('✅ Consultation form submitted', [
                'bot_id' => $bot->id,
                'user_id' => $user->telegram_user_id,
                'consultation_id' => $consultation->id,
                'thank_you_message' => $thankYouMessage,
            ]);

            // Проверяем, есть ли reply кнопки, чтобы отправить сообщение с клавиатурой
            $replyButtons = $settings['reply_buttons'] ?? [];
            $hasReplyButtons = !empty($replyButtons['materials_button_text']) 
                || !empty($replyButtons['consultation_button_text'])
                || !empty($replyButtons['office_button_text']);
            
            if ($hasReplyButtons) {
                // Отправляем сообщение благодарности с reply клавиатурой
                $replyKeyboard = $this->buildReplyKeyboard($bot);
                $this->telegram->sendMessageWithReplyKeyboard(
                    $bot->token,
                    $user->telegram_user_id,
                    $thankYouMessage,
                    $replyKeyboard
                );
            } else {
                // Отправляем сообщение без клавиатуры
                $this->telegram->sendMessage($bot->token, $user->telegram_user_id, $thankYouMessage);
            }

            // Обновляем состояние пользователя на главное меню (без отправки приветственного сообщения)
            $user->update(['current_state' => BotStates::MAIN_MENU]);
        } catch (\Exception $e) {
            Log::error("Error submitting consultation: " . $e->getMessage());
            $this->telegram->sendMessage(
                $bot->token,
                $user->telegram_user_id,
                'Произошла ошибка при отправке заявки. Пожалуйста, попробуйте позже.'
            );
        }
    }
}

