<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum IntegrationProtocol: string implements HasLabel
{
    case Rest = 'rest';
    case Soap = 'soap';
    case File = 'file';
    case Database = 'database';
    case Messaging = 'messaging';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rest => 'API REST',
            self::Soap => 'SOAP',
            self::File => 'Échange de fichiers',
            self::Database => 'Base de données partagée',
            self::Messaging => 'Messagerie / file d’attente',
            self::Other => 'Autre',
        };
    }
}
