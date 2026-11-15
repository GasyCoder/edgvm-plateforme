<?php

namespace App\Models;

use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use Database\Factories\DoctorantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'matricule', 'nom', 'prenoms', 'genre', 'date_naissance', 'lieu_naissance', 'nationalite',
    'numero_piece', 'adresse', 'equipe_accueil_id', 'sujet_these', 'directeur_these', 'co_directeur', 'co_encadrant', 'statut', 'visible_annuaire', 'observation',
])]
class Doctorant extends Model
{
    /** @use HasFactory<DoctorantFactory> */
    use HasFactory, HasUuids;

    /**
     * L'UUID sert d'identifiant public ; l'id reste la clé interne.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'statut' => StatutDoctorant::class,
            'visible_annuaire' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<EquipeAccueil, $this>
     */
    public function equipeAccueil(): BelongsTo
    {
        return $this->belongsTo(EquipeAccueil::class);
    }

    /**
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<PieceJointe, $this>
     */
    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class);
    }

    /**
     * Inscription de l'année universitaire en cours.
     *
     * @return HasOne<Inscription, $this>
     */
    public function inscriptionCourante(): HasOne
    {
        return $this->hasOne(Inscription::class)
            ->whereHas('anneeUniversitaire', fn (Builder $annee) => $annee->where('est_courante', true));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Rang de chaque dérogation (1 pour la première année au delà de D3), indexé par id d'inscription.
     *
     * @return array<int, int>
     */
    public function rangsDerogation(): array
    {
        $rang = 0;

        return $this->inscriptions
            ->sortBy(fn (Inscription $inscription) => $inscription->anneeUniversitaire?->debut)
            ->filter(fn (Inscription $inscription) => $inscription->est_derogation && $inscription->statut !== StatutInscription::Annulee)
            ->mapWithKeys(function (Inscription $inscription) use (&$rang): array {
                return [$inscription->id => ++$rang];
            })
            ->all();
    }

    public function nomComplet(): string
    {
        return trim(mb_strtoupper($this->nom).' '.($this->prenoms ?? ''));
    }

    /**
     * Doctorants qu'un utilisateur a le droit de voir.
     *
     * @param  Builder<Doctorant>  $query
     */
    public function scopeVisiblesPour(Builder $query, User $user): void
    {
        if ($user->estSuperAdmin()) {
            return;
        }

        if ($user->estMembreEad()) {
            $query->whereIn('equipe_accueil_id', $user->idsEquipesSuivies());

            return;
        }

        $query->where('user_id', $user->id);
    }

    /**
     * Doctorants inscrits cette année à un niveau donné (« D1 », « D2 », « D3 » ou « derogation »).
     *
     * @param  Builder<Doctorant>  $query
     */
    public function scopeNiveauCourant(Builder $query, ?string $niveau): void
    {
        if (blank($niveau)) {
            return;
        }

        $query->whereHas('inscriptionCourante', function (Builder $inscription) use ($niveau): void {
            $inscription->where('statut', '!=', StatutInscription::Annulee->value);
            $niveau === 'derogation'
                ? $inscription->where('est_derogation', true)
                : $inscription->where('est_derogation', false)->where('niveau', $niveau);
        });
    }

    /**
     * @param  Builder<Doctorant>  $query
     */
    public function scopeRecherche(Builder $query, ?string $terme): void
    {
        if (blank($terme)) {
            return;
        }

        $query->where(function (Builder $sous) use ($terme): void {
            $sous->where('nom', 'like', "%{$terme}%")
                ->orWhere('prenoms', 'like', "%{$terme}%")
                ->orWhere('matricule', 'like', "%{$terme}%")
                ->orWhereHas('user', fn (Builder $compte) => $compte->where('email', 'like', "%{$terme}%"));
        });
    }

    /**
     * Matricule suivant pour l'année : EDGVM-2026-0001.
     */
    public static function prochainMatricule(?int $annee = null): string
    {
        $prefixe = 'EDGVM-'.($annee ?? now()->year).'-';
        $dernier = static::query()->where('matricule', 'like', $prefixe.'%')->max('matricule');
        $suivant = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}
