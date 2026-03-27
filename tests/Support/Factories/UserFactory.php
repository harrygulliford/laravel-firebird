<?php

namespace HarryGulliford\Firebird\Tests\Support\Factories;

use HarryGulliford\Firebird\Tests\Support\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->email(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'post_code' => fake()->postcode(),
            'country' => fake()->country(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
