<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acte délivré. Il ne se modifie jamais : on l'annule et on en délivre un autre.
 *
 * @property array{nom: ?string, fonction: ?string, titre: ?string, ville: ?string, signature: ?string, logo_gauche: ?string, logo_droite: ?string} $signataire
 * @property array<string, mixed>|null $donnees
 */
#[Fillable([
    'numero', 'code_verification', 'modele_acte_id', 'doctorant_id', 'annee_universitaire_id', 'inscription_id',
    'titre', 'contenu', 'signataire', 'donnees', 'delivre_par', 'delivre_le', 'annule_le', 'motif_annulation', 'annule_par',
])]
class Acte extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signataire' => 'array',
            'donnees' => 'array',
            'delivre_le' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ModeleActe, $this>
     */
    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleActe::class, 'modele_acte_id');
    }

    /**
     * @return BelongsTo<Doctorant, $this>
     */
    public function doctorant(): BelongsTo
    {
        return $this->belongsTo(Doctorant::class);
    }

    /**
     * @return BelongsTo<AnneeUniversitaire, $this>
     */
    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delivrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivre_par');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function urlVerification(): string
    {
        return rtrim((string) config('app.url'), '/').'/verifier/'.$this->code_verification;
    }

    /**
     * Actes des doctorants qu'un utilisateur peut voir (un doctorant ne voit que ses actes valides).
     *
     * @param  Builder<Acte>  $query
     */
    public function scopeVisiblesPour(Builder $query, User $user): void
    {
        $query->whereHas('doctorant', fn (Builder $doctorant) => $doctorant->visiblesPour($user));

        if ($user->estDoctorant()) {
            $query->whereNull('annule_le');
        }
    }

    /**
     * Numéro suivant pour l'année : EDGVM/2026/000123 (à appeler dans une transaction).
     */
    public static function prochainNumero(?int $annee = null): string
    {
        $prefixe = 'EDGVM/'.($annee ?? now()->year).'/';
        $dernier = static::query()->where('numero', 'like', $prefixe.'%')->lockForUpdate()->max('numero');
        $suivant = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 6, '0', STR_PAD_LEFT);
    }
}
