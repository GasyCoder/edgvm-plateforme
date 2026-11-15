<?php

namespace App\Models;

use Database\Factories\ProgrammeDoctoralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Programme doctoral (spécialité) proposé par une équipe d'accueil.
 */
#[Table('programmes_doctoraux')]
#[Fillable(['nom', 'description', 'ordre'])]
class ProgrammeDoctoral extends Model
{
    /** @use HasFactory<ProgrammeDoctoralFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<EquipeAccueil, $this>
     */
    public function equipeAccueil(): BelongsTo
    {
        return $this->belongsTo(EquipeAccueil::class);
    }

    protected function casts(): array
    {
        return ['ordre' => 'integer'];
    }
}
