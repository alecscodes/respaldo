<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'category',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeByUser(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeByApp(Builder $query, int $appId): Builder
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $query->whereRaw('JSON_EXTRACT(context, "$.app_id") = ?', [$appId]),
            'pgsql' => $query->whereRaw('(context->>\'app_id\')::int = ?', [$appId]),
            default => $query->whereRaw('json_extract(context, "$.app_id") = ?', [$appId]),
        };
    }

    public function scopeSearch(Builder $query, string $search, bool $useRegex = false): Builder
    {
        if (! $useRegex) {
            return $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Validate regex pattern
        if (@preg_match($search, '') === false) {
            return $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $query->where(function ($q) use ($search) {
                $q->whereRaw('message REGEXP ?', [$search])
                    ->orWhereRaw('category REGEXP ?', [$search]);
            }),
            'pgsql' => $query->where(function ($q) use ($search) {
                $q->whereRaw('message ~ ?', [$search])
                    ->orWhereRaw('category ~ ?', [$search]);
            }),
            default => $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            }),
        };
    }
}
