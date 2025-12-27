<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotSubscription;
use App\Models\BotUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotSubscriptionService
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Проверить подписку пользователя на канал
     */
    public function checkSubscription(int $botId, int $telegramUserId): bool
    {
        $bot = Bot::find($botId);
        if (!$bot) {
            return false;
        }

        $channelId = $bot->required_channel_id;
        $channelUsername = $bot->required_channel_username;

        if (!$channelId && !$channelUsername) {
            // Подписка не требуется
            return true;
        }

        // Используем ID канала, если есть, иначе username
        $chatId = $channelId ?: ($channelUsername ? '@' . $channelUsername : null);

        if (!$chatId) {
            return false;
        }

        // Кэшируем результат на 5 минут
        $cacheKey = "bot_subscription_{$botId}_{$telegramUserId}";
        
        return Cache::remember($cacheKey, 300, function () use ($bot, $telegramUserId, $chatId, $botId) {
            $result = $this->getChannelMember($bot->token, $chatId, $telegramUserId);
            
            $isSubscribed = $result['success'] && 
                isset($result['data']['status']) && 
                in_array($result['data']['status'], ['member', 'administrator', 'creator']);
            
            // Сохраняем результат проверки
            $this->saveSubscriptionCheck($botId, $telegramUserId, $chatId, $isSubscribed);
            
            return $isSubscribed;
        });
    }

    /**
     * Получить информацию об участнике канала
     */
    public function getChannelMember(string $token, int|string $chatId, int $userId): array
    {
        return $this->telegram->getChatMember($token, $chatId, $userId);
    }

    /**
     * Сохранить результат проверки подписки
     */
    public function saveSubscriptionCheck(int $botId, int $telegramUserId, int|string $chatId, bool $isSubscribed): void
    {
        try {
            $bot = Bot::find($botId);
            if (!$bot) {
                return;
            }

            // Получаем username канала, если это username
            $channelUsername = null;
            $channelIdValue = null;

            if (is_string($chatId) && str_starts_with($chatId, '@')) {
                $channelUsername = substr($chatId, 1);
                $channelIdValue = -1; // Для username используем -1
            } elseif (is_numeric($chatId)) {
                $channelIdValue = (int)$chatId;
            } else {
                // Если это username без @, получаем из настроек бота
                $channelUsername = $bot->required_channel_username;
                $channelIdValue = -1;
            }

            BotSubscription::create([
                'bot_id' => $botId,
                'telegram_user_id' => $telegramUserId,
                'channel_id' => $channelIdValue ?? -1,
                'channel_username' => $channelUsername ?? $bot->required_channel_username,
                'is_subscribed' => $isSubscribed,
                'checked_at' => now(),
            ]);

            // Обновляем статус подписки у пользователя
            BotUser::where('bot_id', $botId)
                ->where('telegram_user_id', $telegramUserId)
                ->update([
                    'is_subscribed' => $isSubscribed,
                    'subscription_checked_at' => now(),
                ]);
        } catch (\Exception $e) {
            Log::error("Error saving subscription check: " . $e->getMessage(), [
                'bot_id' => $botId,
                'telegram_user_id' => $telegramUserId,
            ]);
        }
    }
}

