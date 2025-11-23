<?php

namespace App\Enums;

enum LogCategory: string
{
    case Application = 'application';
    case Api = 'api';
    case App = 'app';
    case Backup = 'backup';
    case Database = 'database';
    case Retention = 'retention';
    case Security = 'security';
    case System = 'system';
    case User = 'user';
}
