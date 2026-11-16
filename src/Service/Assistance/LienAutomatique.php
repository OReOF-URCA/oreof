<?php

namespace App\Service\Assistance;

/**
 * Rend cliquables les adresses https écrites en clair dans un message (ex. « Page : https://… » d'un signalement
 * de bug). Seul le texte est modifié : balises, attributs et liens existants restent intacts.
 *
 * Le résultat doit repasser par le sanitizer « assistance_sanitizer » (qui impose target/rel sur les liens).
 */
class LienAutomatique
{
    /**
     * https uniquement (seul schéma web autorisé par le sanitizer) ; s'arrête aux espaces, guillemets et chevrons,
     * y compris encodés (&quot; &#039; &lt; &gt;) puisque le texte traité est déjà encodé pour HTML.
     */
    private const URL = '~\bhttps://(?:(?!&(?:quot|#0*39|apos|lt|gt);)[^\s<>"\'])+~u';

    public function html(string $html): string
    {
        $morceaux = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $dansUnLien = 0;
        foreach ($morceaux as $i => $morceau) {
            if (str_starts_with($morceau, '<')) {
                if (preg_match('~^<a\b~i', $morceau) === 1) {
                    ++$dansUnLien;
                } elseif (preg_match('~^</a\s*>~i', $morceau) === 1) {
                    $dansUnLien = max(0, $dansUnLien - 1);
                }
                continue;
            }
            if ($dansUnLien === 0) {
                $morceaux[$i] = $this->lier($morceau);
            }
        }

        return implode('', $morceaux);
    }

    /**
     * Texte brut : échappé pour HTML, puis adresses rendues cliquables.
     */
    public function texte(string $texte): string
    {
        return $this->lier(htmlspecialchars($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param string $texte texte déjà encodé pour HTML (l'adresse est reprise telle quelle dans l'attribut href)
     */
    private function lier(string $texte): string
    {
        return (string) preg_replace_callback(self::URL, static function (array $m): string {
            $url = $m[0];
            // ponctuation de fin de phrase hors du lien
            $fin = '';
            while ($url !== '' && preg_match('/[.,;:!?)\]]$/u', $url) === 1) {
                $fin = mb_substr($url, -1) . $fin;
                $url = mb_substr($url, 0, -1);
            }

            return sprintf('<a href="%1$s">%1$s</a>%2$s', $url, $fin);
        }, $texte);
    }
}
