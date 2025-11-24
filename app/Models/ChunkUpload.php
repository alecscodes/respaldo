<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChunkUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'app_id',
        'user_id',
        'filename',
        'total_size',
        'total_chunks',
        'chunk_size',
        'uploaded_chunks',
        'status',
        'file_path',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total_size' => 'integer',
            'total_chunks' => 'integer',
            'chunk_size' => 'integer',
            'uploaded_chunks' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the app that owns the chunk upload.
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * Get the user that owns the chunk upload.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a unique upload ID.
     */
    public static function generateUploadId(): string
    {
        return Str::random(32);
    }

    /**
     * Check if a chunk has been uploaded.
     */
    public function hasChunk(int $chunkIndex): bool
    {
        return in_array($chunkIndex, $this->uploaded_chunks ?? [], true);
    }

    /**
     * Mark a chunk as uploaded.
     */
    public function markChunkUploaded(int $chunkIndex): void
    {
        $uploaded = $this->uploaded_chunks ?? [];
        if (! in_array($chunkIndex, $uploaded, true)) {
            $uploaded[] = $chunkIndex;
            sort($uploaded);
            $this->uploaded_chunks = $uploaded;
        }
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentage(): float
    {
        return $this->total_chunks === 0
            ? 0
            : (count($this->uploaded_chunks ?? []) / $this->total_chunks) * 100;
    }

    /**
     * Check if all chunks have been uploaded.
     */
    public function isComplete(): bool
    {
        return count($this->uploaded_chunks ?? []) >= $this->total_chunks;
    }

    /**
     * Get missing chunk indices.
     */
    public function getMissingChunks(): array
    {
        $uploaded = $this->uploaded_chunks ?? [];
        $allChunks = range(0, $this->total_chunks - 1);

        return array_values(array_diff($allChunks, $uploaded));
    }
}
