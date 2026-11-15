<?php

namespace Database\Factories;

use App\Enums\RoleUtilisateur;
use App\Models\EquipeAccueil;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => RoleUtilisateur::Doctorant,
            'telephone' => '+2613'.fake()->numerify('########'),
            'actif' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => RoleUtilisateur::SuperAdmin]);
    }

    public function doctorant(): static
    {
        return $this->state(fn (array $attributes) => ['role' => RoleUtilisateur::Doctorant]);
    }

    /**
     * Membre d'une équipe d'accueil (créée si aucune n'est fournie).
     */
    public function membreEad(?EquipeAccueil $equipe = null): static
    {
        return $this->state(fn (array $attributes) => ['role' => RoleUtilisateur::MembreEad])
            ->afterCreating(function (User $user) use ($equipe): void {
                $user->equipesAccueil()->attach($equipe ?? EquipeAccueil::factory()->create(), ['fonction' => 'membre']);
            });
    }

    public function inactif(): static
    {
        return $this->state(fn (array $attributes) => ['actif' => false]);
    }
}
