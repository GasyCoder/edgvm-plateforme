<?php

namespace App\Enums;

/**
 * Années de doctorat en durée normale. Au delà de D3, l'inscription est une dérogation.
 */
enum NiveauDoctorat: string
{
    case D1 = 'D1';
    case D2 = 'D2';
    case D3 = 'D3';

    public const DUREE_NORMALE = 3;
}
