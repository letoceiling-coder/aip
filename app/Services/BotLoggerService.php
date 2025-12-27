<?php

namespace App\Services;

use App\Models\BotLog;
use Illuminate\Support\Facades\Log;

class BotLoggerService
{
    /**
     * Логировать сообщение
     */
    public function logMessage(
        int $botId,
        ?int $telegramUserId,
        array $update,
        string $action,
        ?string $error = null
    ): void {
        $this->logToDatabase($botId, $telegramUserId, $update, 'message', $action, $error);
        $this->logToFile($botId, $telegramUserId, 'message', $action, $update, $error);
    }

    /**
     * Логировать callback_query
     */
    public function logCallbackQuery(
        int $botId,
        ?int $telegramUserId,
        array $update,
        string $action,
        ?string $error = null
    ): void {
        $this->logToDatabase($botId, $telegramUserId, $update, 'callback_query', $action, $error);
        $this->logToFile($botId, $telegramUserId, 'callback_query', $action, $update, $error);
    }

    /**
     * Логировать проверку подписки
     */
    public function logSubscriptionCheck(int $botId, int $telegramUserId, bool $isSubscribed): void
    {
        $this->logToDatabase(
            $botId,
            $telegramUserId,
            [],
            'subscription_check',
            'check_subscription',
            null,
            $isSubscribed ? 'success' : 'failed'
        );
        
        $this->logToFile(
            $botId,
            $telegramUserId,
            'subscription_check',
            'check_subscription',
            ['is_subscribed' => $isSubscribed]
        );
    }

    /**
     * Логировать создание заявки
     */
    public function logConsultationCreated(int $botId, int $telegramUserId, int $consultationId): void
    {
        $this->logToDatabase(
            $botId,
            $telegramUserId,
            ['consultation_id' => $consultationId],
            'consultation_created',
            'consultation_submit',
            null,
            'success'
        );
        
        $this->logToFile(
            $botId,
            $telegramUserId,
            'consultation_created',
            'consultation_submit',
            ['consultation_id' => $consultationId]
        );
    }

    /**
     * Логировать в базу данных
     */
    protected function logToDatabase(
        int $botId,
        ?int $telegramUserId,
        array $data,
        string $eventType,
        string $action,
        ?string $error = null,
        ?string $status = null
    ): void {
        try {
            BotLog::create([
                'bot_id' => $botId,
                'telegram_user_id' => $telegramUserId,
                'update_id' => $data['update_id'] ?? null,
                'event_type' => $eventType,
                'action' => $action,
                'data' => $data,
                'response_status' => $status ?? ($error ? 'error' : 'success'),
                'error_message' => $error,
            ]);
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке логирования
            Log::error('Failed to log to database: ' . $e->getMessage());
        }
    }

    /**
     * Логировать в файл
     */
    protected function logToFile(
        int $botId,
        ?int $telegramUserId,
        string $eventType,
        string $action,
        array $data = [],
        ?string $error = null
    ): void {
        try {
            $logData = [
                'bot_id' => $botId,
                'telegram_user_id' => $telegramUserId,
                'event_type' => $eventType,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
            ];

            if ($error) {
                $logData['error'] = $error;
                Log::channel('bot')->error("Bot {$botId} event", $logData);
            } else {
                Log::channel('bot')->info("Bot {$botId} event", $logData);
            }
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке логирования
            Log::error('Failed to log to file: ' . $e->getMessage());
        }
    }
}

