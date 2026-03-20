<?php

namespace App\Models;

use App\Services\StorageConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class App extends Model
{
    use HasFactory;

    private const DAY_MAP = ['M' => 1, 'T' => 2, 'W' => 3, 'R' => 4, 'F' => 5, 'S' => 6, 'U' => 0];

    protected $fillable = [
        'name',
        'storage_size',
        'user_id',
        'backup_period',
        'backup_days',
        'retention_days',
        'retention_count',
    ];

    protected $appends = [
        'storage_size_gb',
    ];

    protected function casts(): array
    {
        return [
            'storage_size' => 'integer',
            'backup_days' => 'array',
            'retention_days' => 'integer',
            'retention_count' => 'integer',
        ];
    }

    public function scopeWithBackupSchedule(Builder $query): Builder
    {
        return $query->whereNotNull('backup_period');
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

    public function hasBackupSchedule(): bool
    {
        return $this->backup_period !== null;
    }

    public function isBackupMissed(): bool
    {
        if (! $this->hasBackupSchedule()) {
            return false;
        }

        $lastBackup = $this->backups()->latest()->value('created_at');

        if (! $lastBackup) {
            return true;
        }

        $today = now()->startOfDay();

        return match ($this->backup_period) {
            'daily' => $lastBackup->lt($today),
            'weekly' => $this->isWeeklyBackupMissed($lastBackup, $today),
            'monthly' => $this->isMonthlyBackupMissed($lastBackup, $today),
            default => false,
        };
    }

    private function isWeeklyBackupMissed(Carbon $lastBackup, Carbon $today): bool
    {
        if (empty($this->backup_days)) {
            return false;
        }

        $todayLetter = array_search($today->dayOfWeek, self::DAY_MAP, true);

        return $todayLetter !== false
            && in_array($todayLetter, $this->backup_days, true)
            && $lastBackup->lt($today);
    }

    private function isMonthlyBackupMissed(Carbon $lastBackup, Carbon $today): bool
    {
        return $today->day === 1 && $lastBackup->lt($today);
    }

    public function shouldBackupToday(): bool
    {
        if (! $this->hasBackupSchedule()) {
            return false;
        }

        return match ($this->backup_period) {
            'daily' => true,
            'weekly' => $this->isScheduledDayToday(),
            'monthly' => now()->day === 1,
            default => false,
        };
    }

    private function isScheduledDayToday(): bool
    {
        if (empty($this->backup_days)) {
            return false;
        }

        $todayLetter = array_search(now()->dayOfWeek, self::DAY_MAP, true);

        return $todayLetter !== false && in_array($todayLetter, $this->backup_days, true);
    }

    /**
     * Check if retention is enabled for this app.
     */
    public function hasRetentionPolicy(): bool
    {
        return $this->retention_days !== null || $this->retention_count !== null;
    }

    /**
     * Get query for backups that should be deleted based on retention policy.
     */
    public function backupsToDeleteQuery()
    {
        $query = $this->backups()->orderBy('created_at', 'desc');

        // Apply age-based retention
        if ($this->retention_days !== null) {
            $query->where('created_at', '<', now()->subDays($this->retention_days));
        }

        // Apply count-based retention: skip the newest N backups
        if ($this->retention_count !== null) {
            $newestIds = $this->backups()
                ->orderBy('created_at', 'desc')
                ->limit($this->retention_count)
                ->pluck('id');

            if ($newestIds->isNotEmpty()) {
                $query->whereNotIn('id', $newestIds);
            }
        }

        return $query;
    }
}
