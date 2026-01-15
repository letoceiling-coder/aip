<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $apiBaseUrl = 'https://api.telegram.org/bot';

    /**
     * Получить информацию о боте
     */
    public function getBotInfo(string $token): array
    {
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . $token . '/getMe');
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Неизвестная ошибка',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram getBotInfo error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Установить webhook
     */
    public function setWebhook(string $token, string $url, array $options = []): array
    {
        try {
            $params = array_merge([
                'url' => $url,
            ], $options);

            Log::info('📤 Sending setWebhook request to Telegram API', [
                'url' => $url,
                'options' => $options,
                'api_url' => $this->apiBaseUrl . $token . '/setWebhook',
            ]);

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/setWebhook', $params);
            
            Log::info('📥 Telegram API setWebhook response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    Log::info('✅ Webhook set successfully', [
                        'url' => $url,
                        'result' => $data['result'] ?? [],
                    ]);
                    return [
                        'success' => true,
                        'message' => 'Webhook успешно установлен',
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                Log::error('❌ Telegram API returned error', [
                    'url' => $url,
                    'description' => $data['description'] ?? 'Unknown error',
                    'error_code' => $data['error_code'] ?? null,
                ]);
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось установить webhook',
                ];
            }
            
            Log::error('❌ HTTP error when setting webhook', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('❌ Exception when setting webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить информацию о webhook
     */
    public function getWebhookInfo(string $token): array
    {
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . $token . '/getWebhookInfo');
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    $webhookInfo = $data['result'] ?? [];
                    
                    return [
                        'success' => true,
                        'data' => [
                            'url' => $webhookInfo['url'] ?? null,
                            'has_custom_certificate' => $webhookInfo['has_custom_certificate'] ?? false,
                            'pending_update_count' => $webhookInfo['pending_update_count'] ?? 0,
                            'last_error_date' => $webhookInfo['last_error_date'] ?? null,
                            'last_error_message' => $webhookInfo['last_error_message'] ?? null,
                            'max_connections' => $webhookInfo['max_connections'] ?? null,
                            'allowed_updates' => $webhookInfo['allowed_updates'] ?? [],
                        ],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось получить информацию о webhook',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram getWebhookInfo error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook(string $token, bool $dropPendingUpdates = false): array
    {
        try {
            $params = [];
            if ($dropPendingUpdates) {
                $params['drop_pending_updates'] = true;
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/deleteWebhook', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'message' => 'Webhook успешно удален',
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось удалить webhook',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram deleteWebhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить сообщение
     */
    public function sendMessage(string $token, int|string $chatId, string $text, array $options = []): array
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $options);

            Log::info('📤 Sending message via Telegram API', [
                'chat_id' => $chatId,
                'text_length' => strlen($text),
                'has_options' => !empty($options),
            ]);

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/sendMessage', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    Log::info('✅ Message sent successfully', [
                        'chat_id' => $chatId,
                        'message_id' => $data['result']['message_id'] ?? null,
                    ]);
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                Log::error('❌ Telegram API error', [
                    'chat_id' => $chatId,
                    'description' => $data['description'] ?? 'Unknown error',
                ]);
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить сообщение',
                ];
            }
            
            Log::error('❌ HTTP error sending message', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('❌ Telegram sendMessage error: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить сообщение с клавиатурой
     */
    public function sendMessageWithKeyboard(
        string $token,
        int|string $chatId,
        string $text,
        array $keyboard = [],
        array $options = []
    ): array {
        // Валидация и очистка клавиатуры
        $cleanedKeyboard = [];
        foreach ($keyboard as $row) {
            $cleanedRow = [];
            foreach ($row as $button) {
                if (!isset($button['text']) || empty($button['text'])) {
                    Log::warning('⚠️ Skipping button with empty or missing text', ['button' => $button]);
                    continue;
                }
                
                $cleanedButton = [
                    'text' => (string) $button['text'],
                ];
                
                if (isset($button['url'])) {
                    $cleanedButton['url'] = (string) $button['url'];
                } elseif (isset($button['callback_data'])) {
                    $cleanedButton['callback_data'] = (string) $button['callback_data'];
                }
                
                $cleanedRow[] = $cleanedButton;
            }
            
            if (!empty($cleanedRow)) {
                $cleanedKeyboard[] = $cleanedRow;
            }
        }
        
        $params = array_merge($options, [
            'reply_markup' => !empty($cleanedKeyboard) ? json_encode([
                'inline_keyboard' => $cleanedKeyboard,
            ]) : null,
        ]);
        
        Log::info('📤 Sending message with keyboard', [
            'chat_id' => $chatId,
            'keyboard_rows' => count($cleanedKeyboard),
            'keyboard' => $cleanedKeyboard,
        ]);
        
        return $this->sendMessage($token, $chatId, $text, $params);
    }

    /**
     * Отправить сообщение с reply клавиатурой (кнопки под полем ввода)
     */
    public function sendMessageWithReplyKeyboard(
        string $token,
        int|string $chatId,
        string $text,
        array $keyboard = [],
        bool $resizeKeyboard = true,
        bool $oneTimeKeyboard = false,
        array $options = []
    ): array {
        // Валидация и очистка клавиатуры
        $cleanedKeyboard = [];
        foreach ($keyboard as $row) {
            $cleanedRow = [];
            foreach ($row as $button) {
                if (!isset($button['text']) || empty($button['text'])) {
                    Log::warning('⚠️ Skipping reply button with empty or missing text', ['button' => $button]);
                    continue;
                }
                
                $cleanedRow[] = [
                    'text' => (string) $button['text'],
                ];
            }
            
            if (!empty($cleanedRow)) {
                $cleanedKeyboard[] = $cleanedRow;
            }
        }
        
        $replyMarkup = null;
        if (!empty($cleanedKeyboard)) {
            $replyMarkup = [
                'keyboard' => $cleanedKeyboard,
                'resize_keyboard' => $resizeKeyboard,
                'one_time_keyboard' => $oneTimeKeyboard,
            ];
        }
        
        $params = array_merge($options, [
            'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
        ]);
        
        Log::info('📤 Sending message with reply keyboard', [
            'chat_id' => $chatId,
            'keyboard_rows' => count($cleanedKeyboard),
            'keyboard' => $cleanedKeyboard,
        ]);
        
        return $this->sendMessage($token, $chatId, $text, $params);
    }

    /**
     * Удалить reply клавиатуру
     */
    public function removeReplyKeyboard(
        string $token,
        int|string $chatId,
        string $text = '',
        array $options = []
    ): array {
        $params = array_merge($options, [
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
            ]),
        ]);
        
        return $this->sendMessage($token, $chatId, $text, $params);
    }

    /**
     * Отправить локацию (карту)
     */
    public function sendLocation(
        string $token,
        int|string $chatId,
        float $latitude,
        float $longitude,
        array $options = []
    ): array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ], $options);

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/sendLocation', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить локацию',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendLocation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить документ
     */
    public function sendDocument(
        string $token,
        int|string $chatId,
        string $filePath,
        ?string $caption = null,
        array $options = []
    ): array {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'Файл не найден',
                ];
            }

            $params = [
                'chat_id' => $chatId,
            ];
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $params = array_merge($params, $options);

            // Используем multipart/form-data для загрузки файла
            $response = Http::timeout(30)
                ->attach('document', file_get_contents($filePath), basename($filePath))
                ->asMultipart()
                ->post($this->apiBaseUrl . $token . '/sendDocument', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить документ',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendDocument error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить документ по file_id (Telegram)
     */
    public function sendDocumentByFileId(
        string $token,
        int|string $chatId,
        string $fileId,
        ?string $caption = null
    ): array {
        try {
            $params = [
                'chat_id' => $chatId,
                'document' => $fileId,
            ];
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/sendDocument', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить документ',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendDocumentByFileId error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить информацию об участнике чата/канала
     */
    public function getChatMember(
        string $token,
        int|string $chatId,
        int $userId
    ): array {
        try {
            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/getChatMember', [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось получить информацию об участнике',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram getChatMember error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ответить на callback_query
     */
    public function answerCallbackQuery(
        string $token,
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): array {
        try {
            $params = [
                'callback_query_id' => $callbackQueryId,
            ];
            
            if ($text !== null) {
                $params['text'] = $text;
            }
            
            if ($showAlert) {
                $params['show_alert'] = true;
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/answerCallbackQuery', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось ответить на callback_query',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram answerCallbackQuery error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить фото
     */
    public function sendPhoto(
        string $token,
        int|string $chatId,
        string $photoPath,
        ?string $caption = null,
        array $options = []
    ): array {
        try {
            if (!file_exists($photoPath)) {
                return [
                    'success' => false,
                    'message' => 'Файл не найден',
                ];
            }

            $params = [
                'chat_id' => $chatId,
            ];
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $params = array_merge($params, $options);

            $response = Http::timeout(30)
                ->attach('photo', file_get_contents($photoPath), basename($photoPath))
                ->asMultipart()
                ->post($this->apiBaseUrl . $token . '/sendPhoto', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить фото',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendPhoto error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить фото по file_id (Telegram)
     */
    public function sendPhotoByFileId(
        string $token,
        int|string $chatId,
        string $fileId,
        ?string $caption = null,
        array $options = []
    ): array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'photo' => $fileId,
            ], $options);
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/sendPhoto', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить фото',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendPhotoByFileId error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить видео
     */
    public function sendVideo(
        string $token,
        int|string $chatId,
        string $videoPath,
        ?string $caption = null,
        array $options = []
    ): array {
        try {
            if (!file_exists($videoPath)) {
                return [
                    'success' => false,
                    'message' => 'Файл не найден',
                ];
            }

            $params = [
                'chat_id' => $chatId,
            ];
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $params = array_merge($params, $options);

            $response = Http::timeout(60)
                ->attach('video', file_get_contents($videoPath), basename($videoPath))
                ->asMultipart()
                ->post($this->apiBaseUrl . $token . '/sendVideo', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить видео',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendVideo error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить видео по file_id (Telegram)
     */
    public function sendVideoByFileId(
        string $token,
        int|string $chatId,
        string $fileId,
        ?string $caption = null,
        array $options = []
    ): array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'video' => $fileId,
            ], $options);
            
            if ($caption !== null) {
                $params['caption'] = $caption;
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/sendVideo', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить видео',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendVideoByFileId error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Отправить медиа-группу (галерея фото/видео)
     */
    public function sendMediaGroup(
        string $token,
        int|string $chatId,
        array $media,
        array $options = []
    ): array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'media' => json_encode($media),
            ], $options);

            $response = Http::timeout(60)->post($this->apiBaseUrl . $token . '/sendMediaGroup', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отправить медиа-группу',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendMediaGroup error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Редактировать текст сообщения
     */
    public function editMessageText(
        string $token,
        int|string $chatId,
        int $messageId,
        string $text,
        array $keyboard = [],
        array $options = []
    ): array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
            ], $options);
            
            if (!empty($keyboard)) {
                $params['reply_markup'] = json_encode([
                    'inline_keyboard' => $keyboard,
                ]);
            }

            $response = Http::timeout(10)->post($this->apiBaseUrl . $token . '/editMessageText', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'data' => $data['result'] ?? [],
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Не удалось отредактировать сообщение',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Ошибка подключения к Telegram API',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram editMessageText error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }
}

