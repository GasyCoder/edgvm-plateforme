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
#[Fillable(['doctorant_id', 'debut', 'fin', 'sujet', 'ordre', 'convoque_le', 'convocation_empreinte'])]
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

    /**
     * Ce que dit la convocation : si l'une de ces informations change, le passage est à reconvoquer.
     */
    public function empreinteConvocation(): string
    {
        $this->loadMissing(['seance', 'intervenants']);

        return hash('sha256', json_encode([
            $this->seance->titre,
            $this->seance->lieu,
            $this->debut->format('Y-m-d H:i'),
            $this->fin->format('Y-m-d H:i'),
            $this->sujet,
            $this->intervenants->map(fn (MembreEquipe $membre) => $membre->id.':'.$membre->pivot->role)->sort()->values()->all(),
        ]));
    }

    /**
     * « jamais » envoyée, « a_jour », ou « modifiee » depuis l'envoi.
     */
    public function etatConvocation(): string
    {
        return match (true) {
            $this->convoque_le === null => 'jamais',
            /* Convoqué avant que l'empreinte existe : on ne le compte pas comme modifié. */
            $this->convocation_empreinte === null => 'a_jour',
            $this->convocation_empreinte !== $this->empreinteConvocation() => 'modifiee',
            default => 'a_jour',
        };
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
