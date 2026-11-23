<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Kind of financial institution a customer is.
 */
enum CustomerType: string implements HasLabel
{
    case Bank = 'bank';
    case Insurance = 'insurance';
    case Microfinance = 'microfinance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Banque',
            self::Insurance => 'Assurance',
            self::Microfinance => 'Microfinance',
            self::Other => 'Autre',
        };
    }
}
