<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle d'acte : titre et texte avec variables, champs complémentaires remplis à la délivrance.
 *
 * @property array<int, array{cle: string, libelle: string, type: string, options?: array<int, string>, obligatoire?: bool}>|null $champs
 */
#[Table('modeles_actes')]
#[Fillable(['code', 'nom', 'titre', 'corps', 'champs', 'exige_inscription', 'inclure_signature', 'actif', 'ordre'])]
class ModeleActe extends Model
{
    public const TYPES_CHAMPS = ['texte', 'texte_long', 'date', 'heure', 'choix'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'champs' => 'array',
            'exige_inscription' => 'boolean',
            'inclure_signature' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Acte, $this>
     */
    public function actes(): HasMany
    {
        return $this->hasMany(Acte::class);
    }
}
