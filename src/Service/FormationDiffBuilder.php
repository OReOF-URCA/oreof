<?php

namespace App\Service;

use App\Entity\Formation;
use Doctrine\Common\Collections\Collection;

/**
 * Construit et applique un diff champ-à-champ entre deux Formation.
 * Partagé entre l'outil de fusion (formations concurrentes) et
 * l'outil de comparaison année N / N+1.
 */
class FormationDiffBuilder
{
    /**
     * @return array<int, array{key:string,label:string,displayA:string,displayB:string,isDifferent:bool,displayType:string,hasCascade:bool}>
     */
    public function buildDiffFields(Formation $a, Formation $b): array
    {
        return [
            $this->scalar('typeDiplome',            'Type de diplôme',                $a->getTypeDiplome()?->getLibelle(),          $b->getTypeDiplome()?->getLibelle()),
            $this->scalar('mention',                 'Mention',                        $a->getMention()?->getLibelle(),              $b->getMention()?->getLibelle()),
            $this->scalar('mentionTexte',            'Mention (texte libre)',           $a->getMentionTexte(),                        $b->getMentionTexte()),
            $this->scalar('composantePorteuse',      'Composante porteuse',             $a->getComposantePorteuse()?->getLibelle(),   $b->getComposantePorteuse()?->getLibelle(), 'text', true),
            $this->collection('localisationMention',    'Localisation(s)',             $a->getLocalisationMention(),                 $b->getLocalisationMention(), true),
            $this->collection('composantesInscription', "Composante(s) d'inscription", $a->getComposantesInscription(),              $b->getComposantesInscription()),
            $this->scalar('niveauEntree',            "Niveau d'entrée",                $a->getNiveauEntree()?->libelle(),            $b->getNiveauEntree()?->libelle()),
            $this->scalar('niveauSortie',            'Niveau de sortie',               $a->getNiveauSortie()?->libelle(),            $b->getNiveauSortie()?->libelle()),
            $this->enumArray('regimeInscription',    "Régime(s) d'inscription",        $a->getRegimeInscription(),                  $b->getRegimeInscription(), true),
            $this->scalar('regimeInscriptionTexte',  "Régime d'inscription (texte)",   $a->getRegimeInscriptionTexte(),              $b->getRegimeInscriptionTexte()),
            $this->scalar('rythmeFormation',         'Rythme de formation',            $a->getRythmeFormation()?->getLibelle(),      $b->getRythmeFormation()?->getLibelle(), 'text', true),
            $this->scalar('rythmeFormationTexte',    'Rythme de formation (texte)',     $a->getRythmeFormationTexte(),                $b->getRythmeFormationTexte(), 'html', true),
            $this->scalar('objectifsFormation',      'Objectifs de la formation',      $a->getObjectifsFormation(),                 $b->getObjectifsFormation(), 'html'),
            $this->scalar('contenuFormation',        'Contenu de la formation',        $a->getContenuFormation(),                   $b->getContenuFormation(), 'html'),
            $this->scalar('resultatsAttendus',       'Résultats attendus',             $a->getResultatsAttendus(),                  $b->getResultatsAttendus(), 'html'),
            $this->scalar('modalitesAlternance',     "Modalités d'alternance",         $a->getModalitesAlternance(),                $b->getModalitesAlternance(), 'html'),
            $this->scalar('codeRNCP',                'Code RNCP',                      $a->getCodeRNCP(),                           $b->getCodeRNCP()),
            $this->scalar('inRncp',                  'Inscrit au RNCP',                $a->isInRncp() ? 'Oui' : 'Non',             $b->isInRncp() ? 'Oui' : 'Non'),
            $this->scalar('sigle',                   'Sigle',                          $a->getSigle(),                              $b->getSigle()),
            $this->scalar('capaciteAccueil',         "Capacité d'accueil",             (string) $a->getCapaciteAccueil(),           (string) $b->getCapaciteAccueil()),
            $this->scalar('commentaire',             'Commentaire',                    $a->getCommentaire(),                        $b->getCommentaire()),
            $this->scalar('responsableMention',      'Responsable',                    $a->getResponsableMention()?->getDisplay(),  $b->getResponsableMention()?->getDisplay()),
            $this->scalar('coResponsable',           'Co-responsable',                 $a->getCoResponsable()?->getDisplay(),       $b->getCoResponsable()?->getDisplay()),
            $this->scalar('structureSemestres',      'Structure des semestres',
                $a->getStructureSemestres() ? json_encode($a->getStructureSemestres()) : '—',
                $b->getStructureSemestres() ? json_encode($b->getStructureSemestres()) : '—'
            ),
        ];
    }

    public function hasDifferences(Formation $a, Formation $b): bool
    {
        foreach ($this->buildDiffFields($a, $b) as $field) {
            if ($field['isDifferent']) {
                return true;
            }
        }

        return false;
    }

