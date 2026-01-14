<?php

namespace Database\Factories;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

class BotFactory extends Factory
{
    protected $model = Bot::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name() . ' Bot',
            'token' => $this->faker->numerify('##########') . ':' . $this->faker->regexify('[A-Za-z0-9]{35}'),
            'username' => $this->faker->unique()->userName() . '_bot',
            'welcome_message' => $this->faker->sentence(),
            'is_active' => true,
            'webhook_registered' => false,
            'settings' => [],
        ];
    }
}


