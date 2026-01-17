<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupCancelledOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cleanup-cancelled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистка отменённых заказов старше TTL согласно настройкам ботов';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем очистку отменённых заказов...');

        $bots = Bot::all();
        $totalProcessed = 0;
        $totalHidden = 0;
        $totalDeleted = 0;

        foreach ($bots as $bot) {
            $settings = $bot->settings ?? [];
            $otherSettings = $settings['other_settings'] ?? [];

            $ttlDays = $otherSettings['canceledOrdersTtlDays'] ?? 7;
            $action = $otherSettings['canceledOrdersAfterTtlAction'] ?? 'hide';

            $this->line("Обработка бота ID: {$bot->id} (TTL: {$ttlDays} дней, Действие: {$action})");

            // Здесь можно добавить логику для работы с заказами из БД, если они там будут храниться
            // На данный момент заказы хранятся в localStorage на фронтенде
            // Эта команда может использоваться для логирования или для будущей интеграции с БД

            Log::info('Cleanup cancelled orders command executed', [
                'bot_id' => $bot->id,
                'ttl_days' => $ttlDays,
                'action' => $action,
            ]);

            $totalProcessed++;
        }

        $this->info("Обработано ботов: {$totalProcessed}");
        $this->info("Скрыто заказов: {$totalHidden}");
        $this->info("Удалено заказов: {$totalDeleted}");

        $this->info('Очистка завершена.');

        // Примечание: Фактическая очистка заказов происходит на фронтенде при загрузке приложения
        // Эта команда может использоваться для логирования, отправки уведомлений администраторам
        // или для будущей интеграции, если заказы будут храниться в БД

        return Command::SUCCESS;
    }
}

