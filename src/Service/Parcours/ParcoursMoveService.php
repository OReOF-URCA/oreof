<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\DTO\ParcoursMoveAnalysis;
use App\Entity\BlocCompetence;
use App\Entity\ChangeParcours;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ParcoursMoveService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    public function analyze(Parcours $parcours): ParcoursMoveAnalysis
    {
        $currentFormation = $parcours->getFormation();
        $isSeulParcours = false;
        if ($currentFormation !== null) {
            $isSeulParcours = $currentFormation->getParcours()->count() <= 1;
        }

        $nbDpeParcours = $parcours->getDpeParcours()->count();
        $nbDpeDemandes = $this->entityManager->getRepository(DpeDemande::class)->count(['parcours' => $parcours]);
        $nbBlocCompetences = $parcours->getBlocCompetences()->count();
        $nbChangeParcours = $this->entityManager->getRepository(ChangeParcours::class)->count(['parcours' => $parcours]);
        $nbSemestres = $parcours->getSemestreParcours()->count();

        return new ParcoursMoveAnalysis(
            parcours: $parcours,
            currentFormation: $currentFormation,
            isParcoursDefaut: (bool) $parcours->isParcoursDefaut(),
            isSeulParcoursDeLaFormation: $isSeulParcours,
            nbDpeParcours: $nbDpeParcours,
            nbDpeDemandes: $nbDpeDemandes,
            nbBlocCompetences: $nbBlocCompetences,
            nbChangeParcours: $nbChangeParcours,
            nbSemestres: $nbSemestres,
        );
    }

    /**
     * Retourne les formations éligibles pour accueillir le parcours,
     * groupées par composante porteuse.
     *
     * @return array<string, list<Formation>>
     */
    public function getEligibleFormations(Parcours $parcours): array
    {
        $currentFormation = $parcours->getFormation();
        $campagne = $currentFormation?->getDpe();
        if ($campagne === null) {
            /** @var DpeParcours|null $firstDpe */
            $firstDpe = $parcours->getDpeParcours()->first() ?: null;
            $campagne = $firstDpe?->getCampagneCollecte();
        }

        if ($campagne === null) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f', 'c', 'td', 'm')
            ->from(Formation::class, 'f')
            ->leftJoin('f.composantePorteuse', 'c')
            ->leftJoin('f.typeDiplome', 'td')
            ->leftJoin('f.mention', 'm')
            ->where('f.dpe = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('c.libelle', 'ASC')
            ->addOrderBy('td.libelle', 'ASC');

        if ($currentFormation !== null && $currentFormation->getId() !== null) {
            $qb->andWhere('f.id != :currentFormationId')
                ->setParameter('currentFormationId', $currentFormation->getId());
        }

        /** @var list<Formation> $formations */
        $formations = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($formations as $formation) {
            $composanteLibelle = $formation->getComposantePorteuse()?->getLibelle() ?? 'Sans composante';
            $grouped[$composanteLibelle][] = $formation;
        }

        return $grouped;
    }

    /**
     * Déplace un parcours vers une nouvelle formation et met à jour
     * toutes les entités rattachées (DpeParcours, DpeDemande, BlocCompetence, ChangeParcours).
     */
    public function move(Parcours $parcours, Formation $newFormation): void
    {
        $oldFormation = $parcours->getFormation();

        if ($oldFormation !== null && $oldFormation->getId() !== null && $oldFormation->getId() === $newFormation->getId()) {
            throw new LogicException('Le parcours est déjà rattaché à cette formation.');
        }

        $oldCampagne = $oldFormation?->getDpe();
        $newCampagne = $newFormation->getDpe();
        if ($oldCampagne !== null && $newCampagne !== null && $oldCampagne->getId() !== $newCampagne->getId()) {
            throw new LogicException('La formation de destination doit appartenir à la même campagne de collecte.');
        }

        // 1. Détachement de l'ancienne formation et mise à jour de hasParcours
        if ($oldFormation !== null) {
            $oldFormation->getParcours()->removeElement($parcours);
            if ($oldFormation->getParcours()->isEmpty()) {
                $oldFormation->setHasParcours(false);
            } elseif ($oldFormation->getParcours()->count() === 1) {
                /** @var Parcours $remainingParcours */
                $remainingParcours = $oldFormation->getParcours()->first();
                if ($remainingParcours->isParcoursDefaut()) {
                    $oldFormation->setHasParcours(false);
                }
            }
        }

        // 2. Rattachement du Parcours à la nouvelle formation
        $parcours->setFormation($newFormation);
        $newFormation->addParcour($parcours);
        $newFormation->setHasParcours(true);

        if ($parcours->isParcoursDefaut()) {
            $parcours->setLibelle(sprintf('Parcours %s', $oldFormation?->getDisplay() ?? 'transféré'));
        }

        // 3. Mise à jour des DpeParcours
        foreach ($parcours->getDpeParcours() as $dpeParcours) {
            $dpeParcours->setFormation($newFormation);
        }

        // 4. Mise à jour des DpeDemande
        $dpeDemandeRepo = $this->entityManager->getRepository(DpeDemande::class);
        /** @var list<DpeDemande> $demandes */
        $demandes = $dpeDemandeRepo->findBy(['parcours' => $parcours]);
        foreach ($demandes as $demande) {
            $demande->setFormation($newFormation);
        }

        // 5. Mise à jour des ChangeParcours
        $changeParcoursRepo = $this->entityManager->getRepository(ChangeParcours::class);
        /** @var list<ChangeParcours> $changes */
        $changes = $changeParcoursRepo->findBy(['parcours' => $parcours]);
        foreach ($changes as $change) {
            $change->setFormation($newFormation);
        }

        // 6. Mise à jour des BlocCompetence
        foreach ($parcours->getBlocCompetences() as $bloc) {
            $bloc->setFormation($newFormation);
        }

        $this->entityManager->flush();

        $userIdentifier = $this->security->getUser()?->getUserIdentifier() ?? 'system';
        $this->logger->info(sprintf(
            'Parcours #%d « %s » déplacé de la formation #%d (« %s ») à la formation #%d (« %s ») par %s.',
            (int) $parcours->getId(),
            (string) $parcours->getLibelle(),
            $oldFormation?->getId() ?? 0,
            $oldFormation?->getDisplay() ?? 'N/A',
            (int) $newFormation->getId(),
            $newFormation->getDisplay(),
            $userIdentifier
        ));
    }
}
