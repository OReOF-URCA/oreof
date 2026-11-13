<?php

namespace App\Service\Assistance;

/**
 * Ramène un sujet de mail à une forme comparable : sans préfixes de réponse/transfert,
 * sans balises ORéOF, en minuscules. Sert à regrouper les mails d'un même échange.
 */
class SujetNormaliseur
{
    private const PREFIXES = '/^\s*(?:(?:re|tr|fw|fwd|aw|réf|ref)\s*(?:\[\d+\])?\s*:|\[\s*or[ée]of\s*\]|\*{2,}\s*important\s*\*{2,})\s*/iu';

    public function normaliser(string $sujet): string
    {
        $resultat = trim($sujet);

        do {
            $precedent = $resultat;
            $resultat = (string) preg_replace(self::PREFIXES, '', $resultat);
        } while ($resultat !== $precedent);

        $resultat = (string) preg_replace('/\s+/u', ' ', $resultat);

        return mb_substr(mb_strtolower(trim($resultat)), 0, 255);
    }
}
