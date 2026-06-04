<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Semestre/TroncCommunToMutualisationMigrator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/04/2026 22:30
 */

declare(strict_types=1);

namespace App\Service\Semestre;

use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Semestre;
use App\Entity\SemestreMutualisable;
use App\Entity\SemestreParcours;
use Doctrine\ORM\EntityManagerInterface;

final class TroncCommunToMutualisationMigrator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function migrate(
        CampagneCollecte $campagne,
        array            $porteursByFormationId,
        bool             $apply,
        bool             $allowCrossFormation,
    ): array
    {
        $report = [
            'apply' => $apply,
            'campaign' => [
                'id' => $campagne->getId(),
                'libelle' => $campagne->getLibelle(),
                'annee' => $campagne->getAnnee(),
            ],
            'totals' => [
                'formations' => 0,
                'semestresTroncCommun' => 0,
                'mutualisationsCreated' => 0,
                'semestresDetaches' => 0,
                'alreadyDetached' => 0,
                'warnings' => 0,
                'errors' => 0,
            ],
            'formations' => [],
        ];

        $formations = $this->listFormationsWithTroncCommun($campagne, array_map('intval', array_keys($porteursByFormationId)));
        $report['totals']['formations'] = count($formations);

        if ($apply) {
            $this->entityManager->getConnection()->beginTransaction();
        }

        try {
            foreach ($formations as $formation) {
                $formationId = $formation->getId();
                if ($formationId === null) {
                    continue;
                }

                $entry = [
                    'formationId' => $formationId,
                    'formationLibelle' => (string)$formation->getDisplayLong(),
                    'parcoursPorteurId' => null,
                    'semestres' => [],
                ];

                $parcoursPorteurId = $porteursByFormationId[$formationId] ?? null;
                if ($parcoursPorteurId === null) {
                    $entry['error'] = 'Aucun parcours porteur fourni pour cette formation.';
                    $report['totals']['errors']++;
                    $report['formations'][] = $entry;
                    continue;
                }

                $entry['parcoursPorteurId'] = (int)$parcoursPorteurId;
                $semestresTronc = $this->findTroncSemestresByFormationAndCampagne($formation, $campagne);

                foreach ($semestresTronc as $semestreTronc) {
                    $report['totals']['semestresTroncCommun']++;
                    $semestreReport = $this->processSemestreTronc(
                        $formation,
                        $semestreTronc,
                        (int)$parcoursPorteurId,
                        $campagne,
                        $apply,
                        $allowCrossFormation,
                        $report,
                    );

                    $entry['semestres'][] = $semestreReport;
                }

                $report['formations'][] = $entry;
            }

            if ($apply) {
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            }
        } catch (\Throwable $e) {
            if ($apply && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }

            throw $e;
        }

        return $report;
    }

    /**
     * @return Formation[]
     */
    public function listFormationsWithTroncCommun(CampagneCollecte $campagne, array $formationIds = []): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT f')
            ->from(Formation::class, 'f')
            ->join('f.parcours', 'p')
            ->join('p.semestreParcours', 'sp')
            ->join('sp.semestre', 's')
            ->join('p.dpeParcours', 'dp')
            ->andWhere('dp.campagneCollecte = :campagne')
            ->andWhere('s.troncCommun = true')
            ->setParameter('campagne', $campagne)
            ->orderBy('f.id', 'ASC');

        if ($formationIds !== []) {
            $qb
                ->andWhere('f.id IN (:formationIds)')
                ->setParameter('formationIds', $formationIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Semestre[]
     */
    private function findTroncSemestresByFormationAndCampagne(Formation $formation, CampagneCollecte $campagne): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT s')
            ->from(Semestre::class, 's')
            ->join('s.semestreParcours', 'sp')
            ->join('sp.parcours', 'p')
            ->join('p.formation', 'f')
            ->join('p.dpeParcours', 'dp')
            ->andWhere('f = :formation')
            ->andWhere('dp.campagneCollecte = :campagne')
            ->andWhere('s.troncCommun = true')
            ->setParameter('formation', $formation)
            ->setParameter('campagne', $campagne)
            ->orderBy('s.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function processSemestreTronc(
        Formation        $formation,
        Semestre         $semestreTronc,
        int              $parcoursPorteurId,
        CampagneCollecte $campagne,
        bool             $apply,
        bool             $allowCrossFormation,
        array            &$report,
    ): array
    {
        $semestreId = $semestreTronc->getId();
        $ordre = $semestreTronc->getOrdre();

        $semestreReport = [
            'semestreId' => $semestreId,
            'ordre' => $ordre,
            'detached' => 0,
            'createdMutualisation' => false,
            'warnings' => [],
        ];

        if ($semestreId === null || $ordre === null) {
            $report['totals']['warnings']++;
            $semestreReport['warnings'][] = 'Semestre invalide (id ou ordre manquant), ignoré.';

            return $semestreReport;
        }

        $relatedFormationIds = $this->findRelatedFormationIdsForSemestre($semestreTronc);
        if (count($relatedFormationIds) > 1 && !$allowCrossFormation) {
            $report['totals']['warnings']++;
            $semestreReport['warnings'][] = sprintf(
                'Semestre partagé entre plusieurs formations (%s), ignoré sans --allow-cross-formation.',
                implode(', ', $relatedFormationIds),
            );

            return $semestreReport;
        }

        $semestresParcours = $this->findSemestreParcoursByFormationOrdreAndCampagne($formation, $ordre, $campagne);

        $porteurSp = null;
        foreach ($semestresParcours as $sp) {
            if ($sp->getParcours()?->getId() === $parcoursPorteurId) {
                $porteurSp = $sp;
                break;
            }
        }

        if (!$porteurSp instanceof SemestreParcours) {
            $report['totals']['errors']++;
            $semestreReport['warnings'][] = sprintf(
                'Aucun semestre parcours trouvé pour le parcours porteur %d à l\'ordre %d.',
                $parcoursPorteurId,
                $ordre,
            );

            return $semestreReport;
        }

        if ($porteurSp->getSemestre()?->getId() !== $semestreId) {
            $report['totals']['warnings']++;
            $semestreReport['warnings'][] = 'Le parcours porteur ne pointe pas sur le semestre tronc commun (déjà migré ou incohérent).';

            return $semestreReport;
        }

        $mutualisation = $this->entityManager->getRepository(SemestreMutualisable::class)->findOneBy([
            'semestre' => $semestreTronc,
            'parcours' => $porteurSp->getParcours(),
        ]);

        if ($mutualisation === null) {
            $semestreReport['createdMutualisation'] = true;
            $report['totals']['mutualisationsCreated']++;

            if ($apply) {
                $mutualisation = new SemestreMutualisable();
                $mutualisation->setSemestre($semestreTronc);
                $mutualisation->setParcours($porteurSp->getParcours());
                $this->entityManager->persist($mutualisation);
            }
        }

        foreach ($semestresParcours as $sp) {
            if ($sp->getParcours()?->getId() === $parcoursPorteurId) {
                continue;
            }

            $currentSemestre = $sp->getSemestre();
            if ($currentSemestre === null) {
                $report['totals']['warnings']++;
                $semestreReport['warnings'][] = sprintf('SemestreParcours %d sans semestre, ignoré.', (int)$sp->getId());
                continue;
            }

            if ($currentSemestre->getId() !== $semestreId) {
                $raccroche = $currentSemestre->getSemestreRaccroche();
                if ($raccroche?->getSemestre()?->getId() === $semestreId) {
                    $report['totals']['alreadyDetached']++;
                } else {
                    $report['totals']['warnings']++;
                    $semestreReport['warnings'][] = sprintf(
                        'SemestreParcours %d pointe sur un autre semestre (%d), ignoré.',
                        (int)$sp->getId(),
                        (int)$currentSemestre->getId(),
                    );
                }
                continue;
            }

            $report['totals']['semestresDetaches']++;
            $semestreReport['detached']++;

            if (!$apply || !$mutualisation instanceof SemestreMutualisable) {
                continue;
            }

            $semestreDetache = $this->createDetachedSemestre($semestreTronc);
            $semestreDetache->setSemestreRaccroche($mutualisation);
            $this->entityManager->persist($semestreDetache);

            // On met a jour explicitement les deux cotes pour garder les collections coherentes en memoire.
            $semestreTronc->removeSemestreParcour($sp);
            $semestreDetache->addSemestreParcour($sp);
        }

        if ($apply && $semestreReport['detached'] > 0 && $this->canDisableTroncCommun($semestreTronc, $formation)) {
            $semestreTronc->setTroncCommun(false);
        }

        return $semestreReport;
    }

    /**
     * @return int[]
     */
    private function findRelatedFormationIdsForSemestre(Semestre $semestre): array
    {
        $formationIds = [];
        foreach ($semestre->getSemestreParcours() as $sp) {
            $formationId = $sp->getParcours()?->getFormation()?->getId();
            if ($formationId !== null) {
                $formationIds[$formationId] = $formationId;
            }
        }

        sort($formationIds);

        return array_values($formationIds);
    }

    /**
     * @return SemestreParcours[]
     */
    private function findSemestreParcoursByFormationOrdreAndCampagne(Formation $formation, int $ordre, CampagneCollecte $campagne): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT sp, p, s')
            ->from(SemestreParcours::class, 'sp')
            ->join('sp.parcours', 'p')
            ->join('sp.semestre', 's')
            ->join('p.formation', 'f')
            ->join('p.dpeParcours', 'dp')
            ->andWhere('f = :formation')
            ->andWhere('dp.campagneCollecte = :campagne')
            ->andWhere('sp.ordre = :ordre')
            ->setParameter('formation', $formation)
            ->setParameter('campagne', $campagne)
            ->setParameter('ordre', $ordre)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function createDetachedSemestre(Semestre $source): Semestre
    {
        $semestre = new Semestre();
        $semestre->setOrdre((int)$source->getOrdre());
        $semestre->setCommentaire($source->getCommentaire());
        $semestre->setNonDispense((bool)$source->isNonDispense());
        $semestre->setCodeApogee($source->getCodeApogee());
        $semestre->setTroncCommun(false);

        return $semestre;
    }

    private function canDisableTroncCommun(Semestre $semestreTronc, Formation $formation): bool
    {
        $formationIds = $this->findRelatedFormationIdsForSemestre($semestreTronc);

        return count($formationIds) === 1 && $formationIds[0] === $formation->getId();
    }
}

