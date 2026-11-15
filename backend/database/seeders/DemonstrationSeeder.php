<?php

namespace Database\Seeders;

use App\Actions\AppliquerFraisInscription;
use App\Enums\ModePaiement;
use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\Paiement;
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
        $dernieres = $nombre > 0 ? $annees->slice(-$nombre)->values() : collect();

        foreach ($dernieres as $rang => $annee) {
            $factory = Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire');
            $factory = $rang >= NiveauDoctorat::DUREE_NORMALE
                ? $factory->derogation(fake()->boolean() ? 'Décision n° '.fake()->numberBetween(1, 60).'/2026/EDGVM' : null)
                : $factory->niveau(NiveauDoctorat::from('D'.($rang + 1)));

            $inscription = $factory->create([
                'statut' => $annee->est_courante && fake()->boolean(20) ? StatutInscription::EnAttente : StatutInscription::Validee,
                'date_inscription' => $annee->debut->copy()->addDays(fake()->numberBetween(0, 40)),
            ]);
            (new AppliquerFraisInscription)($inscription);
        }

        $this->payer($doctorant);
    }

    /**
     * Paiements fictifs : les années passées sont presque toujours réglées, l'année en cours en partie.
     */
    private function payer(Doctorant $doctorant): void
    {
        foreach ($doctorant->frais()->with('anneeUniversitaire')->get() as $frais) {
            $partiel = $frais->anneeUniversitaire?->est_courante ? fake()->randomElement([0, 0, 0.5, 1]) : fake()->randomElement([1, 1, 1, 0.5]);
            $montant = (int) round($frais->montant * $partiel / 1000) * 1000;

            if ($montant <= 0) {
                continue;
            }

            $paiement = Paiement::query()->create([
                'numero_recu' => Paiement::prochainNumero(),
                'doctorant_id' => $doctorant->id,
                'date_paiement' => min(now(), $frais->echeance ?? now())->toDateString(),
                'montant' => $montant,
                'mode' => fake()->randomElement(ModePaiement::cases()),
                'reference' => 'DEMO-'.fake()->numerify('######'),
            ]);
            $paiement->imputations()->create(['frais_doctorant_id' => $frais->id, 'montant' => $montant]);
            $frais->recalculerPaye();
        }
    }
}
