<?php

namespace App\Console\Commands;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crée le premier compte d'administration (ou réactive un compte existant) et affiche un mot de passe généré.
 */
#[Signature('plateforme:creer-admin {email : Adresse e-mail du compte} {--nom=Scolarité EDGVM : Nom affiché}')]
#[Description("Crée un compte d'administration de la plateforme avec un mot de passe généré")]
class CreerAdministrateur extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $motDePasse = Str::password(16, symbols: false);

        User::query()->updateOrCreate(['email' => $email], [
            'name' => $this->option('nom'),
            'password' => $motDePasse,
            'role' => RoleUtilisateur::SuperAdmin,
            'actif' => true,
        ]);

        $this->info("Compte d'administration prêt : {$email}");
        $this->line("Mot de passe : {$motDePasse}");
        $this->comment('Notez-le maintenant : il ne sera plus affiché.');

        return self::SUCCESS;
    }
}
