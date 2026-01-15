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
        // Получаем всех администраторов из всех ботов
        $adminIds = $this->getAllAdminTelegramIds($bot);
        
        if (empty($adminIds)) {
            Log::warning("No admin Telegram IDs found for consultation notification", [
                'bot_id' => $bot->id,
                'consultation_id' => $consultation->id,
            ]);
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
     * Получить все Telegram ID администраторов
     * Собирает ID из всех ботов и пользователей с ролью admin
     */
    protected function getAllAdminTelegramIds(Bot $bot): array
    {
        $adminIds = [];
        
        // 1. Получаем admin_telegram_ids из всех ботов
        $bots = \App\Models\Bot::where('is_active', true)->get();
        foreach ($bots as $botItem) {
            $botAdminIds = $botItem->admin_telegram_ids ?? [];
            if (is_array($botAdminIds)) {
                $adminIds = array_merge($adminIds, $botAdminIds);
            }
        }
        
        // 2. Получаем Telegram ID из пользователей с ролью admin через BotUser
        // Находим всех пользователей с ролью admin
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->get();
        
        // Для каждого администратора ищем его BotUser записи
        foreach ($adminUsers as $adminUser) {
            // Ищем BotUser по username (если username администратора совпадает с username в BotUser)
            // Или по email (если email администратора совпадает с username в BotUser)
            $emailUsername = str_replace('@', '', $adminUser->email ?? '');
            
            $botUsers = \App\Models\BotUser::where(function ($query) use ($adminUser, $emailUsername) {
                if ($adminUser->email) {
                    $query->where('username', $emailUsername)
                          ->orWhere('username', $adminUser->email);
                }
            })->get();
            
            foreach ($botUsers as $botUser) {
                $adminIds[] = $botUser->telegram_user_id;
            }
        }
        
        // Убираем дубликаты и пустые значения
        $adminIds = array_unique(array_filter($adminIds));
        
        return array_values($adminIds);
    }

    /**
     * Форматировать сообщение о заявке
     */
    protected function formatConsultationMessage(Bot $bot, BotConsultation $consultation): string
    {
        $settings = $bot->settings ?? [];
        $messages = $settings['messages'] ?? [];
        $notifications = $messages['notifications'] ?? [];
        
        // Получаем информацию о пользователе
        $botUser = \App\Models\BotUser::where('bot_id', $bot->id)
            ->where('telegram_user_id', $consultation->telegram_user_id)
            ->first();
        
        $userInfo = '';
        if ($botUser) {
            $userInfo = "\n👤 <b>Пользователь:</b> " . htmlspecialchars($botUser->full_name, ENT_QUOTES, 'UTF-8');
            if ($botUser->username) {
                $userInfo .= " (@{$botUser->username})";
            }
            $userInfo .= "\n🆔 <b>Telegram ID:</b> {$consultation->telegram_user_id}";
        }
        
        $defaultTemplate = "🔔 <b>Новая заявка на консультацию</b>\n\n" .
            "📋 <b>Имя:</b> {name}\n" .
            "📞 <b>Телефон:</b> {phone}\n" .
            "📝 <b>Описание:</b> {description}\n" .
            "📅 <b>Дата:</b> {date}\n" .
            "🤖 <b>Бот:</b> {bot_name}{user_info}";
        
        $template = $notifications['consultation_template'] ?? $defaultTemplate;
        
        // Проверяем, что шаблон является строкой, а не массивом
        $template = is_array($template) ? $defaultTemplate : (string) $template;
        
        $date = $consultation->created_at->format('d.m.Y H:i');
        $description = $consultation->description ?: '(не указано)';
        
        $message = str_replace(
            ['{name}', '{phone}', '{description}', '{date}', '{bot_name}', '{user_info}'],
            [
                htmlspecialchars($consultation->name ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($consultation->phone ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                $date,
                htmlspecialchars($bot->name ?? '', ENT_QUOTES, 'UTF-8'),
                $userInfo
            ],
            $template
        );
        
        return (string) $message;
    }
}


