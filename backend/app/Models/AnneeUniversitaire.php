<?php

namespace App\Models;

use Database\Factories\AnneeUniversitaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Table('annees_universitaires')]
#[Fillable(['libelle', 'debut', 'fin', 'est_courante'])]
class AnneeUniversitaire extends Model
{
    /** @use HasFactory<AnneeUniversitaireFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'date',
            'fin' => 'date',
            'est_courante' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public static function courante(): ?self
    {
        return static::query()->where('est_courante', true)->first();
    }

    /**
     * Fait de cette année l'année en cours (une seule à la fois).
     */
    public function definirCommeCourante(): void
    {
        DB::transaction(function (): void {
            static::query()->whereKeyNot($this->getKey())->update(['est_courante' => false]);
            $this->update(['est_courante' => true]);
        });
    }
}
