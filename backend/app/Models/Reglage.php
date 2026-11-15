<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage clé/valeur modifiable depuis l'interface.
 */
#[Table('reglages')]
#[Fillable(['cle', 'valeur'])]
class Reglage extends Model
{
    public static function valeur(string $cle, ?string $defaut = null): ?string
    {
        return static::query()->where('cle', $cle)->value('valeur') ?? $defaut;
    }

    public static function definir(string $cle, ?string $valeur): void
    {
        static::query()->updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}