    public function applyField(Formation $target, Formation $source, string $key): void
    {
        match ($key) {
            'typeDiplome'            => $target->setTypeDiplome($source->getTypeDiplome()),
            'mention'                => $target->setMention($source->getMention()),
            'mentionTexte'           => $target->setMentionTexte($source->getMentionTexte()),
            'composantePorteuse'     => $target->setComposantePorteuse($source->getComposantePorteuse()),
            'niveauEntree'           => $target->setNiveauEntree($source->getNiveauEntree()),
            'niveauSortie'           => $target->setNiveauSortie($source->getNiveauSortie()),
            'regimeInscription'      => $target->setRegimeInscription($source->getRegimeInscription()),
            'regimeInscriptionTexte' => $target->setRegimeInscriptionTexte($source->getRegimeInscriptionTexte()),
            'rythmeFormation'        => $target->setRythmeFormation($source->getRythmeFormation()),
            'rythmeFormationTexte'   => $target->setRythmeFormationTexte($source->getRythmeFormationTexte()),
            'objectifsFormation'     => $target->setObjectifsFormation($source->getObjectifsFormation()),
            'contenuFormation'       => $target->setContenuFormation($source->getContenuFormation()),
            'resultatsAttendus'      => $target->setResultatsAttendus($source->getResultatsAttendus()),
            'modalitesAlternance'    => $target->setModalitesAlternance($source->getModalitesAlternance()),
            'codeRNCP'               => $target->setCodeRNCP($source->getCodeRNCP()),
            'inRncp'                 => $target->setInRncp($source->isInRncp() ?? false),
            'sigle'                  => $target->setSigle($source->getSigle()),
            'capaciteAccueil'        => $target->setCapaciteAccueil($source->getCapaciteAccueil() ?? 0),
            'commentaire'            => $target->setCommentaire($source->getCommentaire()),
            'responsableMention'     => $target->setResponsableMention($source->getResponsableMention()),
            'coResponsable'          => $target->setCoResponsable($source->getCoResponsable()),
            'structureSemestres'     => $target->setStructureSemestres($source->getStructureSemestres()),
            'localisationMention'    => $this->replaceCollection(
                $target->getLocalisationMention(),
                $source->getLocalisationMention(),
                fn($i) => $target->addLocalisationMention($i),
                fn($i) => $target->removeLocalisationMention($i),
            ),
            'composantesInscription' => $this->replaceCollection(
                $target->getComposantesInscription(),
                $source->getComposantesInscription(),
                fn($i) => $target->addComposantesInscription($i),
                fn($i) => $target->removeComposantesInscription($i),
            ),
            default => null,
        };
    }

    public function hashFormation(Formation $f): string
    {
        $locIds = $f->getLocalisationMention()->map(fn($v) => $v->getId())->toArray();
        $compIds = $f->getComposantesInscription()->map(fn($v) => $v->getId())->toArray();
        sort($locIds);
        sort($compIds);

        return md5(serialize([
            $f->getTypeDiplome()?->getId(),
            $f->getMention()?->getId(),
            $f->getMentionTexte(),
            $f->getComposantePorteuse()?->getId(),
            $locIds,
            $compIds,
            $f->getNiveauEntree()?->value,
            $f->getNiveauSortie()?->value,
            array_map(fn($r) => $r->value, $f->getRegimeInscription()),
            $f->getRegimeInscriptionTexte(),
            $f->getRythmeFormation()?->getId(),
            $f->getRythmeFormationTexte(),
            $f->getObjectifsFormation(),
            $f->getContenuFormation(),
            $f->getResultatsAttendus(),
            $f->getModalitesAlternance(),
            $f->getCodeRNCP(),
            $f->isInRncp(),
            $f->getSigle(),
            $f->getCapaciteAccueil(),
            $f->getCommentaire(),
            $f->getResponsableMention()?->getId(),
            $f->getCoResponsable()?->getId(),
            $f->getStructureSemestres(),
        ]));
    }

    // ── Lignée des années universitaires ───────────────────────────────────────

    /**
     * Lignée complète de la formation, de la plus ancienne à la plus récente.
     *
     * @return Formation[]
     */
    public function fullLineage(Formation $formation): array
    {
        $root = $formation;
        $guard = 0;
        while ($root->getFormationOrigineCopie() !== null && $guard < 20) {
            $root = $root->getFormationOrigineCopie();
            $guard++;
        }

        $chain = [$root];
        $current = $root;
        $guard = 0;
        while ($current->getFormationCopieAnneeUniversitaire() !== null && $guard < 20) {
            $current = $current->getFormationCopieAnneeUniversitaire();
            $chain[] = $current;
            $guard++;
        }

        return $chain;
    }

