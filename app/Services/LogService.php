<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * @method void emergency(string $category, string $message, ?array $context = null)
 * @method void alert(string $category, string $message, ?array $context = null)
 * @method void critical(string $category, string $message, ?array $context = null)
 * @method void error(string $category, string $message, ?array $context = null)
 * @method void warning(string $category, string $message, ?array $context = null)
 * @method void notice(string $category, string $message, ?array $context = null)
 * @method void info(string $category, string $message, ?array $context = null)
 * @method void debug(string $category, string $message, ?array $context = null)
 */
class LogService
{
    private const VALID_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /**
     * Log a message with category support.
     * Prefer using: Log::channel('database')->info('message', ['category' => 'backup'])
     */
    public function log(string $level, string $category, string $message, ?array $context = null): void
    {
        if (! in_array($level, self::VALID_LEVELS, true)) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }

        $context = ($context ?? []) + ['category' => $category];
        Log::channel('database')->{$level}($message, $context);
    }

    /**
     * Magic method to handle log level methods.
     */
    public function __call(string $method, array $arguments): void
    {
        if (in_array($method, self::VALID_LEVELS, true)) {
            $this->log($method, ...$arguments);

            return;
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
