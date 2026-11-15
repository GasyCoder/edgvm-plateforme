<?php

namespace App\Actions;

use App\Enums\RoleUtilisateur;
use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\PaiementEnregistre;
use App\Notifications\PaiementRecu;
use App\Support\SituationFinanciere;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre un versement et le répartit entre les frais : répartition donnée, ou automatique
 * (les frais à l'échéance la plus proche d'abord). Un versement ne peut pas dépasser le reste à payer.
 */
class EnregistrerPaiement
{
    /**
     * @param  array{date_paiement: string, mode: string, montant: int, reference?: ?string, observation?: ?string, imputations?: ?array<int, array{frais: int, montant: int}>}  $donnees
     *
     * @throws ValidationException
     */
    public function __invoke(Doctorant $doctorant, array $donnees, User $par, ?UploadedFile $justificatif = null): Paiement
    {
        $paiement = DB::transaction(function () use ($doctorant, $donnees, $par, $justificatif): Paiement {
            $impayes = $doctorant->frais()->impayes()->lockForUpdate()->orderByRaw('echeance is null')->orderBy('echeance')->orderBy('id')->get()->keyBy('id');
            $repartition = $this->repartition($impayes, (int) $donnees['montant'], $donnees['imputations'] ?? null);

            $paiement = Paiement::query()->create([
                'numero_recu' => Paiement::prochainNumero(),
                'doctorant_id' => $doctorant->id,
                'date_paiement' => $donnees['date_paiement'],
                'montant' => (int) $donnees['montant'],
                'mode' => $donnees['mode'],
                'reference' => $donnees['reference'] ?? null,
                'observation' => $donnees['observation'] ?? null,
                'saisi_par' => $par->id,
            ]);

            foreach ($repartition as $fraisId => $montant) {
                $paiement->imputations()->create(['frais_doctorant_id' => $fraisId, 'montant' => $montant]);
                $impayes[$fraisId]->recalculerPaye();
            }

            $paiement->update(['reste_apres' => SituationFinanciere::pour($doctorant)['reste']]);

            if ($justificatif) {
                $paiement->joindreJustificatif($justificatif);
            }

            return $paiement;
        });

        $doctorant->user?->notify(new PaiementRecu($paiement));
        Notification::send(
            User::query()->where('role', RoleUtilisateur::SuperAdmin)->where('actif', true)->whereKeyNot($par->id)->get(),
            new PaiementEnregistre($paiement, $par),
        );

        return $paiement;
    }

    /**
     * @param  Collection<int, FraisDoctorant>  $impayes
     * @param  array<int, array{frais: int, montant: int}>|null  $imputations
     * @return array<int, int> frais => montant
     */
    private function repartition($impayes, int $montant, ?array $imputations): array
    {
        $resteTotal = $impayes->sum(fn (FraisDoctorant $f) => $f->reste());

        if ($montant <= 0) {
            throw ValidationException::withMessages(['montant' => 'Le montant doit être supérieur à zéro.']);
        }

        if ($montant > $resteTotal) {
            throw ValidationException::withMessages(['montant' => 'Le montant dépasse le reste à payer ('.number_format($resteTotal, 0, ',', ' ').' Ar).']);
        }

        if ($imputations === null || $imputations === []) {
            $repartition = [];
            foreach ($impayes as $frais) {
                if ($montant === 0) {
                    break;
                }
                $part = min($montant, $frais->reste());
                $repartition[$frais->id] = $part;
                $montant -= $part;
            }

            return $repartition;
        }

        $repartition = [];
        foreach ($imputations as $ligne) {
            $frais = $impayes->get((int) $ligne['frais']);
            $part = (int) $ligne['montant'];

            if ($part <= 0) {
                continue;
            }
            if (! $frais) {
                throw ValidationException::withMessages(['imputations' => 'Un des frais choisis est déjà soldé ou n\'appartient pas à ce doctorant.']);
            }
            if ($part > $frais->reste()) {
                throw ValidationException::withMessages(['imputations' => "« {$frais->libelle} » : il ne reste que ".number_format($frais->reste(), 0, ',', ' ').' Ar à payer.']);
            }
            $repartition[$frais->id] = ($repartition[$frais->id] ?? 0) + $part;
        }

        if (array_sum($repartition) !== $montant) {
            throw ValidationException::withMessages(['imputations' => 'La répartition ('.number_format(array_sum($repartition), 0, ',', ' ').' Ar) ne correspond pas au montant versé.']);
        }

        return $repartition;
    }
}
