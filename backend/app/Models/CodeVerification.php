<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'activation d'un compte : code à 4 chiffres (haché), essais, envois, puis jeton à usage unique
 * pour choisir le mot de passe une fois le code vérifié.
 */
#[Table('codes_verification')]
#[Fillable(['user_id', 'canal', 'code_hash', 'expire_le', 'essais', 'envois', 'dernier_envoi_le', 'verifie_le', 'jeton_hash', 'utilise_le'])]
class CodeVerification extends Model
{
    use HasUuids;

    public const DUREE_MINUTES = 10;

    public const ESSAIS_MAX = 5;

    public const ENVOIS_MAX = 5;

    public const ATTENTE_RENVOI_SECONDES = 60;

    /** Après la vérification du code, temps laissé pour choisir le mot de passe */
    public const DUREE_JETON_MINUTES = 15;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estExpire(): bool
    {
        return $this->expire_le->isPast();
    }

    public function attenteRenvoi(): int
    {
        return max(0, self::ATTENTE_RENVOI_SECONDES - (int) $this->dernier_envoi_le->diffInSeconds(now()));
    }

    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'dernier_envoi_le' => 'datetime',
            'verifie_le' => 'datetime',
            'utilise_le' => 'datetime',
            'essais' => 'integer',
            'envois' => 'integer',
        ];
    }
}
