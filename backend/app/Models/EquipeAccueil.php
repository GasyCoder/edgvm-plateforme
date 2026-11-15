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

/**
 * Équipe d'accueil doctorale (EAD). Le sigle est le même que sur le site vitrine.
 */
#[Table('equipes_accueil')]
#[Fillable(['sigle', 'nom', 'description'])]
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
