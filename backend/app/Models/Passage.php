<?php

namespace App\Models;

use App\Enums\RoleIntervenant;
use Database\Factories\PassageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Passage d'un doctorant lors d'une séance : créneau, sujet présenté, modérateurs et jury.
 */
#[Table('passages')]
#[Fillable(['doctorant_id', 'debut', 'fin', 'sujet', 'ordre', 'convoque_le'])]
class Passage extends Model
{
    /** @use HasFactory<PassageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Seance, $this>
     */
    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    /**
     * @return BelongsTo<Doctorant, $this>
     */
    public function doctorant(): BelongsTo
    {
        return $this->belongsTo(Doctorant::class);
    }

    /**
     * Modérateurs et jury (personnes des équipes d'accueil), avec leur rôle.
     *
     * @return BelongsToMany<MembreEquipe, $this>
     */
    public function intervenants(): BelongsToMany
    {
        return $this->belongsToMany(MembreEquipe::class, 'intervenants_passage')
            ->withPivot('role')
            ->withTimestamps()
            ->orderByRaw("CASE intervenants_passage.role WHEN '".RoleIntervenant::Moderateur->value."' THEN 0 ELSE 1 END")
            ->orderBy('membres_equipe.nom');
    }

    protected function casts(): array
    {
        return [
            'debut' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'ordre' => 'integer',
            'convoque_le' => 'datetime',
        ];
    }
}
