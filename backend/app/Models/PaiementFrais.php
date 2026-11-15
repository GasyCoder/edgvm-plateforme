<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part d'un paiement affectée à un frais.
 */
#[Table('paiement_frais')]
#[Fillable(['paiement_id', 'frais_doctorant_id', 'montant'])]
class PaiementFrais extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /**
     * @return BelongsTo<Paiement, $this>
     */
    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    /**
     * @return BelongsTo<FraisDoctorant, $this>
     */
    public function frais(): BelongsTo
    {
        return $this->belongsTo(FraisDoctorant::class, 'frais_doctorant_id');
    }
}
