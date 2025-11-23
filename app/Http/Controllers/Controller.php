<?php

namespace App\Http\Controllers;

use App\Services\LogService;

abstract class Controller
{
    protected function log(string $level, string $category, string $message, ?array $context = null): void
    {
        app(LogService::class)->{$level}($category, $message, $context);
    }
}
