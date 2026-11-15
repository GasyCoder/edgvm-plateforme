<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Encadrés des e-mails : détails clé, valeur (convocation, paiement) ou petit tableau (liste de passages).
 * Sans fond ni encadré : de simples lignes fines, styles en ligne lus par toutes les messageries.
 */
class TableauCourriel
{
    /**
     * @param  array<string, ?string>  $details  libellé => valeur (les valeurs vides sont omises)
     */
    public static function details(array $details): HtmlString
    {
        $lignes = collect($details)->filter(fn (?string $valeur) => filled($valeur))->map(fn (string $valeur, string $libelle) => '<tr>'
            .'<td style="padding:9px 16px 9px 0;color:#6b7a72;font-size:14px;white-space:nowrap;vertical-align:top;width:1%;border-bottom:1px solid #eceff0;">'.e($libelle).'</td>'
            .'<td style="padding:9px 0;color:#1f2a24;font-size:14px;vertical-align:top;border-bottom:1px solid #eceff0;">'.nl2br(e($valeur)).'</td>'
            .'</tr>')->implode('');

        return new HtmlString(self::cadre('<table width="100%" cellpadding="0" cellspacing="0" role="presentation">'.$lignes.'</table>'));
    }

    /**
     * Phrase avec des passages en gras : « Vous êtes convoqué au :seance le :date ». Les valeurs sont échappées.
     *
     * @param  array<string, ?string>  $gras
     */
    public static function phrase(string $modele, array $gras): HtmlString
    {
        return new HtmlString(strtr(e($modele), collect($gras)->mapWithKeys(fn (?string $valeur, string $cle) => [':'.$cle => '<strong>'.e((string) $valeur).'</strong>'])->all()));
    }

    /**
     * Largeurs : une valeur par colonne (« 30% », « serree » pour une colonne courte qui ne passe pas à la ligne,
     * ou « masquable » pour une colonne cachée sur téléphone, comme un sujet de thèse).
     *
     * @param  array<int, string>  $entetes
     * @param  array<int, array<int, ?string>>  $lignes
     * @param  array<int, ?string>  $largeurs
     */
    public static function tableau(array $entetes, array $lignes, array $largeurs = []): HtmlString
    {
        $style = fn (int $index): string => match ($largeurs[$index] ?? null) {
            null, 'masquable' => '',
            'serree' => 'width:1%;white-space:nowrap;',
            default => 'width:'.$largeurs[$index].';',
        };
        $classe = fn (int $index): string => ($largeurs[$index] ?? null) === 'masquable' ? ' class="mobile-masque"' : '';
        $tete = collect($entetes)->map(fn (string $entete, int $index) => '<th align="left"'.$classe($index).' style="padding:0 12px 8px 0;color:#6b7a72;font-size:13px;font-weight:normal;border-bottom:1px solid #d9dfdc;'.$style($index).'">'.e($entete).'</th>')->implode('');
        $corps = collect($lignes)->map(fn (array $ligne) => '<tr>'.collect($ligne)->map(fn (?string $cellule, int $index) => '<td'.$classe($index).' style="padding:10px 12px 10px 0;font-size:14px;color:#1f2a24;border-bottom:1px solid #eceff0;vertical-align:top;'.$style($index).($index === 0 ? 'font-weight:bold;' : '').'">'.e((string) $cellule).'</td>')->implode('').'</tr>')->implode('');

        return new HtmlString(self::cadre('<table class="tableau-courriel" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>'.$tete.'</tr>'.$corps.'</table>'));
    }

    private static function cadre(string $contenu): string
    {
        return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:6px 0 24px;">'
            .'<tr><td style="padding:0;">'.$contenu.'</td></tr></table>';
    }
}
