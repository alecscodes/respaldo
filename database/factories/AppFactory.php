<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\StorageConverter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\App>
 */
class AppFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' App',
            'storage_size' => StorageConverter::gbToBytes(fake()->randomFloat(2, 1, 100)),
            'user_id' => User::factory(),
        ];
    }
}
