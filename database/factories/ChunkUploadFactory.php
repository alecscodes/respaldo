<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\ChunkUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChunkUpload>
 */
class ChunkUploadFactory extends Factory
{
    protected $model = ChunkUpload::class;

    public function definition(): array
    {
        $chunkSize = 10 * 1024 * 1024; // 10MB
        $totalSize = $chunkSize * 5; // 50MB total

        return [
            'upload_id' => ChunkUpload::generateUploadId(),
            'app_id' => App::factory(),
            'user_id' => User::factory(),
            'filename' => fake()->word().'.tar.gz',
            'total_size' => $totalSize,
            'total_chunks' => 5,
            'chunk_size' => $chunkSize,
            'uploaded_chunks' => [],
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalChunks = $attributes['total_chunks'] ?? 5;

            return [
                'status' => 'completed',
                'uploaded_chunks' => range(0, $totalChunks - 1),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Test error message',
        ]);
    }
}
