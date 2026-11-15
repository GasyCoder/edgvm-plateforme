<?php

namespace App\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case Virement = 'virement';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Virement => 'Versement bancaire',
            self::MobileMoney => 'Mobile Money',
            self::Cheque => 'Chèque',
        };
    }
}
