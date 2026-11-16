<?php

namespace App\Console\Commands;

use App\Actions\AppliquerFraisInscription;
use App\Actions\CreerDoctorant;
use App\Enums\ModePaiement;
use App\Enums\NiveauDoctorat;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\FraisDoctorant;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration (4 doctorants D1, D2, D3, dérogation et 3 candidats), créé avec la logique réelle
 * de l'application, et retiré en entier avec --supprimer (y compris ce qui a été ajouté pendant la démo).
 * Repères : e-mails en @demo.edgvm.test (jamais délivrés), observation « Données de démonstration »,
 * absents de l'annuaire et de la banque de thèses du site public.
 */
#[Signature('plateforme:donnees-demo {--supprimer : Retire tous les doctorants et candidats de démonstration}')]
#[Description('Crée (ou supprime) les doctorants et candidats de démonstration')]
class DonneesDemonstration extends Command
{
    public const DOMAINE = '@demo.edgvm.test';

    private const OBSERVATION = 'Données de démonstration : à supprimer après le test (plateforme:donnees-demo --supprimer).';

    public function handle(CreerDoctorant $creerDoctorant, AppliquerFraisInscription $appliquerFrais): int
    {
        if ($this->option('supprimer')) {
            $this->info($this->supprimer().' dossier(s) de démonstration supprimé(s).');

            return self::SUCCESS;
        }

        if (User::query()->where('email', 'like', '%'.self::DOMAINE)->exists()) {
            $this->warn('Les données de démonstration existent déjà. Lancez d\'abord --supprimer pour les recréer.');

            return self::FAILURE;
        }

        $annee = AnneeUniversitaire::courante();
        $equipes = EquipeAccueil::query()->ordonnees()->get()->keyBy('sigle');
        $admin = User::query()->where('role', RoleUtilisateur::SuperAdmin)->orderBy('id')->first();
        if ($annee === null || $equipes->isEmpty() || $admin === null) {
            $this->error('Il faut une année universitaire courante, des équipes d\'accueil et un compte d\'administration.');

            return self::FAILURE;
        }

        $motDePasse = 'Demo-'.Str::password(10, symbols: false);
        $equipe = fn (string $sigle): int => ($equipes->get($sigle) ?? $equipes->first())->id;

        DB::transaction(function () use ($creerDoctorant, $appliquerFrais, $annee, $equipe, $admin, $motDePasse): void {
            $doctorants = [
                ['RAKOTOARISOA', 'Fanja Nirina', 'F', 'EAD 1', 'Qualité des eaux de puits à Mahajanga et risques sanitaires', NiveauDoctorat::D1, false, 0.5],
                ['RANDRIAMANANA', 'Tojo Hery', 'M', 'EAD 2', 'Activité antioxydante de plantes médicinales du Boeny', NiveauDoctorat::D2, false, 1.0],
                ['RAZAFINDRAKOTO', 'Mialy Hasina', 'F', 'EAD 4', 'Cartographie des géoressources de la région Boeny', NiveauDoctorat::D3, false, 0.0],
                ['ANDRIANAIVO', 'Solo Fenitra', 'M', 'EAD 6', 'Valorisation nutritionnelle des produits halieutiques séchés', NiveauDoctorat::D3, true, 0.5],
            ];

            foreach ($doctorants as $rang => [$nom, $prenoms, $genre, $sigle, $sujet, $niveau, $derogation, $partPayee]) {
                $doctorant = $creerDoctorant([
                    ...$this->identite($nom, $prenoms, $genre, $rang),
                    'equipe_accueil_id' => $equipe($sigle),
                    'sujet_these' => $sujet,
                    'directeur_these' => 'Pr DEMO Directeur',
                    'statut' => StatutDoctorant::Actif->value,
                ], envoyerInvitation: false);
                $doctorant->user->forceFill(['password' => $motDePasse])->save();

                $inscription = $doctorant->inscriptions()->create([
                    'annee_universitaire_id' => $annee->id,
                    'niveau' => $niveau,
                    'type' => $niveau === NiveauDoctorat::D1 ? TypeInscription::Premiere : TypeInscription::Reinscription,
                    'est_derogation' => $derogation,
                    'motif_derogation' => $derogation ? 'Retard dans les travaux de terrain' : null,
                    'decision_derogation' => $derogation ? 'Décision n° DEMO/2026/EDGVM' : null,
                    'date_decision_derogation' => $derogation ? now()->toDateString() : null,
                    'statut' => StatutInscription::Validee,
                    'date_inscription' => now()->toDateString(),
                ]);
                $appliquerFrais($inscription, $admin);

                $this->payer($doctorant, $partPayee, $rang, $admin);
            }

            $candidats = [
                ['RASOAMANANA', 'Lalaina', 'F', 'EAD 1', 'Traitement biologique des déchets du marché de Mahajanga'],
                ['RABEARIVELO', 'Andry Tiana', 'M', 'EAD 3', 'Modèle in vitro pour tester des antipaludiques d\'origine végétale'],
                ['HARIMALALA', 'Voahangy', 'F', 'EAD 5', 'Alimentation des volailles à base de ressources locales'],
            ];

            foreach ($candidats as $rang => [$nom, $prenoms, $genre, $sigle, $sujet]) {
                $creerDoctorant([
                    ...$this->identite($nom, $prenoms, $genre, $rang + 10),
                    'equipe_accueil_id' => $equipe($sigle),
                    'sujet_these' => $sujet,
                    'directeur_these' => 'Pr DEMO Directeur',
                    'statut' => StatutDoctorant::Candidat->value,
                ], envoyerInvitation: false);
            }
        });

        $this->info('Démonstration prête : 4 doctorants (D1, D2, D3, dérogation) et 3 candidats.');
        $this->newLine();
        $this->table(['Doctorant', 'Identifiant de connexion'], Doctorant::query()
            ->where('statut', StatutDoctorant::Actif)
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%'.self::DOMAINE))
            ->with('user')->orderBy('id')->get()
            ->map(fn (Doctorant $d) => [$d->nomComplet(), $d->user->email])->all());
        $this->line("Mot de passe commun des 4 doctorants : {$motDePasse}");
        $this->comment('Notez-le maintenant : il ne sera plus affiché. Les candidats n\'ont pas d\'accès avant leur admission.');

        return self::SUCCESS;
    }

