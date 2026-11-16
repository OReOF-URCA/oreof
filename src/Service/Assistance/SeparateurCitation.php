<?php

namespace App\Service\Assistance;

/**
 * Sépare le contenu propre d'un mail de l'historique qu'il cite (« Le … a écrit : », en-têtes Outlook,
 * citation HTML, message transféré), pour n'afficher d'emblée que le nouveau contenu.
 *
 * Le découpage HTML se fait sur le texte brut : chaque partie doit repasser par le sanitizer avant affichage
 * (il referme ou supprime les balises coupées).
 */
class SeparateurCitation
{
    /** Morceau HTML sans balise de bloc (le « Le … a écrit » tient sur une ligne, liens et mise en forme compris). */
    private const EN_LIGNE = '(?:[^<>]|<(?!\/?(?:p|div|br|blockquote|table|tr|li|hr)\b)[^>]*>)';

    private const ESPACE = '(?:\s|&nbsp;|&#160;)';

    private const E_AIGU = '(?:é|&eacute;|&#233;)';

    /**
     * @return array{0: string, 1: ?string} contenu visible, historique cité (null si aucun)
     */
    public function separerHtml(string $html): array
    {
        $e = self::E_AIGU;
        $s = self::ESPACE;
        $l = self::EN_LIGNE;

        $position = $this->premierePosition($html, [
            '/<blockquote\b/i',
            '/<div\b[^>]*(?:id="divRplyFwdMsg"|class="[^"]*\bgmail_quote)/i',
            '/\bLe' . $s . '[^<>]{0,15}\d' . $l . '{0,400}?\ba' . $s . '+' . $e . 'crit' . $s . '*:/iu',
            '/\bOn' . $s . '[^<>]{0,15}\d' . $l . '{0,400}?\bwrote' . $s . '*:/iu',
            '/\b(?:De|From)' . $s . '*:(?:[^<>]|<[^>]+>){1,300}?\b(?:Envoy' . $e . '|Sent|Date)' . $s . '*:/iu',
            '/-{2,}' . $s . '*(?:Original Message|Message d.{1,6}origine|Message original|Message transf' . $e . 'r' . $e . ')/iu',
        ]);

        if ($position === null) {
            return [$html, null];
        }
        $visible = substr($html, 0, $position);
        if (trim(html_entity_decode(strip_tags($visible), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") === '') {
            return [$html, null];
        }

        return [$visible, substr($html, $position)];
    }

    /**
     * @return array{0: string, 1: ?string} contenu visible, historique cité (null si aucun)
     */
    public function separerTexte(string $texte): array
    {
        $position = $this->premierePosition($texte, [
            '/^Le\s[^\n]{0,15}\d[^\n]*(?:\n[^\n]*)?\ba\s+écrit\s*:/imu',
            '/^On\s[^\n]{0,15}\d[^\n]*(?:\n[^\n]*)?\bwrote\s*:/imu',
            '/^(?:De|From)\s*:[^\n]*\n(?:[^\n]*\n){0,3}?(?:Envoyé|Sent|Date)\s*:/imu',
            '/^-{2,}\s*(?:Original Message|Message d.origine|Message original|Message transféré)/imu',
            '/^>/m',
        ]);

        if ($position === null) {
            return [$texte, null];
        }
        $visible = rtrim(substr($texte, 0, $position));
        if ($visible === '') {
            return [$texte, null];
        }

        return [$visible, trim(substr($texte, $position))];
    }

    /**
     * @param list<string> $motifs
     */
    private function premierePosition(string $contenu, array $motifs): ?int
    {
        $position = null;
        foreach ($motifs as $motif) {
            if (preg_match($motif, $contenu, $correspondance, PREG_OFFSET_CAPTURE) === 1) {
                $debut = $correspondance[0][1];
                $position = $position === null ? $debut : min($position, $debut);
            }
        }

        return $position;
    }
}
