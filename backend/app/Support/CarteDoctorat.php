<?php

namespace App\Support;

use App\Enums\StatutInscription;
use App\Models\Doctorant;
use App\Models\Inscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Carte de doctorat (une carte par page, format carte 17 x 10 cm), d'après le modèle papier de l'École.
 * Textes, directeur et logos viennent de Paramètres > Application :
 * en-tête ministère et arrêté d'habilitation, photo et identité, années de thèse à viser chaque année.
 */
class CarteDoctorat
{
    /** Largeur et hauteur en points (1 cm = 28,3465 pt) */
    public const FORMAT = [0, 0, 481.89, 283.46];

    /**
     * @param  Collection<int, Doctorant>  $doctorants
     */
    public static function generer(Collection $doctorants): string
    {
        return Pdf::loadHTML(self::html($doctorants))
            ->setPaper(self::FORMAT)
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'Helvetica'])
            ->output();
    }

    /**
     * @param  Collection<int, Doctorant>  $doctorants
     */
    public static function html(Collection $doctorants): string
    {
        @set_time_limit(300);
        (new EloquentCollection($doctorants->all()))->loadMissing(['inscriptions.anneeUniversitaire']);

        return view('cartes.carte', [
            'cartes' => $doctorants->map(fn (Doctorant $doctorant) => [
                'doctorant' => $doctorant,
                'photo' => PhotoDoctorant::dataUri($doctorant),
                'annees' => self::anneesDeThese($doctorant),
            ]),
            'logo_gauche' => ReglagesActes::dataUri(ReglagesActes::image('logo_gauche'), 'logo_gauche'),
            'logo_droite' => ReglagesActes::dataUri(ReglagesActes::image('logo_droite'), 'logo_droite'),
            'ministere' => ReglagesApplication::texte('ecole.ministere'),
            'arrete' => ReglagesApplication::texte('ecole.arrete'),
            'universite' => ReglagesApplication::texte('ecole.universite'),
            'directeur' => ReglagesApplication::directeurAvecGrade(),
        ])->render();
    }

    /**
     * Les trois années de thèse : celles des inscriptions D1, D2, D3 quand elles existent,
     * sinon déduites de l'année connue la plus proche (D1 en 2025-2026 donne D2 en 2026-2027).
     *
     * @return array<int, array{rang: string, annee: ?string}>
     */
    public static function anneesDeThese(Doctorant $doctorant): array
    {
        $doctorant->loadMissing(['inscriptions.anneeUniversitaire']);
        $parRang = $doctorant->inscriptions
            ->filter(fn (Inscription $inscription) => ! $inscription->est_derogation && $inscription->statut !== StatutInscription::Annulee && $inscription->anneeUniversitaire)
            ->mapWithKeys(fn (Inscription $inscription) => [(int) substr($inscription->niveau->value, 1) => $inscription->anneeUniversitaire]);

        $reference = $parRang->sortKeys()->map(fn ($annee, int $rang) => $annee->debut->year - ($rang - 1))->first();

        return collect([1 => '1ère', 2 => '2ème', 3 => '3ème'])->map(fn (string $libelle, int $rang) => [
            'rang' => $libelle,
            'annee' => $parRang->get($rang)?->libelle
                ?? ($reference !== null ? ($reference + $rang - 1).'-'.($reference + $rang) : null),
        ])->values()->all();
    }
}