    /**
     * Paiement direct (sans les notifications d'un vrai paiement), réparti sur les frais dans l'ordre.
     */
    private function payer(Doctorant $doctorant, float $part, int $rang, User $admin): void
    {
        $frais = $doctorant->frais()->orderBy('id')->get();
        $reste = (int) (round($frais->sum('montant') * $part / 1000) * 1000);
        if ($reste <= 0) {
            return;
        }

        $paiement = Paiement::query()->create([
            'numero_recu' => Paiement::prochainNumero(),
            'doctorant_id' => $doctorant->id,
            'date_paiement' => now()->toDateString(),
            'montant' => $reste,
            'mode' => ModePaiement::Especes,
            'reference' => 'DEMO-'.($rang + 1),
            'observation' => 'Paiement de démonstration',
            'saisi_par' => $admin->id,
        ]);

        $frais->each(function (FraisDoctorant $ligne) use ($paiement, &$reste): void {
            $montant = min($reste, (int) $ligne->montant);
            if ($montant > 0) {
                $paiement->imputations()->create(['frais_doctorant_id' => $ligne->id, 'montant' => $montant]);
                $ligne->recalculerPaye();
                $reste -= $montant;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function identite(string $nom, string $prenoms, string $genre, int $rang): array
    {
        $email = Str::slug(Str::before($prenoms, ' ').'.'.$nom, '.').self::DOMAINE;

        return [
            'nom' => $nom,
            'prenoms' => $prenoms,
            'genre' => $genre,
            'date_naissance' => now()->subYears(28 + $rang % 6)->subDays(37 * $rang)->toDateString(),
            'lieu_naissance' => 'Mahajanga',
            'nationalite' => 'Malagasy',
            'email' => $email,
            'telephone' => null,
            'est_boursier' => $rang % 2 === 1,
            'visible_annuaire' => false,
            'observation' => self::OBSERVATION,
        ];
    }

    /**
     * Supprime les dossiers de démonstration et tout ce qui s'y rattache (séances, actes, paiements, frais, inscriptions).
     */
    private function supprimer(): int
    {
        $utilisateurs = User::query()->where('email', 'like', '%'.self::DOMAINE)->with('doctorant')->get();

        DB::transaction(function () use ($utilisateurs): void {
            foreach ($utilisateurs as $user) {
                if ($doctorant = $user->doctorant) {
                    DB::table('passages')->where('doctorant_id', $doctorant->id)->delete();
                    DB::table('actes')->where('doctorant_id', $doctorant->id)->delete();
                    DB::table('paiements')->where('doctorant_id', $doctorant->id)->delete();
                    DB::table('frais_doctorants')->where('doctorant_id', $doctorant->id)->delete();
                    DB::table('inscriptions')->where('doctorant_id', $doctorant->id)->delete();
                    $doctorant->delete();
                }
                $user->notifications()->delete();
                DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
                $user->delete();
            }
        });

        return $utilisateurs->count();
    }
}
