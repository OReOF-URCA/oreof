<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereSaveController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\UpdateEntity;
use App\Classes\verif\FicheMatiereState;
use App\Entity\FicheMatiere;
use App\Enums\EtatRemplissageEnum;
use App\Enums\ModaliteEnseignementEnum;
use App\Repository\ButApprentissageCritiqueRepository;
use App\Repository\CompetenceRepository;
use App\Repository\LangueRepository;
use App\Repository\UserRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FicheMatiereSaveController extends BaseController
{
    /**
     * @throws TypeDiplomeNotFoundException
     * @throws JsonException
     */
    #[Route('/fiche_matiere/save/{ficheMatiere}', name: 'app_fiche_matiere_save')]
    public function save(
        ButApprentissageCritiqueRepository $butApprentissageCritiqueRepository,
        FicheMatiereState      $ficheMatiereState,
        EntityManagerInterface $entityManager,
        CompetenceRepository   $competenceRepository,
        UserRepository         $userRepository,
        LangueRepository       $langueRepository,
        UpdateEntity           $updateEntity,
        Request                $request,
        FicheMatiere           $ficheMatiere
    ): Response {
        $ficheMatiereState->setFicheMatiere($ficheMatiere);
        $updateEntity->setGroups(['fiche_matiere:read']);
        if (str_ends_with($ficheMatiere->getLibelle(), '(à compléter)')) {
            $ficheMatiere->setLibelle(trim(str_replace('(à compléter)', '', $ficheMatiere->getLibelle())));
            $entityManager->flush();
        }

        $data = JsonRequest::getFromRequest($request);
        switch ($data['action']) {
            case 'typeMatiere':
                return $updateEntity->saveField(
                    $ficheMatiere,
                    'typeMatiere',
                    $data['value']
                );
            case 'checkbox':
                return $updateEntity->saveYesNo(
                    $ficheMatiere,
                    $data['field'],
                    $data['isChecked']
                );
            case 'stateOnglet':
                $ong = substr($data['onglet'], 6);
                $val = $ficheMatiereState->onglets()[$ong];
                //if $val => en-cours ou vide => désactiver le checkbox
                if ($val === EtatRemplissageEnum::EN_COURS || $val === EtatRemplissageEnum::VIDE) {
                    $etatSteps = $ficheMatiere->getEtatSteps();
                    $etatSteps[$ong] = false;
                    $ficheMatiere->setEtatSteps($etatSteps);
                    $entityManager->flush();
                }

                return $this->json($val->badge());
            case 'yesNo':
                return $updateEntity->saveYesNo($ficheMatiere, $data['field'], $data['value']);
            case 'textarea':
            case 'selectWithoutEntity':
                return $updateEntity->saveField($ficheMatiere, $data['field'], $data['value']);
            case 'float':
                return $updateEntity->saveField($ficheMatiere, $data['field'], (float)$data['value']);
            case 'langue':
                return $updateEntity->saveCheckbox(
                    $ficheMatiere,
                    $data['field'],
                    $data['value'],
                    $data['isChecked'],
                    $langueRepository
                );
            case 'responsableFicheMatiere':
                $responsableFicheMatiere = $userRepository->find($data['value']);

                return $updateEntity->saveField($ficheMatiere, 'responsableFicheMatiere', $responsableFicheMatiere);
            case 'modalitesEnseignement':
                return $updateEntity->saveField(
                    $ficheMatiere,
                    'modaliteEnseignement',
                    ModaliteEnseignementEnum::from($data['value'])
                );

            case 'int':
                return $updateEntity->saveField($ficheMatiere, $data['field'], (int)$data['value']);
            case 'removeBcc':
                //utile ?
                if ($ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
                    $acs = $ficheMatiere->getApprentissagesCritiques();
                    foreach ($acs as $ac) {
                        if ($ac->getNiveau()?->getCompetence()?->getId() === $data['value']) {
                            $ficheMatiere->removeApprentissagesCritique($ac);
                        }
                    }
                } else {
                    $competences = $ficheMatiere->getCompetences();
                    foreach ($competences as $competence) {
                        if ($competence->getBlocCompetence()?->getId() === $data['value']) {
                            $competence->removeFicheMatiere($ficheMatiere);
                            $ficheMatiere->removeCompetence($competence);
                        }
                    }
                }
                $entityManager->flush();

                return $this->json(true);
            case 'addCompetence':

                if ($ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
                    $competence = $butApprentissageCritiqueRepository->find($data['value']);
                    if ($competence !== null) {
                        if ($data['checked'] === false) {
                            $ficheMatiere->removeApprentissagesCritique($competence);
                        } else {
                            $ficheMatiere->addApprentissagesCritique($competence);
                        }

                        $entityManager->flush();
                        return $this->json(true);
                    }
                } else {
                    $competence = $competenceRepository->find($data['value']);
                    if ($competence !== null) {
                        $ficheMatiere->addCompetence($competence);
                        $competence->addFicheMatiere($ficheMatiere);
                        $entityManager->flush();

                        return $this->json(true);
                    }
                }

                return $this->json(false);
            case 'array':
                if ($data['isChecked'] === true) {
                    return $updateEntity->addToArray($ficheMatiere, $data['field'], $data['value']);
                }

                return $updateEntity->removeToArray($ficheMatiere, $data['field'], $data['value']);

            case 'etatStep':
                $valideState = (bool)$data['isChecked'] === true ? $ficheMatiereState->valideStep(
                    $data['value']
                ) : true;

                if ($valideState === true) {
                    $etatSteps = $ficheMatiere->getEtatSteps();
                    $step = $data['value'];
                    $etatSteps[$step] = $data['isChecked'];
                    $ficheMatiere->setEtatSteps($etatSteps);

                    $entityManager->flush();

                    return $this->json(true);
                }

                return $this->json($valideState);
        }

        return $this->json(['error' => 'action inconnue']);
    }
}
