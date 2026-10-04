<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/EC/Heures.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/02/2026 16:48
 */

namespace App\Service\EC;

use App\Classes\GetElementConstitutif;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Events\McccUpdateEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\InputBag;

class Heures
{

    public function __construct(
        private EntityManagerInterface $entityManager,
    )
    {
    }

    public function saveHeures(
        ElementConstitutif              $elementConstitutif,
        Parcours                        $parcours,
        ElementConstitutif|FicheMatiere $ecHeures,
        InputBag                        $request,
    ): bool
    {
        //            $originalHeuresToText = $this->heuresToTexte($getElement->getFicheMatiereHeures());
        if (array_key_exists('heuresEnfantsIdentiques', $request->all()['ec_step4'])) {
            if ($elementConstitutif->getEcParent() !== null) {
                $elementConstitutif->getEcParent()->setHeuresEnfantsIdentiques((bool)$request->all()['ec_step4']['heuresEnfantsIdentiques']);
            } else {
                $elementConstitutif->setHeuresEnfantsIdentiques((bool)$request->all()['ec_step4']['heuresEnfantsIdentiques']);
            }
        } else {
            $elementConstitutif->setHeuresEnfantsIdentiques(false);
        }

        if (array_key_exists('sansHeure', $request->all())) {
            $elementConstitutif->setSansHeure($request->all()['sansHeure'] === 'on');
            $elementConstitutif->setVolumeTdPresentiel(0);
            $elementConstitutif->setVolumeTpPresentiel(0);
            $elementConstitutif->setVolumeCmPresentiel(0);
            $elementConstitutif->setVolumeCmDistanciel(0);
            $elementConstitutif->setVolumeTdDistanciel(0);
            $elementConstitutif->setVolumeTpDistanciel(0);
            $elementConstitutif->setVolumeTe(0);
            $newHeuresToText = '';
        } else {
            $elementConstitutif->setSansHeure(false);
            if ($elementConstitutif->getFicheMatiere() !== null &&
                $elementConstitutif->getFicheMatiere()->getParcours() !== null &&
                $elementConstitutif->getFicheMatiere()->getParcours()->getId() === $parcours->getId()) {
                //sauvegarde des heures sur la fiche matière
                $elementConstitutif->setHeuresSpecifiques(false);
                // $newHeuresToText = $this->heuresToTexte($ecHeures);
            } else {
                //sauvegarde des heures sur l'EC
                $elementConstitutif->setVolumeCmPresentiel($ecHeures->getVolumeCmPresentiel());
                $elementConstitutif->setVolumeTdPresentiel($ecHeures->getVolumeTdPresentiel());
                $elementConstitutif->setVolumeTpPresentiel($ecHeures->getVolumeTpPresentiel());
                $elementConstitutif->setVolumeCmDistanciel($ecHeures->getVolumeCmDistanciel());
                $elementConstitutif->setVolumeTdDistanciel($ecHeures->getVolumeTdDistanciel());
                $elementConstitutif->setVolumeTpDistanciel($ecHeures->getVolumeTpDistanciel());
                $elementConstitutif->setVolumeTe($ecHeures->getVolumeTe());
                $elementConstitutif->setHeuresSpecifiques(true);
                //                    $newHeuresToText = $this->heuresToTexte($elementConstitutif);
            }
        }

        $this->entityManager->flush();

        //            //evenement pour MCCC sur EC mis à jour
        //            $event = new McccUpdateEvent($elementConstitutif, $parcours);
        //            $event->setNewStructure($originalHeuresToText, $newHeuresToText);
        //            $eventDispatcher->dispatch($event, McccUpdateEvent::UPDATE_MCCC);

        return true;

    }
}
