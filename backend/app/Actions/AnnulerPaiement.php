<?php

namespace App\Actions;

use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Annule un paiement saisi par erreur : il reste au journal, et les frais qu'il réglait redeviennent dus.
 */
class AnnulerPaiement
{
    public function __invoke(Paiement $paiement, string $motif, User $par): Paiement
    {
        DB::transaction(function () use ($paiement, $motif, $par): void {
            $paiement->update(['annule_le' => now(), 'motif_annulation' => $motif, 'annule_par' => $par->id]);
            $paiement->imputations()->with('frais')->get()->each(fn ($imputation) => $imputation->frais->recalculerPaye());
        });

        return $paiement;
    }
}
