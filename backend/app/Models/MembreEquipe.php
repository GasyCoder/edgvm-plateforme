<?php

namespace App\Models;

use App\Enums\FonctionMembre;
use Database\Factories\MembreEquipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personne d'une équipe d'accueil : responsable, encadrant (directeur de thèse) ou membre.
 */
#[Table('membres_equipe')]
#[Fillable(['nom', 'titre', 'fonction', 'specialite', 'etablissement', 'email', 'telephone'])]
class MembreEquipe extends Model
{
    /** @use HasFactory<MembreEquipeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<EquipeAccueil, $this>
     */
    public function equipeAccueil(): BelongsTo
    {
        return $this->belongsTo(EquipeAccueil::class);
    }

    /** « Pr RAKOTO Jean » : titre puis nom, tel qu'on l'écrit dans un acte. */
    public function nomAvecTitre(): string
    {
        return trim(($this->titre ? $this->titre.' ' : '').$this->nom);
    }

    protected function casts(): array
    {
        return ['fonction' => FonctionMembre::class];
    }
}
