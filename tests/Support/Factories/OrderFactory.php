<?php

namespace HarryGulliford\Firebird\Tests\Support\Factories;

use HarryGulliford\Firebird\Tests\Support\Models\Order;
use HarryGulliford\Firebird\Tests\Support\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'price' => fake()->numberBetween(1, 200),
            'quantity' => fake()->numberBetween(0, 8),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
