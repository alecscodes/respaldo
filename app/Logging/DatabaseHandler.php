<?php

namespace App\Logging;

use App\Enums\LogCategory;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseHandler extends AbstractProcessingHandler
{
    public function __construct(
        private ?Request $request = null,
        int|string|\Monolog\Level $level = \Monolog\Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Write the log record to the database.
     */
    protected function write(LogRecord $record): void
    {
        Log::create([
            'level' => strtolower($record->level->getName()),
            'category' => $this->extractCategory($record),
            'message' => $record->message,
            'context' => $this->formatContext($record),
            'user_id' => Auth::id(),
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
        ]);
    }

    protected function extractCategory(LogRecord $record): string
    {
        return $record->context['category']
            ?? $record->extra['category']
            ?? $record->channel
            ?? LogCategory::Application->value;
    }

    protected function formatContext(LogRecord $record): ?array
    {
        $context = $record->context;
        unset($context['category']);

        return empty($context) ? null : $context;
    }
}
