<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminRequestController extends Controller
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Получить список заявок
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminRequest::with(['bot', 'botUser', 'approver']);

        // Фильтр по статусу
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Фильтр по боту
        if ($request->has('bot_id') && $request->bot_id) {
            $query->where('bot_id', $request->bot_id);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 20), 100);
        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $requests->items(),
                'total' => $requests->total(),
                'filters' => [
                    'status' => $request->status,
                    'bot_id' => $request->bot_id,
                ],
            ],
        ]);
    }

    /**
     * Получить детали заявки
     */
    public function show(string $id): JsonResponse
    {
        $request = AdminRequest::with(['bot', 'botUser', 'approver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $request,
        ]);
    }

    /**
     * Подтвердить заявку
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $adminRequest = AdminRequest::with(['bot', 'botUser'])->findOrFail($id);

        if ($adminRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Заявка уже обработана',
            ], 400);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Получаем роль администратора
            $adminRole = Role::where('slug', 'admin')->first();
            if (!$adminRole) {
                throw new \Exception('Роль администратора не найдена');
            }

            // Создаем или находим пользователя с автоматически сгенерированным email
            $email = "telegram_{$adminRequest->telegram_user_id}@telegram.local";
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Создаем нового пользователя
                $name = $adminRequest->full_name;
                $password = Str::random(16); // Генерируем случайный пароль

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
            }

            // Назначаем роль администратора
            if (!$user->hasRole('admin')) {
                $user->roles()->syncWithoutDetaching([$adminRole->id]);
            }

            // Добавляем telegram_user_id в admin_telegram_ids бота (если еще не добавлен)
            $bot = $adminRequest->bot;
            $adminTelegramIds = $bot->admin_telegram_ids ?? [];
            if (!in_array($adminRequest->telegram_user_id, $adminTelegramIds)) {
                $adminTelegramIds[] = $adminRequest->telegram_user_id;
                $bot->update(['admin_telegram_ids' => $adminTelegramIds]);
            }

            // Обновляем заявку
            $adminRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            DB::commit();

            // Отправляем уведомление в Telegram
            $this->sendApprovalNotification($adminRequest->bot, $adminRequest->telegram_user_id);

            return response()->json([
                'success' => true,
                'message' => 'Заявка одобрена, пользователю назначена роль администратора',
                'data' => $adminRequest->fresh(['bot', 'botUser', 'approver']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving admin request: ' . $e->getMessage(), [
                'request_id' => $id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при одобрении заявки: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отклонить заявку
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $adminRequest = AdminRequest::findOrFail($id);

        if ($adminRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Заявка уже обработана',
            ], 400);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $adminRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        // Отправляем уведомление в Telegram
        $this->sendRejectionNotification($adminRequest->bot, $adminRequest->telegram_user_id, $validated['admin_notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Заявка отклонена',
            'data' => $adminRequest->fresh(['bot', 'botUser', 'approver']),
        ]);
    }

    /**
     * Отправить уведомление об одобрении
     */
    protected function sendApprovalNotification(Bot $bot, int $telegramUserId): void
    {
        $message = "🎉 Поздравляем!\n\n" .
            "Ваша заявка на назначение администратором бота <b>{$bot->name}</b> была одобрена.\n\n" .
            "Вам назначена роль администратора. Теперь вы можете управлять ботом через админ-панель.";

        $this->telegram->sendMessage($bot->token, $telegramUserId, $message, ['parse_mode' => 'HTML']);
    }

    /**
     * Отправить уведомление об отклонении
     */
    protected function sendRejectionNotification(Bot $bot, int $telegramUserId, ?string $notes = null): void
    {
        $message = "❌ Ваша заявка на назначение администратором была отклонена.";

        if ($notes) {
            $message .= "\n\nПричина: {$notes}";
        }

        $this->telegram->sendMessage($bot->token, $telegramUserId, $message);
    }
}
