<?php

namespace App\Models;

use Database\Factories\EquipeAccueilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Équipe d'accueil doctorale (EAD). Le sigle est le même que sur le site vitrine.
 */
#[Table('equipes_accueil')]
#[Fillable(['sigle', 'nom', 'domaine', 'description'])]
class EquipeAccueil extends Model
{
    /** @use HasFactory<EquipeAccueilFactory> */
    use HasFactory;

    /**
     * @return HasMany<Doctorant, $this>
     */
    public function doctorants(): HasMany
    {
        return $this->hasMany(Doctorant::class);
    }

    /**
     * @return HasMany<ProgrammeDoctoral, $this>
     */
    public function programmes(): HasMany
    {
        return $this->hasMany(ProgrammeDoctoral::class)->orderBy('ordre')->orderBy('nom');
    }

    /**
     * Personnes de l'équipe (responsable, encadrants, membres), qu'elles aient un compte ou non.
     *
     * @return HasMany<MembreEquipe, $this>
     */
    public function personnes(): HasMany
    {
        return $this->hasMany(MembreEquipe::class)
            ->orderByRaw("CASE fonction WHEN 'responsable' THEN 0 WHEN 'encadrant' THEN 1 ELSE 2 END")
            ->orderBy('nom');
    }

    /**
     * Responsable de l'équipe (la première personne de fonction « responsable »).
     *
     * @return HasOne<MembreEquipe, $this>
     */
    public function responsable(): HasOne
    {
        return $this->hasOne(MembreEquipe::class)->ofMany(['id' => 'min'], fn (Builder $membre) => $membre->where('fonction', 'responsable'));
    }

    /**
     * Comptes de la plateforme rattachés à l'équipe (accès aux doctorants de l'équipe).
     *
     * @return BelongsToMany<User, $this>
     */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'equipe_accueil_user')
            ->withPivot('fonction')
            ->withTimestamps();
    }

    /**
     * Tri naturel des sigles : EAD 2 avant EAD 10.
     *
     * @param  Builder<EquipeAccueil>  $query
     */
    public function scopeOrdonnees(Builder $query): void
    {
        $query->orderByRaw('LENGTH(sigle)')->orderBy('sigle');
    }
}
