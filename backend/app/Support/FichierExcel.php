<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Classeur Excel (.xlsx) d'une seule feuille, écrit sans dépendance : en-tête en gras et figée,
 * filtre automatique, nombres alignés avec séparateur de milliers, largeurs de colonnes ajustées.
 */
class FichierExcel
{
    /**
     * @param  array<int, string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $nomFichier, array $entetes, iterable $lignes, string $nomFeuille = 'Feuille 1'): StreamedResponse
    {
        $chemin = self::ecrire($entetes, $lignes, $nomFeuille);

        return response()->streamDownload(function () use ($chemin): void {
            readfile($chemin);
            @unlink($chemin);
        }, $nomFichier, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Écrit le classeur dans un fichier temporaire et renvoie son chemin.
     *
     * @param  array<int, string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function ecrire(array $entetes, iterable $lignes, string $nomFeuille = 'Feuille 1'): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        $archive = new ZipArchive;

        if ($chemin === false || $archive->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de préparer le fichier Excel.');
        }

        $largeurs = array_map(fn (string $entete): int => mb_strlen($entete), $entetes);
        $rangees = [self::rangee(1, $entetes, $largeurs, estEntete: true)];
        $numero = 1;

        foreach ($lignes as $ligne) {
            $rangees[] = self::rangee(++$numero, array_values($ligne), $largeurs);
        }

        $derniereColonne = self::colonne(max(count($entetes), 1));
        $colonnes = collect($largeurs)->map(fn (int $largeur, int $index) => sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $index + 1, min(max($largeur + 2, 8), 60)))->implode('');

        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::texte(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nomFeuille), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $archive->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F5132"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>');
        $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($colonnes !== '' ? '<cols>'.$colonnes.'</cols>' : '')
            .'<sheetData>'.implode('', $rangees).'</sheetData>'
            .'<autoFilter ref="A1:'.$derniereColonne.$numero.'"/>'
            .'</worksheet>');

        $archive->close();

        return $chemin;
    }

    /**
     * @param  array<int, mixed>  $valeurs
     * @param  array<int, int>  $largeurs
     */
    private static function rangee(int $numero, array $valeurs, array &$largeurs, bool $estEntete = false): string
    {
        $cellules = '';

        foreach ($valeurs as $index => $valeur) {
            $reference = self::colonne($index + 1).$numero;
            $largeurs[$index] = max($largeurs[$index] ?? 0, mb_strlen((string) $valeur) + (is_int($valeur) || is_float($valeur) ? 3 : 0));

            if ($valeur === null || $valeur === '') {
                continue;
            }

            $cellules .= match (true) {
                $estEntete => '<c r="'.$reference.'" s="1" t="inlineStr"><is><t>'.self::texte((string) $valeur).'</t></is></c>',
                is_int($valeur), is_float($valeur) => '<c r="'.$reference.'" s="2"><v>'.$valeur.'</v></c>',
                default => '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.self::texte((string) $valeur).'</t></is></c>',
            };
        }

        return '<row r="'.$numero.'">'.$cellules.'</row>';
    }

    /** 1 donne A, 27 donne AA */
    private static function colonne(int $numero): string
    {
        $lettres = '';

        while ($numero > 0) {
            $reste = ($numero - 1) % 26;
            $lettres = chr(65 + $reste).$lettres;
            $numero = intdiv($numero - 1, 26);
        }

        return $lettres;
    }

    private static function texte(string $valeur): string
    {
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $valeur), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
