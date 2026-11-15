<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Versement d'un doctorant, avec reçu numéroté (REC-2026-000001).
 */
#[Fillable([
    'numero_recu', 'doctorant_id', 'date_paiement', 'montant', 'reste_apres', 'mode', 'reference', 'observation', 'saisi_par',
    'justificatif_chemin', 'justificatif_nom', 'justificatif_type', 'justificatif_taille',
    'annule_le', 'motif_annulation', 'annule_par',
])]
class Paiement extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'reste_apres' => 'integer',
            'mode' => ModePaiement::class,
            'date_paiement' => 'date',
            'annule_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Doctorant, $this>
     */
    public function doctorant(): BelongsTo
    {
        return $this->belongsTo(Doctorant::class);
    }

    /**
     * @return HasMany<PaiementFrais, $this>
     */
    public function imputations(): HasMany
    {
        return $this->hasMany(PaiementFrais::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public const DISQUE = 'local';

    /** Photo ou scan du reçu de versement : images et PDF, 10 Mo au plus */
    public const EXTENSIONS_JUSTIFICATIF = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * Enregistre (ou remplace) le reçu de versement ; l'ancien fichier est supprimé.
     */
    public function joindreJustificatif(UploadedFile $fichier): void
    {
        $ancien = $this->justificatif_chemin;
        $extension = strtolower($fichier->getClientOriginalExtension() ?: $fichier->extension() ?: 'jpg');
        $chemin = $fichier->storeAs('paiements/'.$this->doctorant->uuid, $this->numero_recu.'-'.Str::lower(Str::random(8)).'.'.$extension, self::DISQUE);

        $this->update([
            'justificatif_chemin' => $chemin,
            'justificatif_nom' => $fichier->getClientOriginalName() ?: basename($chemin),
            'justificatif_type' => $fichier->getMimeType() ?? 'application/octet-stream',
            'justificatif_taille' => $fichier->getSize(),
        ]);

        if ($ancien && $ancien !== $chemin) {
            Storage::disk(self::DISQUE)->delete($ancien);
        }
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    /**
     * @param  Builder<Paiement>  $query
     */
    public function scopeValides(Builder $query): void
    {
        $query->whereNull('annule_le');
    }

    /**
     * Numéro de reçu suivant pour l'année (à appeler dans une transaction).
     */
    public static function prochainNumero(?int $annee = null): string
    {
        $prefixe = 'REC-'.($annee ?? now()->year).'-';
        $dernier = static::query()->where('numero_recu', 'like', $prefixe.'%')->lockForUpdate()->max('numero_recu');
        $suivant = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 6, '0', STR_PAD_LEFT);
    }
}