    /**
     * Libellé d'année universitaire, déduit de la campagne des DpeParcours
     * (le champ Formation::dpe est déprécié et peu fiable).
     */
    public function anneeLabel(Formation $formation): string
    {
        $dpeParcours = $formation->getDpeParcours()->first();
        $campagne = $dpeParcours ? $dpeParcours->getCampagneCollecte() : null;

        return $campagne?->getLibelle() ?? $formation->getDisplayLong();
    }

    /**
     * Chaîne ascendante de la formation, de la racine jusqu'à la formation
     * elle-même (incluse). Ne contient pas les versions postérieures.
     *
     * @return Formation[]
     */
    public function ancestryChain(Formation $formation): array
    {
        $chain = [];
        $current = $formation;
        $guard = 0;
        while ($current !== null && $guard < 20) {
            $chain[] = $current;
            $current = $current->getFormationOrigineCopie();
            $guard++;
        }

        return array_reverse($chain);
    }

    /**
     * Premier couple de versions adjacentes qui diffèrent, en ne regardant que
     * la chaîne ascendante (versions antérieures ou égales à la formation).
     * Sert à l'alerte : une version antérieure modifiée se signale sur toutes
     * les versions postérieures, mais pas sur les versions plus anciennes.
     *
     * @return array{source: Formation, cible: Formation}|null
     */
    public function firstDifferingPair(Formation $formation): ?array
    {
        $chain = $this->ancestryChain($formation);
        for ($i = 1, $n = count($chain); $i < $n; $i++) {
            $older = $chain[$i - 1];
            $newer = $chain[$i];
            if ($this->hasDifferences($older, $newer) || $this->parcoursSetDiffers($older, $newer)) {
                return ['source' => $older, 'cible' => $newer];
            }
        }

        return null;
    }

    /**
     * Indique si le jeu de parcours diffère entre deux versions adjacentes :
     * un parcours ajouté (présent dans la plus récente sans origine dans la plus
     * ancienne) ou non répercuté (présent dans la plus ancienne, sans copie dans
     * la plus récente).
     */
    public function parcoursSetDiffers(Formation $older, Formation $newer): bool
    {
        $olderMatched = [];
        foreach ($older->getParcours() as $p) {
            $olderMatched[$p->getId()] = false;
        }

        foreach ($newer->getParcours() as $p) {
            $origine = $p->getParcoursOrigineCopie();
            if ($origine === null || !array_key_exists($origine->getId(), $olderMatched)) {
                return true; // ajouté dans la version récente
            }
            $olderMatched[$origine->getId()] = true;
        }

        foreach ($olderMatched as $matched) {
            if (!$matched) {
                return true; // présent dans l'ancienne, non répercuté
            }
        }

        return false;
    }

    // ── Builders internes ──────────────────────────────────────────────────────

    private function scalar(string $key, string $label, ?string $valueA, ?string $valueB, string $type = 'text', bool $hasCascade = false): array
    {
        return [
            'key'         => $key,
            'label'       => $label,
            'displayA'    => $valueA ?? '—',
            'displayB'    => $valueB ?? '—',
            'isDifferent' => $valueA !== $valueB,
            'displayType' => $type,
            'hasCascade'  => $hasCascade,
        ];
    }

    private function collection(string $key, string $label, Collection $a, Collection $b, bool $hasCascade = false): array
    {
        $toIds  = fn(Collection $c) => array_map(fn($e) => $e->getId(), $c->toArray());
        $toText = fn(Collection $c) => $c->isEmpty() ? '—' : implode(', ', array_map(fn($e) => $e->getLibelle(), $c->toArray()));

        $idsA = $toIds($a);
        $idsB = $toIds($b);
        sort($idsA);
        sort($idsB);

        return [
            'key'         => $key,
            'label'       => $label,
            'displayA'    => $toText($a),
            'displayB'    => $toText($b),
            'isDifferent' => $idsA !== $idsB,
            'displayType' => 'text',
            'hasCascade'  => $hasCascade,
        ];
    }

    private function enumArray(string $key, string $label, array $a, array $b, bool $hasCascade = false): array
    {
        $toText = fn(array $arr) => empty($arr) ? '—' : implode(', ', array_map(fn($e) => $e->value, $arr));
        $toIds  = fn(array $arr) => array_map(fn($e) => $e->value, $arr);

        $vA = $toIds($a);
        $vB = $toIds($b);
        sort($vA);
        sort($vB);

        return [
            'key'         => $key,
            'label'       => $label,
            'displayA'    => $toText($a),
            'displayB'    => $toText($b),
            'isDifferent' => $vA !== $vB,
            'displayType' => 'text',
            'hasCascade'  => $hasCascade,
        ];
    }

    private function replaceCollection(Collection $current, Collection $source, callable $adder, callable $remover): void
    {
        foreach ($current->toArray() as $item) {
            $remover($item);
        }
        foreach ($source->toArray() as $item) {
            $adder($item);
        }
    }
}
