<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\User;
use App\Services\StorageConverter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Backup>
 */
class BackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'filename' => fake()->word().'-'.fake()->unixTime().'.tar.gz',
            'file_path' => 'backups/'.fake()->numberBetween(1, 100).'/'.fake()->uuid().'.tar.gz',
            'size' => StorageConverter::gbToBytes(fake()->randomFloat(3, 0.1, 5)),
            'user_id' => User::factory(),
        ];
    }
}
