<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\PlateformeAdmission;
use App\Entity\TypeDiplome;
use App\Entity\TypeDiplomePlateformeAdmission;
use App\Repository\CampagneCollecteRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

readonly class TypeDiplomePlateformeService
{
    public function __construct(
        private EntityManagerInterface                   $entityManager,
        private TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository
    )
    {
    }

    /**
     * Synchronise les plateformes d'admission sélectionnées pour un type de diplôme
     *
     * @param TypeDiplome $typeDiplome
     * @param array $plateformesData Liste des données [['plateforme' => PlateformeAdmission, 'annees' => [1, 2, 3]], ...]
     * @param CampagneCollecte|null $campagne Campagne à utiliser (null = campagne par défaut)
     */
    public function syncPlateformes(TypeDiplome $typeDiplome, array $plateformesData, CampagneCollecte $campagne): void
    {
        // Récupérer les associations existantes pour cette campagne
        $existingAssociations = $this->typeDiplomePlateformeAdmissionRepository->findBy([
            'typeDiplome' => $typeDiplome,
            'campagne' => $campagne
        ]);

        // Créer un map des associations existantes par ID de plateforme
        $existingMap = [];
        foreach ($existingAssociations as $association) {
            $plateformeId = $association->getPlateforme()?->getId();
            if ($plateformeId) {
                $existingMap[$plateformeId] = $association;
            }
        }

        // Créer un tableau des IDs de plateformes sélectionnées
        $selectedPlateformeIds = [];
        foreach ($plateformesData as $data) {
            if (isset($data['plateforme']) && $data['plateforme'] instanceof PlateformeAdmission) {
                $selectedPlateformeIds[] = $data['plateforme']->getId();
            }
        }

        // Supprimer les associations qui ne sont plus sélectionnées
        foreach ($existingAssociations as $association) {
            $plateformeId = $association->getPlateforme()?->getId();
            if (!in_array($plateformeId, $selectedPlateformeIds, true)) {
                $this->entityManager->remove($association);
            }
        }

        // Ajouter ou mettre à jour les associations
        foreach ($plateformesData as $data) {
            if (!isset($data['plateforme']) || !($data['plateforme'] instanceof PlateformeAdmission)) {
                continue;
            }

            $plateforme = $data['plateforme'];
            $annees = $data['annees'] ?? [];
            $plateformeId = $plateforme->getId();

            if (isset($existingMap[$plateformeId])) {
                // Mettre à jour l'association existante
                $existingMap[$plateformeId]->setAnnees($annees);
            } else {
                // Créer une nouvelle association
                $association = new TypeDiplomePlateformeAdmission();
                $association->setTypeDiplome($typeDiplome);
                $association->setPlateforme($plateforme);
                $association->setCampagne($campagne);
                $association->setAnnees($annees);
                $this->entityManager->persist($association);
            }
        }

        $this->entityManager->flush();
    }
}
