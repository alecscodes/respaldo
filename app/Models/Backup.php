<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id',
        'filename',
        'file_path',
        'size',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Get the app that owns the backup.
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * Get the user that created the backup.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if backup file exists.
     */
    public function fileExists(): bool
    {
        return Storage::disk('backups')->exists($this->file_path);
    }

    /**
     * Get the full file path.
     */
    public function getFilePath(): string
    {
        return Storage::disk('backups')->path($this->file_path);
    }
}
