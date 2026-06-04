<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ParcoursSaveController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Bcc;
use App\Classes\GetDpeParcours;
use App\Classes\UpdateEntity;
use App\Classes\verif\ParcoursState;
use App\Entity\Parcours;
use App\Enums\EtatRemplissageEnum;
use App\Enums\ModaliteEnseignementEnum;
use App\Enums\NiveauLangueEnum;
use App\Events\AddCentreParcoursEvent;
use App\Events\NotifCentreFormationEvent;
use App\Events\NotifCentreParcoursEvent;
use App\Repository\ComposanteRepository;
use App\Repository\ProfilRepository;
use App\Repository\RythmeFormationRepository;
use App\Repository\UserRepository;
use App\Repository\VilleRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ParcoursSaveController extends BaseController
{
    public function __construct(private WorkflowInterface $dpeParcoursWorkflow)
    {
    }


    /**
     * @throws JsonException
     */
    #[Route('/parcours/save/{parcours}', name: 'app_parcours_save')]
    public function save(
        ProfilRepository $profilRepository,
        Bcc $bcc,
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher,
        UserRepository $userRepository,
        ComposanteRepository $composanteRepository,
        VilleRepository $villeRepository,
        RythmeFormationRepository $rythmeFormationRepository,
        UpdateEntity $updateEntity,
        ParcoursState $parcoursState,
        Request $request,
        Parcours $parcours
    ): Response {
        $updateEntity->setGroups(['parcours:read']);
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        if (null === $dpeParcours) {
            return $this->json(['error' => 'DPE non trouvé']);
        }

        if (!$this->isGranted('EDIT', [
                'route' => 'app_parcours',
                'subject' => $dpeParcours,
            ]) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        //        if (!($this->parcoursWorkflow->can($parcours, 'valider_parcours') || $this->parcoursWorkflow->can(
        //            $parcours, 'autoriser')) && !$this->isGranted('ROLE_SES')) {
        //            //si on est pas dans un état qui permet de modifier la formation
        //            return $this->json('Vous ne pouvez plus modifier cette formation', Response::HTTP_FORBIDDEN);
        //todo: bloquant + pas erreur envoyée ou pas traitée dans JS
        //        }

        if ($this->dpeParcoursWorkflow->can($dpeParcours, 'autoriser')) {
            //un champ est modifié, on met à jour l'état
            $this->dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');
        }

        $data = JsonRequest::getFromRequest($request);
        $parcoursState->setParcours($parcours);

        switch ($data['action']) {
            case 'stateOnglet':
                $ong = substr($data['onglet'], 6);
                $val = $parcoursState->onglets()[$ong];
                //if $val => en-cours ou vide => désactiver le checkbox
                if ($val === EtatRemplissageEnum::EN_COURS || $val === EtatRemplissageEnum::VIDE) {
                    $etatSteps = $parcours->getEtatSteps();
                    $etatSteps[$ong] = false;
                    $parcours->setEtatSteps($etatSteps);
                    $em->flush();
                }

                return $this->json($val->badge());
            case 'yesNo':
                $rep = $updateEntity->saveYesNo($parcours, $data['field'], $data['value']);

                return $this->json($rep);
            case 'textarea':
            case 'selectWithoutEntity':
                $rep = $updateEntity->saveField($parcours, $data['field'], $data['value']);

                return $this->json($rep);
            case 'float':
                $rep = $updateEntity->saveField($parcours, $data['field'], (float)$data['value']);

                return $this->json($rep);
            case 'recopieBcc':
                $bcc->recopieBcc($parcours, $data['value']);

                return $this->json(true);
            case 'resetBcc':
                $bcc->resetBcc($parcours);

                return $this->json(true);
            case 'modalitesEnseignement':
                $rep = $updateEntity->saveField(
                    $parcours,
                    'modalitesEnseignement',
                    ModaliteEnseignementEnum::from($data['value'])
                );

                return $this->json($rep);
            case 'rythmeFormation':
                $rythme = $rythmeFormationRepository->find($data['value']);
                $rep = $updateEntity->saveField($parcours, 'rythmeFormation', $rythme);

                return $this->json($rep);
            case 'respParcours':
                $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);

                if (null === $profil) {
                    throw new \InvalidArgumentException('Profil ROLE_RESP_PARCOURS not found');
                }

                if ($parcours->getRespParcours() !== null) {
                    $event = new AddCentreParcoursEvent($parcours, $parcours->getRespParcours(), $profil, $this->getCampagneCollecte());
                    $eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                }

                if ($data['value'] !== null || $data['value'] !== '') {
                    $user = $userRepository->find($data['value']);
                    if ($user !== null) {
                        $rep = $updateEntity->saveField($parcours, 'respParcours', $user);
                        $event = new AddCentreParcoursEvent($parcours, $user, $profil, $this->getCampagneCollecte());
                        $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
                    }
                } else {
                    $rep = $updateEntity->saveField($parcours, 'respParcours', null);
                }

                return $this->json($rep);
            case 'coRespParcours':
                $profil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);

                if (null === $profil) {
                    throw new \InvalidArgumentException('Profil ROLE_CO_RESP_PARCOURS not found');
                }

                if ($parcours->getCoResponsable() !== null) {
                    $event = new AddCentreParcoursEvent($parcours, $parcours->getCoResponsable(), $profil, $this->getCampagneCollecte());
                    $eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                }

                if ($data['value'] !== null || $data['value'] !== '') {
                    $user = $userRepository->find($data['value']);
                    $rep = $updateEntity->saveField($parcours, 'coResponsable', $user);
                    $event = new AddCentreParcoursEvent($parcours, $user, $profil, $this->getCampagneCollecte());
                    $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
                } else {
                    $rep = $updateEntity->saveField($parcours, 'respParcours', null);
                }
                return $this->json($rep);
            case 'localisation':
                $ville = $villeRepository->find($data['value']);
                $rep = $updateEntity->saveField($parcours, 'localisation', $ville);

                return $this->json($rep);
            case 'niveauFrancais':
                $rep = $updateEntity->saveField($parcours, 'niveauFrancais', NiveauLangueEnum::tryFrom($data['value']));

                return $this->json($rep);
            case 'composanteInscription':
                $composante = $composanteRepository->find($data['value']);
                $rep = $updateEntity->saveField($parcours, 'composanteInscription', $composante);

                return $this->json($rep);
            case 'int':
                $rep = $updateEntity->saveField($parcours, $data['field'], (int)$data['value']);

                return $this->json($rep);
            case 'etatStep':
                $valideState = (bool)$data['isChecked'] === true ? $parcoursState->valideStep(
                    $data['value']
                ) : true;

                if ($valideState === true) {
                    $etatSteps = $parcours->getEtatSteps();
                    $step = $data['value'];
                    $etatSteps[$step] = $data['isChecked'];
                    $parcours->setEtatSteps($etatSteps);

                    $em->flush();

                    return $this->json(true);
                }

                return $this->json($valideState);
            case 'array':
                if ($data['isChecked'] === true) {
                    $rep = $updateEntity->addToArray($parcours, $data['field'], $data['value']);
                } else {
                    $rep = $updateEntity->removeToArray($parcours, $data['field'], $data['value']);
                }

                return $this->json($rep);
        }

        return $this->json(['error' => 'action inconnue']);
    }
}
