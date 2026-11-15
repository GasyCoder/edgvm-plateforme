<?php

namespace App\Console\Commands;

use App\Enums\RoleUtilisateur;
use App\Models\Doctorant;
use App\Models\User;
use App\Notifications\RappelPaiement;
use App\Notifications\RecapitulatifRetards;
use App\Support\SituationFinanciere;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Chaque jour : rappel aux doctorants en retard (une fois par semaine au plus) et récapitulatif à l'administration.
 * Relancer la commande le même jour n'envoie rien de plus.
 */
#[Signature('paiements:rappels')]
#[Description('Rappels de paiement aux doctorants en retard et récapitulatif des retards à l\'administration')]
class RappelsPaiements extends Command
{
    public function handle(): int
    {
        $enRetard = SituationFinanciere::avecTotaux(Doctorant::query()->enRetardDePaiement()->with('user'))->get();
        $rappels = 0;
        $totalRetard = 0;

        foreach ($enRetard as $doctorant) {
            $situation = SituationFinanciere::pour($doctorant);
            $totalRetard += $situation['en_retard'];
            $user = $doctorant->user;

            if (! $user?->actif || $user->notifications()->where('type', RappelPaiement::class)->where('created_at', '>=', now()->subDays(7))->exists()) {
                continue;
            }

            $user->notify(new RappelPaiement($situation['en_retard'], $situation['reste']));
            $rappels++;
        }

        $admins = User::query()->where('role', RoleUtilisateur::SuperAdmin)->where('actif', true)
            ->whereDoesntHave('notifications', fn ($n) => $n->where('type', RecapitulatifRetards::class)->whereDate('created_at', today()))
            ->get();

        if ($enRetard->isNotEmpty() && $admins->isNotEmpty()) {
            Notification::send($admins, new RecapitulatifRetards($enRetard->count(), $totalRetard));
        }

        $this->info("{$enRetard->count()} doctorant(s) en retard, {$rappels} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
