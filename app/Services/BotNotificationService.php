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
     * ОБЯЗАТЕЛЬНО отправляет уведомления всем администраторам
     */
    public function notifyNewConsultation(Bot $bot, BotConsultation $consultation): void
    {
        // Получаем всех администраторов из всех ботов
        $adminIds = $this->getAllAdminTelegramIds($bot);
        
        if (empty($adminIds)) {
            // КРИТИЧЕСКАЯ ОШИБКА: нет администраторов для уведомления
            Log::error("❌ CRITICAL: No admin Telegram IDs found for consultation notification", [
                'bot_id' => $bot->id,
                'bot_name' => $bot->name,
                'consultation_id' => $consultation->id,
                'consultation_name' => $consultation->name,
                'consultation_phone' => $consultation->phone,
            ]);
            
            // Все равно обновляем флаг, но с пометкой об ошибке
            $consultation->update([
                'telegram_notified' => false,
                'telegram_notified_at' => null,
            ]);
            
            return;
        }

        $message = $this->formatConsultationMessage($bot, $consultation);
        
        // Проверяем, что сообщение не пустое
        if (empty(trim($message))) {
            Log::error('❌ CRITICAL: Consultation message is empty after formatting', [
                'bot_id' => $bot->id,
                'consultation_id' => $consultation->id,
            ]);
            
            // Используем минимальный шаблон
            $message = "🔔 <b>Новая заявка на консультацию</b>\n\n" .
                "📋 <b>Имя:</b> " . htmlspecialchars($consultation->name ?? 'Не указано', ENT_QUOTES, 'UTF-8') . "\n" .
                "📞 <b>Телефон:</b> " . htmlspecialchars($consultation->phone ?? 'Не указано', ENT_QUOTES, 'UTF-8') . "\n" .
                "📝 <b>Описание:</b> " . htmlspecialchars($consultation->description ?: '(не указано)', ENT_QUOTES, 'UTF-8') . "\n" .
                "📅 <b>Дата:</b> " . $consultation->created_at->format('d.m.Y H:i') . "\n" .
                "🤖 <b>Бот:</b> " . htmlspecialchars($bot->name ?? 'Не указано', ENT_QUOTES, 'UTF-8');
        }
        
        $successCount = 0;
        $failCount = 0;
        
        Log::info('📤 Sending consultation notifications to admins', [
            'bot_id' => $bot->id,
            'consultation_id' => $consultation->id,
            'admin_count' => count($adminIds),
            'admin_ids' => $adminIds,
            'message_length' => strlen($message),
        ]);
        
        foreach ($adminIds as $adminId) {
            try {
                $result = $this->telegram->sendMessage(
                    $bot->token,
                    $adminId,
                    $message,
                    ['parse_mode' => 'HTML']
                );
                
                if ($result['success'] ?? false) {
                    $successCount++;
                    Log::info("✅ Notification sent to admin", [
                        'bot_id' => $bot->id,
                        'consultation_id' => $consultation->id,
                        'admin_id' => $adminId,
                    ]);
                } else {
                    $failCount++;
                    Log::error("❌ Failed to send notification to admin", [
                        'bot_id' => $bot->id,
                        'consultation_id' => $consultation->id,
                        'admin_id' => $adminId,
                        'error' => $result['message'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                $failCount++;
                Log::error("❌ Exception sending notification to admin {$adminId}: " . $e->getMessage(), [
                    'bot_id' => $bot->id,
                    'consultation_id' => $consultation->id,
                    'admin_id' => $adminId,
                    'exception' => $e->getTraceAsString(),
                ]);
            }
        }
        
        // Обновляем флаг уведомления только если хотя бы одно уведомление отправлено успешно
        if ($successCount > 0) {
            $consultation->update([
                'telegram_notified' => true,
                'telegram_notified_at' => now(),
            ]);
            
            Log::info('✅ Consultation notifications completed', [
                'bot_id' => $bot->id,
                'consultation_id' => $consultation->id,
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'total_admins' => count($adminIds),
            ]);
        } else {
            Log::error('❌ CRITICAL: Failed to send consultation notifications to all admins', [
                'bot_id' => $bot->id,
                'consultation_id' => $consultation->id,
                'fail_count' => $failCount,
                'total_admins' => count($adminIds),
            ]);
        }
    }

    /**
     * Получить все Telegram ID администраторов
     * Собирает ID из всех ботов и пользователей с ролью admin
     * ОБЯЗАТЕЛЬНО находит всех администраторов
     */
    protected function getAllAdminTelegramIds(Bot $bot): array
    {
        $adminIds = [];
        
        // 1. Получаем admin_telegram_ids из всех активных ботов
        $bots = \App\Models\Bot::where('is_active', true)->get();
        foreach ($bots as $botItem) {
            $botAdminIds = $botItem->admin_telegram_ids ?? [];
            if (is_array($botAdminIds) && !empty($botAdminIds)) {
                $adminIds = array_merge($adminIds, $botAdminIds);
                Log::info('📋 Found admin IDs from bot settings', [
                    'bot_id' => $botItem->id,
                    'bot_name' => $botItem->name,
                    'admin_ids' => $botAdminIds,
                ]);
            }
        }
        
        // 2. Получаем Telegram ID из пользователей с ролью admin через BotUser
        // Находим ВСЕХ пользователей с ролью admin
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->get();
        
        Log::info('👥 Found admin users in system', [
            'admin_count' => $adminUsers->count(),
            'admin_emails' => $adminUsers->pluck('email')->toArray(),
        ]);
        
        // Для каждого администратора ищем его BotUser записи
        foreach ($adminUsers as $adminUser) {
            $foundTelegramIds = [];
            
            // Способ 1: Ищем BotUser по email (email администратора может совпадать с username в BotUser)
            if ($adminUser->email) {
                $emailUsername = str_replace('@', '', $adminUser->email);
                
                $botUsers = \App\Models\BotUser::where(function ($query) use ($adminUser, $emailUsername) {
                    $query->where('username', $emailUsername)
                          ->orWhere('username', $adminUser->email)
                          ->orWhere('username', 'like', '%' . $emailUsername . '%');
                })->get();
                
                foreach ($botUsers as $botUser) {
                    if ($botUser->telegram_user_id) {
                        $foundTelegramIds[] = $botUser->telegram_user_id;
                    }
                }
            }
            
            // Способ 2: Ищем BotUser по имени (если имя администратора совпадает с именем в BotUser)
            if ($adminUser->name) {
                $botUsersByName = \App\Models\BotUser::where(function ($query) use ($adminUser) {
                    $query->where('first_name', 'like', '%' . $adminUser->name . '%')
                          ->orWhere('last_name', 'like', '%' . $adminUser->name . '%');
                })->get();
                
                foreach ($botUsersByName as $botUser) {
                    if ($botUser->telegram_user_id && !in_array($botUser->telegram_user_id, $foundTelegramIds)) {
                        $foundTelegramIds[] = $botUser->telegram_user_id;
                    }
                }
            }
            
            // Способ 3: Если email администратора в формате telegram_{id}@telegram.local, извлекаем ID
            if ($adminUser->email && preg_match('/telegram_(\d+)@telegram\.local/', $adminUser->email, $matches)) {
                $telegramId = (int) $matches[1];
                if ($telegramId && !in_array($telegramId, $foundTelegramIds)) {
                    $foundTelegramIds[] = $telegramId;
                }
            }
            
            if (!empty($foundTelegramIds)) {
                $adminIds = array_merge($adminIds, $foundTelegramIds);
                Log::info('✅ Found Telegram IDs for admin user', [
                    'admin_email' => $adminUser->email,
                    'admin_name' => $adminUser->name,
                    'telegram_ids' => $foundTelegramIds,
                ]);
            } else {
                Log::warning('⚠️ Admin user has no associated Telegram ID', [
                    'admin_email' => $adminUser->email,
                    'admin_name' => $adminUser->name,
                    'admin_id' => $adminUser->id,
                ]);
            }
        }
        
        // Убираем дубликаты и пустые значения, оставляем только числовые ID
        $adminIds = array_filter($adminIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        $adminIds = array_unique($adminIds);
        $adminIds = array_values($adminIds);
        
        Log::info('📊 Total admin Telegram IDs collected', [
            'total_count' => count($adminIds),
            'admin_ids' => $adminIds,
        ]);
        
        return $adminIds;
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
        
        // Проверяем, что шаблон является строкой, а не массивом, и не пустой
        if (is_array($template) || empty(trim((string) $template))) {
            $template = $defaultTemplate;
        } else {
            $template = trim((string) $template);
        }
        
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
        
        $message = trim((string) $message);
        
        // Дополнительная проверка: если сообщение пустое, используем минимальный шаблон
        if (empty($message)) {
            Log::warning('⚠️ Generated consultation message is empty, using fallback', [
                'bot_id' => $bot->id,
                'consultation_id' => $consultation->id,
                'template' => $template,
            ]);
            
            $message = "🔔 <b>Новая заявка на консультацию</b>\n\n" .
                "📋 <b>Имя:</b> " . htmlspecialchars($consultation->name ?? 'Не указано', ENT_QUOTES, 'UTF-8') . "\n" .
                "📞 <b>Телефон:</b> " . htmlspecialchars($consultation->phone ?? 'Не указано', ENT_QUOTES, 'UTF-8') . "\n" .
                "📝 <b>Описание:</b> " . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "\n" .
                "📅 <b>Дата:</b> {$date}\n" .
                "🤖 <b>Бот:</b> " . htmlspecialchars($bot->name ?? 'Не указано', ENT_QUOTES, 'UTF-8') . $userInfo;
        }
        
        Log::info('📝 Consultation message formatted', [
            'bot_id' => $bot->id,
            'consultation_id' => $consultation->id,
            'message_length' => strlen($message),
        ]);
        
        return $message;
    }
}


