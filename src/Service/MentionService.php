<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/MentionService.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/05/2025 22:38
 */

namespace App\Service;

use App\DTO\MentionDto;
use App\Entity\Domaine;
use App\Entity\Mention;
use App\Entity\TypeDiplome;
use App\Repository\DomaineRepository;
use App\Repository\MentionRepository;
use App\Repository\TypeDiplomeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service responsable de la gestion des mentions.
 * Centralise la logique métier liée aux mentions pour éviter la duplication de code
 * et alléger les contrôleurs.
 */
class MentionService
{
    // Tableau des codes possibles pour les parcours
    private const TAB_CODE_PARCOURS = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'Y', 'Z',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MentionRepository      $mentionRepository,
        private readonly DomaineRepository      $domaineRepository,
        private readonly TypeDiplomeRepository  $typeDiplomeRepository
    )
    {
    }

    /**
     * Récupère toutes les mentions avec possibilité de filtrage et tri.
     *
     * @param string|null $search Terme de recherche
     * @param string $sort Champ de tri
     * @param string $direction Direction du tri (asc/desc)
     * @param int|null $limit Limite de résultats
     * @param int|null $offset Offset pour la pagination
     * @return array Liste des mentions
     */
    public function getAllMentions(?string $search = null, string $sort = 'type_diplome', string $direction = 'asc', ?int $limit = null, ?int $offset = null): array
    {
        return $this->mentionRepository->findBySearch($search, $sort, $direction, $limit, $offset);
    }

    /**
     * Compte le nombre total de mentions correspondant aux critères de recherche.
     *
     * @param string|null $search Terme de recherche
     * @return int Nombre total de mentions
     */
    public function countAllMentions(?string $search = null): int
    {
        return $this->mentionRepository->countBySearch($search);
    }

    /**
     * Récupère une mention par son ID.
     *
     * @param int $id ID de la mention
     * @return Mention|null La mention trouvée ou null
     * @throws NotFoundHttpException Si la mention n'existe pas
     */
    public function getMentionById(int $id): ?Mention
    {
        $mention = $this->mentionRepository->find($id);

        if (!$mention) {
            throw new NotFoundHttpException(sprintf('Mention avec ID %d non trouvée', $id));
        }

        return $mention;
    }

    /**
     * Crée une nouvelle mention à partir d'un DTO.
     *
     * @param MentionDto $mentionDto DTO contenant les données de la mention
     * @return Mention La mention créée
     */
    public function createMention(MentionDto $mentionDto): Mention
    {
        $mention = new Mention();
        $this->updateMentionFromDto($mention, $mentionDto);

        $this->mentionRepository->save($mention, true);

        return $mention;
    }

    /**
     * Met à jour une mention existante à partir d'un DTO.
     *
     * @param Mention $mention La mention à mettre à jour
     * @param MentionDto $mentionDto DTO contenant les nouvelles données
     * @return Mention La mention mise à jour
     */
    public function updateMention(Mention $mention, MentionDto $mentionDto): Mention
    {
        $this->updateMentionFromDto($mention, $mentionDto);

        $this->mentionRepository->save($mention, true);

        return $mention;
    }

    /**
     * Duplique une mention existante.
     *
     * @param Mention $mention La mention à dupliquer
     * @return Mention La nouvelle mention créée
     */
    public function duplicateMention(Mention $mention): Mention
    {
        $mentionNew = clone $mention;
        $mentionNew->setLibelle($mention->getLibelle() . ' - Copie');

        $this->mentionRepository->save($mentionNew, true);

        return $mentionNew;
    }

    /**
     * Supprime une mention.
     *
     * @param Mention $mention La mention à supprimer
     * @return bool Succès de l'opération
     */
    public function deleteMention(Mention $mention): bool
    {
        try {
            $this->mentionRepository->remove($mention, true);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Génère les codes Apogée pour les mentions selon les critères spécifiés.
     *
     * @param Request $request La requête contenant les filtres
     * @return bool Succès de l'opération
     */
    public function generateCodification(Request $request): bool
    {
        $checkTypeDiplome = $request->request->all()['typediplome'] ?? null;
        $checkTypeDomaine = $request->request->all()['domaine'] ?? null;

        if (null === $checkTypeDiplome || null === $checkTypeDomaine) {
            return false;
        }

        $typeDiplomes = $this->getTypeDiplomesFromRequest($checkTypeDiplome);
        $domaines = $this->getDomainesFromRequest($checkTypeDomaine);

        foreach ($typeDiplomes as $typeDiplome) {
            foreach ($domaines as $domaine) {
                $this->processCodificationForTypeDiplomeAndDomaine($typeDiplome, $domaine);
            }
        }

        $this->entityManager->flush();
        return true;
    }

    /**
     * Met à jour les propriétés d'une mention à partir d'un DTO.
     *
     * @param Mention $mention La mention à mettre à jour
     * @param MentionDto $mentionDto DTO contenant les données
     */
    private function updateMentionFromDto(Mention $mention, MentionDto $mentionDto): void
    {
        $mention->setLibelle($mentionDto->libelle);
        $mention->setSigle($mentionDto->sigle);
        $mention->setCodeApogee($mentionDto->codeApogee);
        $mention->setTypeDiplome($mentionDto->typeDiplomeId);
        foreach ($mentionDto->domaines as $idDomaine) {
            $domaine = $this->domaineRepository->find($idDomaine);
            if ($domaine && !$mention->getDomaines()->contains($domaine)) {
                $mention->addDomaine($domaine);
            }
        }
    }

    /**
     * Récupère les types de diplômes à partir de la requête.
     *
     * @param mixed $checkTypeDiplome Valeur du paramètre typediplome
     * @return array Liste des types de diplômes
     */
    private function getTypeDiplomesFromRequest(mixed $checkTypeDiplome): array
    {
        if ($checkTypeDiplome === 'all') {
            return $this->typeDiplomeRepository->findBy([], ['libelle' => 'ASC']);
        }

        return is_array($checkTypeDiplome) ? $checkTypeDiplome : [$checkTypeDiplome];
    }

    /**
     * Récupère les domaines à partir de la requête.
     *
     * @param mixed $checkTypeDomaine Valeur du paramètre domaine
     * @return array Liste des domaines
     */
    private function getDomainesFromRequest(mixed $checkTypeDomaine): array
    {
        if ($checkTypeDomaine === 'all') {
            return $this->domaineRepository->findBy([], ['libelle' => 'ASC']);
        }

        return is_array($checkTypeDomaine) ? $checkTypeDomaine : [$checkTypeDomaine];
    }

    /**
     * Traite la codification pour un type de diplôme et un domaine spécifiques.
     *
     * @param TypeDiplome $typeDiplome Type de diplôme
     * @param Domaine $domaine Domaine
     */
    private function processCodificationForTypeDiplomeAndDomaine(TypeDiplome $typeDiplome, Domaine $domaine): void
    {
        $mentions = $this->mentionRepository->findBy(
            ['typeDiplome' => $typeDiplome, 'domaine' => $domaine],
            ['libelle' => 'ASC']
        );

        $codeLettre = 0;

        foreach ($mentions as $mention) {
            $offset = 1;
            $mention->setCodeApogee(self::TAB_CODE_PARCOURS[$codeLettre]);

            // Traitement des formations et parcours
            foreach ($mention->getFormations() as $formation) {
                foreach ($formation->getParcours() as $parcours) {
                    if (
                        count($formation->getParcours()) > 1 &&
                        $parcours->isParcoursDefaut() === false &&
                        $parcours->getComposanteInscription() !== $formation->getComposantePorteuse() &&
                        $parcours->getComposanteInscription()?->getComposanteParent() === null
                    ) {
                        // Plusieurs parcours sur des composantes porteurs différentes
                        $parcours->setCodeMentionApogee(self::TAB_CODE_PARCOURS[$codeLettre]);
                        $offset++;
                    } else {
                        $parcours->setCodeMentionApogee(self::TAB_CODE_PARCOURS[$codeLettre]);
                    }
                }
            }

            $codeLettre = $codeLettre + $offset;
        }
    }
}
