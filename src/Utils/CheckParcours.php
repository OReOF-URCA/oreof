<?php

namespace App\Utils;

use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\SemestreMutualisable;
use App\Entity\Ue;
use App\Entity\UeMutualisable;
use Doctrine\ORM\EntityManagerInterface;

class CheckParcours {

    public function __construct(
        private EntityManagerInterface $em
    ){}

    public function checkParcoursIsSafeToDelete(int $idParcours) {
        $linkedData = [
            'parcoursDisplay' => '',
            'semestres' => [],
            'ues' => [],
            'fiches_matieres' => []
        ];

        /** @var Parcours $parcoursToCheck */
        $parcoursToCheck = $this->em->getRepository(Parcours::class)->findOneById($idParcours);

        $linkedData['parcoursDisplay']  = $parcoursToCheck->getFormation()->getDisplayLong(true) 
            . ' - ' . $parcoursToCheck->getDisplay();
        foreach($parcoursToCheck->getSemestreParcours() as $sp){
            $isSemestreRaccroche = count($this->em->getRepository(SemestreMutualisable::class)
                ->findBy(['semestre' => $sp->getSemestre()])) > 0;
            if($isSemestreRaccroche){
                $linkedData['semestres'][] = [
                    'id' => $sp->getSemestre()->getId(),
                    'display' => $sp->getSemestre()->display()
                ];
            }
            /** @var Ue[] $ueToCheck */
            $ueToCheck = $this->em->getRepository(Ue::class)->getBySemestre($sp->getSemestre());
            foreach($ueToCheck as $ue){
                $isUeRaccrochee = count($this->em->getRepository(UeMutualisable::class)
                    ->findBy(['ue' => $ue])) > 0;
                if($isUeRaccrochee){
                    $linkedData['ues'][] = [
                        'id' => $ue->getId(),
                        'display' => $ue->display() . ($ue->getLibelle() ? ' - ' . $ue->getLibelle() : '')
                    ];
                }
            }
        }

        /** @var FicheMatiere[] $ficheMatiereArray */
        $ficheMatiereArray = $this->em->getRepository(FicheMatiere::class)->findBy(['parcours' => $parcoursToCheck]);
        foreach($ficheMatiereArray as $fm) {
            if(count($fm->getFicheMatiereParcours()) > 0){
                $mutualisations = [];
                foreach($fm->getFicheMatiereParcours() as $fmMutu){
                    $mutualisations[] = [
                        'parcours_id' => $fmMutu->getParcours()->getId(),
                        'parcours_libelle' => ($fmMutu->getParcours()?->getFormation()?->getDisplayLong(true) ?? '')
                            . ' - ' . $fmMutu->getParcours()->getDisplay()
                    ];
                }   
                $linkedData['fiches_matieres'][] = [
                    'id' => $fm->getId(),
                    'display' => $fm->getDisplay(),
                    'mutualisations' => $mutualisations
                ];
            }
        }

        return $linkedData;

    }   

    public function checkFormationIsSafeToDelete(array $idParcours) {
        return array_map(function($id) {
            return $this->checkParcoursIsSafeToDelete($id);
        }, $idParcours);
    }
}