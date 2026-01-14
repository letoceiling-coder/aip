<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotConsultation;
use Illuminate\Support\Facades\Log;

class BotNotificationService
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Уведомить администраторов о новой заявке
     */
    public function notifyNewConsultation(Bot $bot, BotConsultation $consultation): void
    {
        $adminIds = $bot->admin_telegram_ids ?? [];
        
        if (empty($adminIds)) {
            return;
        }

        $message = $this->formatConsultationMessage($bot, $consultation);
        
        foreach ($adminIds as $adminId) {
            try {
                $this->telegram->sendMessage(
                    $bot->token,
                    $adminId,
                    $message,
                    ['parse_mode' => 'HTML']
                );
            } catch (\Exception $e) {
                Log::error("Failed to send notification to admin {$adminId}: " . $e->getMessage(), [
                    'bot_id' => $bot->id,
                    'consultation_id' => $consultation->id,
                ]);
            }
        }
        
        // Обновляем флаг уведомления
        $consultation->update([
            'telegram_notified' => true,
            'telegram_notified_at' => now(),
        ]);
    }

    /**
     * Форматировать сообщение о заявке
     */
    protected function formatConsultationMessage(Bot $bot, BotConsultation $consultation): string
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $notifications = $messages['notifications'] ?? [];
        
        $template = $notifications['consultation_template'] ?? 
            "Новая заявка на консультацию\n\nИмя: {name}\nТелефон: {phone}\nОписание: {description}\nДата: {date}";
        
        $date = $consultation->created_at->format('d.m.Y H:i');
        $description = $consultation->description ?: '(не указано)';
        
        $message = str_replace(
            ['{name}', '{phone}', '{description}', '{date}'],
            [
                htmlspecialchars($consultation->name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($consultation->phone, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                $date
            ],
            $template
        );
        
        return $message;
    }
}


