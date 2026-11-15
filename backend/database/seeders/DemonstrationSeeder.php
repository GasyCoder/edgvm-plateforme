<?php

namespace Database\Seeders;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Données fictives pour le développement local uniquement (jamais en production).
 * Comptes : admin@edgvm.test, membre@edgvm.test, doctorant@edgvm.test ; mot de passe « password ».
 */
class DemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $equipes = EquipeAccueil::query()->ordonnees()->get();
        $annees = AnneeUniversitaire::query()->orderBy('debut')->get()->values();

        User::factory()->superAdmin()->create(['name' => 'Scolarité EDGVM', 'email' => 'admin@edgvm.test']);
        $membre = User::factory()->create(['name' => 'Pr RAKOTO Jean', 'email' => 'membre@edgvm.test', 'role' => 'membre_ead']);
        $membre->equipesAccueil()->attach($equipes->first(), ['fonction' => 'responsable']);

        $premier = Doctorant::factory()
            ->dansEquipe($equipes->first())
            ->for(User::factory()->doctorant()->state(['email' => 'doctorant@edgvm.test']))
            ->create(['nom' => 'RANDRIA', 'prenoms' => 'Hery Nomena', 'visible_annuaire' => true]);
        $premier->user->update(['name' => $premier->nomComplet()]);
        $this->inscrire($premier, $annees, 2);

        foreach ($equipes as $equipe) {
            Doctorant::factory()->count(4)->dansEquipe($equipe)->create()->each(function (Doctorant $doctorant) use ($annees): void {
                $doctorant->user->update(['name' => $doctorant->nomComplet()]);
                $this->inscrire($doctorant, $annees, fake()->numberBetween(0, 4));
            });
        }
    }

    /**
     * Historique d'inscriptions jusqu'à l'année en cours : D1, D2, D3 puis dérogation.
     *
     * @param  Collection<int, AnneeUniversitaire>  $annees
     */
    private function inscrire(Doctorant $doctorant, $annees, int $nombre): void
    {
        $dernieres = $annees->slice(-$nombre)->values();

        foreach ($dernieres as $rang => $annee) {
            $factory = Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire');
            $factory = $rang >= NiveauDoctorat::DUREE_NORMALE
                ? $factory->derogation(fake()->boolean() ? 'Décision n° '.fake()->numberBetween(1, 60).'/2026/EDGVM' : null)
                : $factory->niveau(NiveauDoctorat::from('D'.($rang + 1)));

            $factory->create(['statut' => $annee->est_courante && fake()->boolean(20) ? StatutInscription::EnAttente : StatutInscription::Validee]);
        }
    }
}
