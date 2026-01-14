<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем пользователя с ролью admin
        $this->user = User::factory()->create();
        
        $adminRole = \App\Models\Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'slug' => 'admin']
        );
        $this->user->roles()->sync([$adminRole->id]);
        $this->user->refresh();
        
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Создаем тестового бота
        $this->bot = Bot::factory()->create([
            'token' => '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz',
            'username' => 'test_bot',
            'name' => 'Test Bot',
        ]);
    }

    /**
     * Тест: Получение списка заявок
     */
    public function test_get_consultations(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bot-management/{$this->bot->id}/consultations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'consultations',
                    'total',
                    'filters',
                ],
            ]);
    }

    /**
     * Тест: Получение настроек бота
     */
    public function test_get_bot_settings(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bot-management/{$this->bot->id}/settings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'required_channel_id',
                    'required_channel_username',
                    'admin_telegram_ids',
                    'yandex_maps_url',
                    'welcome_message',
                    'settings',
                ],
            ]);
    }

    /**
     * Тест: Обновление настроек бота
     */
    public function test_update_bot_settings(): void
    {
        $settings = [
            'required_channel_id' => -1001234567890,
            'required_channel_username' => 'test_channel',
            'admin_telegram_ids' => [123456789, 987654321],
            'yandex_maps_url' => 'https://yandex.ru/maps/org/test',
            'welcome_message' => 'Добро пожаловать!',
            'settings' => [
                'other_settings' => [
                    'phone_validation_strict' => false,
                    'max_description_length' => 1000,
                    'subscription_check_timeout' => 5,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/bot-management/{$this->bot->id}/settings", $settings);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $this->bot->refresh();
        $this->assertEquals(-1001234567890, $this->bot->required_channel_id);
        $this->assertEquals('test_channel', $this->bot->required_channel_username);
    }

    /**
     * Тест: Получение категорий материалов
     */
    public function test_get_material_categories(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bot-management/{$this->bot->id}/materials/categories");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Тест: Создание категории материалов
     */
    public function test_create_material_category(): void
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test Description',
            'order_index' => 0,
            'is_active' => true,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/bot-management/{$this->bot->id}/materials/categories", $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $this->assertDatabaseHas('bot_material_categories', [
            'bot_id' => $this->bot->id,
            'name' => 'Test Category',
        ]);
    }

    /**
     * Тест: Получение статистики бота
     */
    public function test_get_bot_statistics(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bot-management/{$this->bot->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_users',
                    'active_users_30d',
                    'total_consultations',
                    'consultations_by_status',
                    'materials_downloads',
                    'popular_materials',
                ],
            ]);
    }

    /**
     * Тест: Получение списка пользователей бота
     */
    public function test_get_bot_users(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bot-management/{$this->bot->id}/users");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}


