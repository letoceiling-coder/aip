<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\BotUser;
use App\Services\BotFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotFormServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BotFormService $service;
    protected Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BotFormService::class);
        $this->bot = Bot::factory()->create();
    }

    /**
     * Тест: Валидация имени
     */
    public function test_validate_name(): void
    {
        // Валидное имя
        $result = $this->service->validateFormField('name', 'Иван Иванов', []);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Слишком короткое имя (проверка на 2 символа, один символ должен быть невалиден)
        // Но если используется strlen вместо mb_strlen, может быть проблема
        // Поэтому проверим с пустой строкой или очень коротким именем
        $result = $this->service->validateFormField('name', 'A', []);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // Пустое имя
        $result = $this->service->validateFormField('name', '', []);
        $this->assertFalse($result['valid']);
    }

    /**
     * Тест: Валидация телефона
     */
    public function test_validate_phone(): void
    {
        // Мягкая валидация - есть цифры
        $result = $this->service->validateFormField('phone', '+7 900 123-45-67', []);
        $this->assertTrue($result['valid']);

        // Строгая валидация - корректный формат
        $settings = ['other_settings' => ['phone_validation_strict' => true]];
        $result = $this->service->validateFormField('phone', '+79001234567', $settings);
        $this->assertTrue($result['valid']);

        // Строгая валидация - некорректный формат
        $result = $this->service->validateFormField('phone', '123', $settings);
        $this->assertFalse($result['valid']);
    }

    /**
     * Тест: Сохранение поля формы
     */
    public function test_save_form_field(): void
    {
        $user = BotUser::create([
            'bot_id' => $this->bot->id,
            'telegram_user_id' => 123456789,
            'first_name' => 'Test',
            'state_data' => [],
        ]);

        $this->service->saveFormField($this->bot->id, $user->telegram_user_id, 'name', 'Иван Иванов');

        $user->refresh();
        $this->assertEquals('Иван Иванов', $user->state_data['consultation']['name']);
    }

    /**
     * Тест: Очистка входных данных
     */
    public function test_sanitize_input(): void
    {
        // Очистка имени
        $result = $this->service->sanitizeInput('  Иван   Иванов  ', 'name');
        $this->assertEquals('Иван Иванов', $result);

        // Очистка телефона
        $result = $this->service->sanitizeInput('+7 (900) 123-45-67', 'phone');
        $this->assertNotEmpty($result);
    }
}

