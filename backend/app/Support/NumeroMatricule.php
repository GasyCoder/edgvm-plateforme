<?php

namespace App\Support;

use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\Reglage;

/**
 * Matricule automatique des doctorants, réglé dans Paramètres > Matricule.
 *
 * Le modèle combine du texte et des repères :
 *  - {AAAA} année sur 4 chiffres, {AA} sur 2 chiffres ;
 *  - {N}, {NN}... {NNNNNNNN} : numéro d'ordre, complété par des zéros jusqu'au nombre de N.
 * Exemples : « EDGVM-{AAAA}-{NNNN} » donne EDGVM-2026-0001 ; « {NNN}-{AA} » donne 271-26.
 * Les matricules déjà attribués ne changent jamais.
 */
class NumeroMatricule
{
    public const DEFAUTS = [
        'format' => 'EDGVM-{AAAA}-{NNNN}',
        'annee' => 'civile',
        'remise' => 'annuelle',
        'depart' => '1',
    ];

    /** Lettres, chiffres, espaces et séparateurs courants, en plus des repères */
    public const CARACTERES = '/^(?:[A-Za-z0-9 \-\/_.]|\{AAAA\}|\{AA\}|\{N{1,8}\})+$/';

    /**
     * « depart_annee » : avec un compteur annuel, le numéro de départ ne vaut que pour l'année où il a été réglé.
     *
     * @return array{format: string, annee: string, remise: string, depart: int, depart_annee: ?int}
     */
    public static function reglages(): array
    {
        $departAnnee = Reglage::valeur('matricule.depart_annee');

        return [
            'format' => (string) Reglage::valeur('matricule.format', self::DEFAUTS['format']),
            'annee' => (string) Reglage::valeur('matricule.annee', self::DEFAUTS['annee']),
            'remise' => (string) Reglage::valeur('matricule.remise', self::DEFAUTS['remise']),
            'depart' => max(1, (int) Reglage::valeur('matricule.depart', self::DEFAUTS['depart'])),
            'depart_annee' => filled($departAnnee) ? (int) $departAnnee : null,
        ];
    }

    /**
     * Matricule suivant avec les réglages enregistrés (ou ceux passés, pour l'aperçu).
     *
     * @param  array{format: string, annee: string, remise: string, depart: int, depart_annee?: ?int}|null  $reglages
     */
    public static function suivant(?array $reglages = null, ?int $annee = null): string
    {
        $reglages ??= self::reglages();
        $annee ??= self::annee($reglages['annee']);

        return self::rendre($reglages['format'], $annee, self::prochainCompteur($reglages, $annee));
    }

    /**
     * Ce qu'il faut corriger dans un modèle, ou null s'il convient.
     */
    public static function erreurFormat(string $format, string $remise): ?string
    {
        if (! preg_match(self::CARACTERES, $format)) {
            return 'Caractères permis : lettres, chiffres, espace, - / _ . et les repères {AAAA}, {AA}, {NNNN}.';
        }

        if (preg_match_all('/\{N{1,8}\}/', $format) !== 1) {
            return 'Le modèle doit contenir un seul numéro d\'ordre, par exemple {NNNN}.';
        }

        if ($remise === 'annuelle' && ! preg_match('/\{AAAA\}|\{AA\}/', $format)) {
            return 'Avec une remise à zéro chaque année, le modèle doit contenir l\'année ({AAAA} ou {AA}), sinon deux doctorants auraient le même matricule.';
        }

        if (mb_strlen(self::rendre($format, 2026, 99999999)) > 30) {
            return 'Le matricule obtenu dépasse 30 caractères.';
        }

        return null;
    }

    public static function annee(string $source): int
    {
        return $source === 'universitaire'
            ? (AnneeUniversitaire::courante()?->debut?->year ?? now()->year)
            : now()->year;
    }

    public static function rendre(string $format, int $annee, int $compteur): string
    {
        return (string) preg_replace_callback('/\{(AAAA|AA|N{1,8})\}/', fn (array $repere) => match ($repere[1]) {
            'AAAA' => (string) $annee,
            'AA' => substr((string) $annee, -2),
            default => str_pad((string) $compteur, strlen($repere[1]), '0', STR_PAD_LEFT),
        }, $format);
    }

    /**
     * Plus grand numéro d'ordre déjà attribué avec ce modèle (pour l'année si le compteur repart chaque année), plus un.
     *
     * @param  array{format: string, annee: string, remise: string, depart: int, depart_annee?: ?int}  $reglages
     */
    private static function prochainCompteur(array $reglages, int $annee): int
    {
        $annuel = $reglages['remise'] === 'annuelle';
        $morceaux = preg_split('/(\{(?:AAAA|AA|N{1,8})\})/', $reglages['format'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $motif = '';
        $like = '';

        foreach ($morceaux as $morceau) {
            [$expression, $joker] = match (true) {
                $morceau === '{AAAA}' => [$annuel ? (string) $annee : '\d{4}', $annuel ? (string) $annee : '%'],
                $morceau === '{AA}' => [$annuel ? substr((string) $annee, -2) : '\d{2}', $annuel ? substr((string) $annee, -2) : '%'],
                (bool) preg_match('/^\{N+\}$/', $morceau) => ['(\d+)', '%'],
                default => [preg_quote($morceau, '/'), str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $morceau)],
            };
            $motif .= $expression;
            $like .= $joker;
        }

        $plusGrand = Doctorant::query()->where('matricule', 'like', $like)->pluck('matricule')
            ->map(fn (string $matricule) => preg_match('/^'.$motif.'$/', $matricule, $trouve) ? (int) $trouve[1] : 0)
            ->max() ?? 0;

        $departAnnee = $reglages['depart_annee'] ?? null;
        $depart = $annuel && $departAnnee !== null && $departAnnee !== $annee ? 1 : $reglages['depart'];

        return max($plusGrand + 1, $depart);
    }
}
