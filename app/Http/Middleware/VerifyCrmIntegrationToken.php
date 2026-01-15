<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCrmIntegrationToken
{
    /**
     * Handle an incoming request.
     * Проверяет токен для интеграции с CRM (принимает CRM_API_TOKEN)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем токен из заголовка или тела запроса
        $authHeader = $request->header('Authorization');
        $token = null;
        
        // Проверяем Authorization header с Bearer токеном
        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // Fallback на другие заголовки
        $token = $token 
            ?? $request->header('X-Deploy-Token') 
            ?? $request->header('X-Deploy-Secret')
            ?? $request->header('X-CRM-Token')
            ?? $request->input('token')
            ?? $request->input('secret');
        
        // Получаем ожидаемый токен CRM (9 символов)
        $expectedToken = config('app.crm_api_token') ?: env('CRM_API_TOKEN');
        
        // Если не найден, пробуем DEPLOY_TOKEN как fallback (для обратной совместимости)
        if (!$expectedToken) {
            $expectedToken = config('app.deploy_token') ?: env('DEPLOY_TOKEN');
        }

        if (!$expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'CRM integration token not configured',
            ], 500);
        }

        Log::info('Проверка токена интеграции CRM', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'received_token_length' => $token ? strlen($token) : 0,
            'expected_token_length' => $expectedToken ? strlen($expectedToken) : 0,
            'tokens_match' => $token === $expectedToken,
        ]);
        
        if (!$token || $token !== $expectedToken) {
            Log::warning('Попытка интеграции с неверным токеном CRM', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'received_token' => $token ? substr($token, 0, 3) . '...' . substr($token, -3) : 'null',
                'expected_token' => $expectedToken ? substr($expectedToken, 0, 3) . '...' . substr($expectedToken, -3) : 'null',
                'received_token_length' => $token ? strlen($token) : 0,
                'expected_token_length' => $expectedToken ? strlen($expectedToken) : 0,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Неверный токен интеграции',
                'debug' => config('app.debug') ? [
                    'received_length' => $token ? strlen($token) : 0,
                    'expected_length' => $expectedToken ? strlen($expectedToken) : 0,
                ] : null,
            ], 403);
        }

        return $next($request);
    }
}




