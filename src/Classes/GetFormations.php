<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/GetFormations.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/02/2024 08:57
 */

namespace App\Classes;

use App\Entity\CampagneCollecte;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Repository\FormationRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class GetFormations
{

    public function __construct(
        private FormationRepository $formationRepository,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    public function getFormations(
        User|UserInterface $user,
        CampagneCollecte $campagneCollecte,
        array $options = [],
        bool $isCfvu = false
    ): array {
        $sort = $options['sort'] ?? 'typeDiplome';
        $direction = $options['direction'] ?? 'asc';
        $q = $options['q'] ?? null;

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('SHOW', ['route' => 'app_etablissement', 'subject' => 'etablissement'])) {
            $formations = $this->formationRepository->findBySearch($q, $campagneCollecte, $options);
        } else {

            $formations = [];
            $canListNonClassique = false;
            //gérer le cas ou l'utilisateur dispose des droits pour lire la composante
            $centres = $user?->getUserProfils();
            $tempFormation = [];
            /** @var UserProfil $centre */
            foreach ($centres as $centre) {
                if ($centre->getProfil()?->getId() === 20) {
                    $canListNonClassique = true;
                }

                if (
                    $centre->getComposante() !== null &&
                    $this->authorizationChecker->isGranted('SHOW', ['route' => 'app_composante', 'subject' => $centre->getComposante()])) {
                    $formations[] = $this->formationRepository->findByComposante(
                        $centre->getComposante(),
                        $campagneCollecte,
                        [$sort => $direction]
                    );
                }

                if ($centre->getFormation() !== null) {
                    $formation = $centre->getFormation();
                    if ($formation->getDpe()->getId() === $campagneCollecte->getId()) {
                        $tempFormation[] = $formation;
                    }
                }
            }

            $formations[] = $tempFormation;

            if ($canListNonClassique) {
                $formations[] = array_filter(
                    $this->formationRepository->findBySearch($q, $campagneCollecte, $options),
                    static fn($formation) => $formation->getTypeDiplome()?->isClassique() === false
                );
            }

            $formations[] = $this->formationRepository->findByComposanteDpe(
                $user,
                $campagneCollecte,
                [$sort => $direction]
            );
            $formations[] = $this->formationRepository->findByResponsableOuCoResponsable(
                $user,
                $campagneCollecte,
                [$sort => $direction]
            );
            $formations[] = $this->formationRepository->findByResponsableOuCoResponsableParcours(
                $user,
                $campagneCollecte,
                [$sort => $direction]
            );

            $formations = array_merge(...$formations);
        }
        $tFormations = [];
        foreach ($formations as $formation) {
            $tFormations[$formation->getId()] = $formation;
        }

        return $tFormations;
    }
}
