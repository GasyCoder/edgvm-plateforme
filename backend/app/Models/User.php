<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'telephone', 'actif'])]
#[Hidden(['password', 'remember_token', 'deux_facteurs_secret', 'deux_facteurs_secours', 'deux_facteurs_dernier_pas'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'deux_facteurs_secret' => 'encrypted',
            'deux_facteurs_active_le' => 'datetime',
            'deux_facteurs_secours' => 'encrypted:array',
            'deux_facteurs_dernier_pas' => 'integer',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'actif' => 'boolean',
        ];
    }

    /**
     * @return HasOne<Doctorant, $this>
     */
    public function doctorant(): HasOne
    {
        return $this->hasOne(Doctorant::class);
    }

    /**
     * Équipes d'accueil dont ce compte est membre.
     *
     * @return BelongsToMany<EquipeAccueil, $this>
     */
    public function equipesAccueil(): BelongsToMany
    {
        return $this->belongsToMany(EquipeAccueil::class, 'equipe_accueil_user')
            ->withPivot('fonction')
            ->withTimestamps();
    }

    public function estSuperAdmin(): bool
    {
        return $this->role === RoleUtilisateur::SuperAdmin;
    }

    public function estMembreEad(): bool
    {
        return $this->role === RoleUtilisateur::MembreEad;
    }

    public function estDoctorant(): bool
    {
        return $this->role === RoleUtilisateur::Doctorant;
    }

    /**
     * Identifiants des équipes d'accueil suivies par un membre d'équipe.
     *
     * @return array<int, int>
     */
    public function idsEquipesSuivies(): array
    {
        return $this->equipesAccueil()->pluck('equipes_accueil.id')->all();
    }

    /** Double authentification activée (confirmée par un premier code de l'appli) */
    public function deuxFacteursActive(): bool
    {
        return ($this->getAttributes()['deux_facteurs_active_le'] ?? null) !== null;
    }
}
