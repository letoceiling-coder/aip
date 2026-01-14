<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\BotMaterialCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BotMaterialCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * Создает категории материалов для всех существующих ботов
     */
    public function run(): void
    {
        // Создаем категории для всех существующих ботов
        $bots = Bot::all();
        
        if ($bots->isEmpty()) {
            // Если ботов нет, просто выходим (можно создать бота позже через админ-панель)
            return;
        }

        foreach ($bots as $bot) {
            $this->createCategoriesForBot($bot);
        }
    }

    /**
     * Создать категории материалов для бота
     */
    protected function createCategoriesForBot(Bot $bot): void
    {
        $categories = [
            [
                'name' => 'Структурирование',
                'icon' => '🧩',
                'description' => 'для построения эффективной юридической и финансовой структуры',
                'external_url' => 'https://www.ap-group.ru/author-materials/strukturirovanie/',
                'order_index' => 1,
            ],
            [
                'name' => 'Партнёрство',
                'icon' => '🤝',
                'description' => 'по юридическим связкам между учредителями и предотвращению корпоративных конфликтов.',
                'external_url' => 'https://www.ap-group.ru/author-materials/partnerstvo/',
                'order_index' => 2,
            ],
            [
                'name' => 'Проверки',
                'icon' => '🔍',
                'description' => 'в помощь для эффективной подготовки и прохождения',
                'external_url' => 'https://www.ap-group.ru/author-materials/proverki/',
                'order_index' => 3,
            ],
            [
                'name' => 'Наследование',
                'icon' => '🧬',
                'description' => 'по наследованию бизнеса, чтобы активы и управление не были утрачены.',
                'external_url' => 'https://www.ap-group.ru/author-materials/nasledovanie/',
                'order_index' => 4,
            ],
            [
                'name' => 'Ликвидация',
                'icon' => '🚪',
                'description' => 'компаний без последствий и претензий.',
                'external_url' => 'https://www.ap-group.ru/author-materials/likvidatsiya/',
                'order_index' => 5,
            ],
            [
                'name' => 'Банкротство',
                'icon' => '⚠️',
                'description' => 'работа в процедуре и защита от субсидиарной ответственности',
                'external_url' => 'https://www.ap-group.ru/author-materials/bankrotstvo/',
                'order_index' => 6,
            ],
            [
                'name' => 'Работа с задолженностями',
                'icon' => '💸',
                'description' => 'Чек-лист по управлению долгами и снижению рисков личной ответственности.',
                'external_url' => 'https://www.ap-group.ru/author-materials/rabota-s-zadolzhennostyami/',
                'order_index' => 7,
            ],
            [
                'name' => 'Защита активов',
                'icon' => '🛡',
                'description' => 'по защите активов в предпринимательских спорах.',
                'external_url' => 'https://www.ap-group.ru/author-materials/zashchita-aktivov/',
                'order_index' => 8,
            ],
        ];

        foreach ($categories as $categoryData) {
            BotMaterialCategory::updateOrCreate(
                [
                    'bot_id' => $bot->id,
                    'name' => $categoryData['name'],
                ],
                [
                    'icon' => $categoryData['icon'] ?? null,
                    'description' => $categoryData['description'],
                    'external_url' => $categoryData['external_url'] ?? null,
                    'order_index' => $categoryData['order_index'],
                    'is_active' => true,
                ]
            );
        }
    }
}

