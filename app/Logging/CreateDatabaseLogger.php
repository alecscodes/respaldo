<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class CreateDatabaseLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new MonologLogger('database');
        $handler = new DatabaseHandler(
            request(),
            Level::fromName($config['level'] ?? 'debug')
        );
        $logger->pushHandler($handler);

        return new Logger($logger, app('events'));
    }
}
