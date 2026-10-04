<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Parcours/GenereStructureParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/02/2026 10:50
 */

namespace App\Service\Parcours;

use App\Entity\Annee;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use Doctrine\ORM\EntityManagerInterface;

class GenereStructureParcours
{

    public function __construct(protected EntityManagerInterface $entityManager)
    {
    }


    public function genereStructureParcours(Parcours $parcours): void
    {
        // Génération sécurisée : créer chaque semestre entre debut et fin (inclus)
        // et lier au parcours si nécessaire. La logique de regroupement "année"
        // (impair puis pair) est une règle d'affichage/traitement qui peut être
        // appliquée côté UI ou consommateur du service.

        $start = (int)$parcours->getSemestreDebut();
        $end = (int)$parcours->getSemestreFin();

        if ($start < $end) {
            $arborescence = [];

            for ($i = $start; $i <= $end; $i++) {

                // On calcule le numéro de l'année (S1,S2 -> An 1 | S3,S4 -> An 2)
                $anneeNum = (int)ceil($i / 2);

                // Si l'année n'existe pas encore dans notre tableau, on l'initialise
                if (!isset($arborescence[$anneeNum])) {
                    $annee = new Annee();
                    $annee->setParcours($parcours);
                    $annee->setOrdre($anneeNum);
                    $this->entityManager->persist($annee);
                    $arborescence[$anneeNum] = $annee;
                }

                $semestre = new Semestre();
                $semestre->setOrdre($i);
                $this->entityManager->persist($semestre);

                $sp = new SemestreParcours($semestre, $parcours);
                $sp->setAnnee($arborescence[$anneeNum]);
                $arborescence[$anneeNum]->addParcoursSemestre($sp);
                $this->entityManager->persist($sp);
                $parcours->addSemestreParcour($sp);
                $semestre->addSemestreParcour($sp);
            }
        }
        $this->entityManager->flush();
    }
}
