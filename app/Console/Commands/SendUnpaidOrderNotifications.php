<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendUnpaidOrderNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:notify-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправка уведомлений пользователям о неоплаченных заказах через Telegram-бота';

    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        parent::__construct();
        $this->telegram = $telegram;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем проверку неоплаченных заказов...');

        $bots = Bot::where('is_active', true)->get();
        $totalProcessed = 0;
        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($bots as $bot) {
            $settings = $bot->settings ?? [];
            $otherSettings = $settings['other_settings'] ?? [];

            // Проверяем, включены ли уведомления
            $notificationsEnabled = $otherSettings['unpaidNotificationsEnabled'] ?? true;
            if (!$notificationsEnabled) {
                $this->line("Уведомления отключены для бота ID: {$bot->id}");
                continue;
            }

            $notifyAfterMinutes = $otherSettings['unpaidNotifyAfterMinutes'] ?? 30;
            
            $this->line("Обработка бота ID: {$bot->id} (уведомление через {$notifyAfterMinutes} минут)");

            // TODO: Здесь должна быть логика работы с заказами из БД
            // На данный момент заказы хранятся в localStorage на фронтенде
            // Эта команда готова для интеграции с БД, когда заказы будут перенесены на сервер
            
            // Пример логики для будущей интеграции:
            /*
            $cutoffTime = Carbon::now()->subMinutes($notifyAfterMinutes);
            
            $unpaidOrders = Order::where('bot_id', $bot->id)
                ->where('status', 'waiting_payment')
                ->where('created_at', '<=', $cutoffTime)
                ->whereNull('unpaid_notified_at') // Не отправляем повторно
                ->whereNull('paid_at') // Заказ еще не оплачен
                ->with('botUser')
                ->get();

            foreach ($unpaidOrders as $order) {
                if (!$order->botUser || !$order->botUser->telegram_user_id) {
                    $totalSkipped++;
                    continue;
                }

                $message = $this->formatUnpaidOrderMessage($bot, $order);
                
                $result = $this->telegram->sendMessage(
                    $bot->token,
                    $order->botUser->telegram_user_id,
                    $message,
                    ['parse_mode' => 'HTML']
                );

                if ($result['success'] ?? false) {
                    $order->update(['unpaid_notified_at' => now()]);
                    $totalSent++;
                    $this->info("✅ Уведомление отправлено пользователю {$order->botUser->telegram_user_id} (заказ #{$order->id})");
                } else {
                    $this->error("❌ Ошибка отправки уведомления для заказа #{$order->id}: " . ($result['message'] ?? 'Unknown error'));
                }
                
                $totalProcessed++;
            }
            */

            Log::info('Unpaid order notifications check executed', [
                'bot_id' => $bot->id,
                'notify_after_minutes' => $notifyAfterMinutes,
                'notifications_enabled' => $notificationsEnabled,
            ]);

            $totalProcessed++;
        }

        $this->info("Обработано ботов: {$totalProcessed}");
        $this->info("Отправлено уведомлений: {$totalSent}");
        $this->info("Пропущено: {$totalSkipped}");

        $this->info('Проверка завершена.');

        // Примечание: Фактическая отправка уведомлений будет работать после переноса заказов в БД
        // Или можно использовать API endpoint для получения заказов из localStorage и отправки уведомлений

        return Command::SUCCESS;
    }

    /**
     * Форматировать сообщение о неоплаченном заказе
     */
    protected function formatUnpaidOrderMessage(Bot $bot, $order): string
    {
        $message = "⏰ <b>Напоминание об оплате</b>\n\n";
        $message .= "У вас есть неоплаченный заказ #{$order->id}\n\n";
        
        if (isset($order->property_title)) {
            $message .= "🏠 <b>Объект:</b> " . htmlspecialchars($order->property_title, ENT_QUOTES, 'UTF-8') . "\n";
        }
        
        if (isset($order->property_price)) {
            $price = number_format($order->property_price, 0, ',', ' ') . ' ₽';
            $message .= "💰 <b>Сумма:</b> {$price}\n";
        }
        
        $message .= "\nПожалуйста, завершите оплату заказа.\n\n";
        $message .= "🤖 <b>Бот:</b> " . htmlspecialchars($bot->name ?? '', ENT_QUOTES, 'UTF-8');

        return $message;
    }
}

