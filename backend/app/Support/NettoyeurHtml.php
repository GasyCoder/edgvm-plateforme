<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Garde seulement la mise en forme utile aux actes (paragraphes, gras, listes, alignement) :
 * aucun script, lien, image ou style libre ne passe.
 */
class NettoyeurHtml
{
    private const BALISES = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'span'];

    private const A_SUPPRIMER = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'img', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select'];

    public static function nettoyer(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="racine">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $racine = $document->getElementById('racine');
        if (! $racine) {
            return '';
        }

        self::parcourir($racine);

        $resultat = '';
        foreach (iterator_to_array($racine->childNodes) as $enfant) {
            $resultat .= $document->saveHTML($enfant);
        }

        return trim($resultat);
    }

    private static function parcourir(DOMNode $noeud): void
    {
        foreach (iterator_to_array($noeud->childNodes) as $enfant) {
            if ($enfant->nodeType === XML_COMMENT_NODE || $enfant->nodeType === XML_PI_NODE) {
                $noeud->removeChild($enfant);

                continue;
            }

            if (! $enfant instanceof DOMElement) {
                continue;
            }

            $balise = strtolower($enfant->tagName);

            if (in_array($balise, self::A_SUPPRIMER, true)) {
                $noeud->removeChild($enfant);

                continue;
            }

            self::parcourir($enfant);

            if (! in_array($balise, self::BALISES, true)) {
                while ($enfant->firstChild) {
                    $noeud->insertBefore($enfant->firstChild, $enfant);
                }
                $noeud->removeChild($enfant);

                continue;
            }

            $alignement = null;
            if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $enfant->getAttribute('style'), $trouve)) {
                $alignement = strtolower($trouve[1]);
            }

            foreach (iterator_to_array($enfant->attributes) as $attribut) {
                $enfant->removeAttribute($attribut->nodeName);
            }

            if ($alignement && in_array($balise, ['p', 'h2', 'h3', 'h4'], true)) {
                $enfant->setAttribute('style', "text-align: {$alignement}");
            }
        }
    }
}
