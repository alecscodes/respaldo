<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    protected $fillable = [
        'ip_address',
        'email',
        'attempts_count',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts_count' => 'integer',
            'last_attempt_at' => 'datetime',
        ];
    }

    /**
     * Record a failed login attempt.
     */
    public static function recordFailure(string $ip, string $email): self
    {
        $attempt = self::firstOrNew(
            [
                'ip_address' => $ip,
                'email' => $email,
            ],
            [
                'attempts_count' => 0,
            ]
        );

        $attempt->attempts_count++;
        $attempt->last_attempt_at = now();
        $attempt->save();

        return $attempt;
    }

    /**
     * Get attempt count for IP and email.
     */
    public static function getAttempts(string $ip, string $email): int
    {
        $attempt = self::where('ip_address', $ip)
            ->where('email', $email)
            ->first();

        return $attempt !== null ? $attempt->attempts_count : 0;
    }

    /**
     * Reset attempts for IP and email.
     */
    public static function resetAttempts(string $ip, string $email): void
    {
        self::where('ip_address', $ip)
            ->where('email', $email)
            ->delete();
    }
}
