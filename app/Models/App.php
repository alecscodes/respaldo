<?php

namespace App\Models;

use App\Services\StorageConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class App extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'storage_size',
        'user_id',
    ];

    protected $appends = [
        'storage_size_gb',
    ];

    protected function casts(): array
    {
        return [
            'storage_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function getStorageSizeGbAttribute(): float
    {
        return StorageConverter::bytesToGb($this->storage_size);
    }

    public function setStorageSizeGbAttribute(float $gb): void
    {
        $this->attributes['storage_size'] = StorageConverter::gbToBytes($gb);
    }

    public function usedSpace(): int
    {
        return (int) $this->backups()->sum('size');
    }

    public function availableSpace(): int
    {
        return max(0, $this->storage_size - $this->usedSpace());
    }

    public function canBackup(int $size): bool
    {
        return $this->availableSpace() >= $size;
    }
}
