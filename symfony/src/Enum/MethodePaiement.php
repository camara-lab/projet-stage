<?php

declare(strict_types=1);

namespace App\Enum;

enum MethodePaiement: string
{
    case CARD     = 'CARD';     // Carte bancaire en ligne (CMI)
    case CASH     = 'CASH';     // Espèces via agent (CashPlus / Wafacash)
    case TRANSFER = 'TRANSFER'; // Virement bancaire (CIH / Attijariwafa / BCP)

    public function prestataire(): string
    {
        return match ($this) {
            self::CARD     => 'CMI',
            self::CASH     => 'CASHPLUS',
            self::TRANSFER => 'CIH',
        };
    }
}
