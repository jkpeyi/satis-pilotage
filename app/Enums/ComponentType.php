<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum ComponentType: string implements HasLabel
{
    case Api = 'api';
    case Web = 'web';
    case Mobile = 'mobile';
    case Batch = 'batch';
    case Worker = 'worker';
    case Database = 'database';
    case Library = 'library';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'API / backend',
            self::Web => 'Application web',
            self::Mobile => 'Application mobile',
            self::Batch => 'Traitement batch',
            self::Worker => 'Service / worker',
            self::Database => 'Base de données',
            self::Library => 'Bibliothèque partagée',
            self::Other => 'Autre',
        };
    }
}
