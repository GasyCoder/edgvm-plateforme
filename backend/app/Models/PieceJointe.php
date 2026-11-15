<?php

namespace App\Models;

use App\Enums\CategoriePiece;
use Database\Factories\PieceJointeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Table('pieces_jointes')]
#[Fillable(['doctorant_id', 'categorie', 'titre', 'nom_original', 'chemin', 'type_mime', 'taille', 'ajoute_par'])]
class PieceJointe extends Model
{
    /** @use HasFactory<PieceJointeFactory> */
    use HasFactory;

    public const DISQUE = 'local';

    /** Types acceptés : PDF, images et documents Word */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

    public const TAILLE_MAX_KO = 10240;

    protected static function booted(): void
    {
        static::deleted(fn (PieceJointe $piece) => Storage::disk(self::DISQUE)->delete($piece->chemin));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categorie' => CategoriePiece::class,
            'taille' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par');
    }

    /** Les PDF et images s'ouvrent dans le navigateur ; les autres se téléchargent. */
    public function peutSAfficher(): bool
    {
        return $this->type_mime === 'application/pdf' || str_starts_with($this->type_mime, 'image/');
    }
}
