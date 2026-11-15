<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fichiers CSV lisibles par Excel en français : séparateur « ; », encodage UTF-8 avec BOM.
 * À la lecture, accepte « ; », « , » ou tabulation, et les fichiers enregistrés par Excel (Windows-1252).
 */
class FichierCsv
{
    /**
     * @param  array<int, string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes): void {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';', escape: '');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map(fn ($valeur) => $valeur ?? '', $ligne), ';', escape: '');
            }

            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lit un fichier CSV : renvoie les lignes indexées par en-tête normalisé (« Date de naissance » devient « date_de_naissance »)
     * et numérotées comme dans le tableur (l'en-tête est la ligne 1).
     *
     * @return array<int, array<string, string>>
     */
    public static function lire(string $chemin): array
    {
        $contenu = (string) file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $premiere = strtok($contenu, "\r\n") ?: '';
        $separateur = collect([';', ',', "\t"])->sortByDesc(fn (string $candidat) => substr_count($premiere, $candidat))->first();

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $entetes = array_map(fn (?string $entete) => self::normaliserEntete((string) $entete), fgetcsv($flux, separator: $separateur, escape: '') ?: []);
        $resultat = [];
        $numero = 1;

        while (($valeurs = fgetcsv($flux, separator: $separateur, escape: '')) !== false) {
            $numero++;

            if (implode('', array_map('trim', array_map('strval', $valeurs))) === '') {
                continue;
            }

            $resultat[$numero] = collect($entetes)
                ->mapWithKeys(fn (string $entete, int $position) => [$entete => trim((string) ($valeurs[$position] ?? ''))])
                ->all();
        }

        fclose($flux);

        return $resultat;
    }

    public static function normaliserEntete(string $entete): string
    {
        return Str::of($entete)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
