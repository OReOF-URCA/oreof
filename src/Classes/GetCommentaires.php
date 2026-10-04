<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/GetCommentaire.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 06/10/2023 07:58
 */

namespace App\Classes;

use App\Entity\CommentaireFicheMatiere;
use App\Entity\CommentaireParcours;
use App\Entity\CommentaireFormation;
use App\Entity\User;
use App\Repository\CommentaireFicheMatiereRepository;
use App\Repository\CommentaireFormationRepository;
use App\Repository\CommentaireParcoursRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class GetCommentaires
{
    public function __construct(
        protected CommentaireParcoursRepository     $commentaireParcoursRepository,
        protected CommentaireFormationRepository    $commentaireFormationRepository,
        protected CommentaireFicheMatiereRepository $commentaireFicheMatiereRepository,
        protected FormationRepository               $formationRepository,
        protected ParcoursRepository                $parcoursRepository,
        protected FicheMatiereRepository            $ficheMatiereRepository,
        protected EntityManagerInterface            $entityManager
    ) {
    }


    public function getCommentaires(int $id, string $type, string $zone): array
    {
        return match ($type) {
            'formation' => $this->commentaireFormationRepository->findByZone($id, $zone),
            'parcours' => $this->commentaireParcoursRepository->findByZone($id, $zone),
            'ficheMatiere' => $this->commentaireFicheMatiereRepository->findByZone($id, $zone),
            default => [],
        };

    }

    public function getCommentairesByUser(int $id, string $type, string $zone, UserInterface $user): array
    {
        return match ($type) {
            'formation' => $this->commentaireFormationRepository->findByZoneUser($id, $zone, $user),
            'parcours' => $this->commentaireParcoursRepository->findByZoneUser($id, $zone, $user),
            'ficheMatiere' => $this->commentaireFicheMatiereRepository->findByZoneUser($id, $zone, $user),
            default => [],
        };

    }

    public function ajoutCommentaire(int $id, string $type, string $zone, string $message, ?UserInterface $user): void
    {
        $userEntity = $user instanceof User ? $user : null;

        switch ($type) {
            case 'formation':
                $formation = $this->formationRepository->find($id);
                $commentaire = new CommentaireFormation($userEntity, $message, $zone);
                $commentaire->setFormation($formation);
                $this->entityManager->persist($commentaire);
                break;
            case 'parcours':
                $parcours = $this->parcoursRepository->find($id);
                $commentaire = new CommentaireParcours($userEntity, $message, $zone);
                $commentaire->setParcours($parcours);
                $this->entityManager->persist($commentaire);
                break;
            case 'ficheMatiere':
                $ficheMatiere = $this->ficheMatiereRepository->find($id);
                $commentaire = new CommentaireFicheMatiere($userEntity, $message, $zone);
                $commentaire->setFicheMatiere($ficheMatiere);
                $this->entityManager->persist($commentaire);
                break;
        }

        $this->entityManager->flush();
    }

    public function getAllCommentairesByUser(?UserInterface $getUser): array
    {
        return array_merge(
            $this->commentaireFormationRepository->findByUser($getUser),
            $this->commentaireParcoursRepository->findByUser($getUser),
            $this->commentaireFicheMatiereRepository->findByUser($getUser)
        );
    }
}
