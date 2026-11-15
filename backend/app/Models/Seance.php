<?php

namespace App\Models;

use App\Enums\StatutDoctorant;
use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use App\Support\SituationFinanciere;
use Carbon\CarbonImmutable;
use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Séance de communication (séminaire doctoral, état d'avancement...) : des doctorants passent l'un après l'autre,
 * chacun devant ses modérateurs et son jury.
 */
#[Table('seances')]
#[Fillable(['type', 'titre', 'description', 'lieu', 'date', 'heure_debut', 'duree_passage', 'pause', 'annee_universitaire_id', 'exige_paiement_solde', 'statut', 'convocations_envoyees_le', 'cree_par'])]
class Seance extends Model
{
    /** @use HasFactory<SeanceFactory> */
    use HasFactory;

    /**
     * @return HasMany<Passage, $this>
     */
    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class)->orderBy('debut')->orderBy('ordre');
    }

    /**
     * @return BelongsTo<AnneeUniversitaire, $this>
     */
    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class);
    }

    /** Début de la séance (date et heure de début) */
    public function commencement(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->toDateString().' '.substr((string) $this->heure_debut, 0, 5));
    }

    /**
     * Pourquoi ce doctorant ne peut pas être programmé, ou null s'il le peut.
     */
    public function raisonInadmissible(Doctorant $doctorant): ?string
    {
        if ($doctorant->statut !== StatutDoctorant::Actif) {
            return 'Dossier '.mb_strtolower($doctorant->statut->libelle()).'.';
        }

        if ($this->exige_paiement_solde) {
            $reste = SituationFinanciere::pour($doctorant)['reste'];

            if ($reste > 0) {
                return 'Paiement non soldé : reste '.number_format($reste, 0, ',', "\u{00A0}")."\u{00A0}Ar.";
            }
        }

        return null;
    }

    /**
     * Séances visibles : toutes pour l'administration ; pour un doctorant, celles où il passe ;
     * pour un membre d'équipe, celles des doctorants de ses équipes ou celles où il intervient (même e-mail).
     *
     * @param  Builder<Seance>  $query
     */
    public function scopeVisiblesPour(Builder $query, User $user): void
    {
        if ($user->estSuperAdmin()) {
            return;
        }

        if ($user->estDoctorant()) {
            $query->whereHas('passages', fn (Builder $p) => $p->where('doctorant_id', $user->doctorant?->id ?? 0));

            return;
        }

        $query->whereHas('passages', fn (Builder $p) => $p
            ->whereHas('doctorant', fn (Builder $d) => $d->whereIn('equipe_accueil_id', $user->idsEquipesSuivies()))
            ->orWhereHas('intervenants', fn (Builder $m) => $m->where('email', $user->email)));
    }

    /** « Séminaire doctoral de décembre », ou « État d'avancement, EAD 1 » si le titre ne dit pas déjà le type. */
    public function intitule(): string
    {
        return str_starts_with(mb_strtolower($this->titre), mb_strtolower($this->type->libelle()))
            ? $this->titre
            : $this->type->libelle().', '.$this->titre;
    }

    public function estModifiable(): bool
    {
        return $this->statut !== StatutSeance::Annulee;
    }

    protected function casts(): array
    {
        return [
            'type' => TypeSeance::class,
            'statut' => StatutSeance::class,
            'date' => 'date',
            'duree_passage' => 'integer',
            'pause' => 'integer',
            'exige_paiement_solde' => 'boolean',
            'convocations_envoyees_le' => 'datetime',
        ];
    }
}
