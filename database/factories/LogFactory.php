<?php

namespace Database\Factories;

use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Log>
 */
class LogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        $categories = ['backup', 'api', 'database', 'security', 'system', 'user', 'debug'];

        return [
            'level' => fake()->randomElement($levels),
            'category' => fake()->randomElement($categories),
            'message' => fake()->sentence(),
            'context' => ['key' => fake()->word(), 'value' => fake()->numberBetween(1, 100)],
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the log has no user.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the log has no context.
     */
    public function withoutContext(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => null,
        ]);
    }
}
