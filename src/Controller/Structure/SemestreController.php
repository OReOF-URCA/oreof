<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/SemestreController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\SemestreOrdre;
use App\Controller\BaseController;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreMutualisable;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\ComposanteRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\SemestreMutualisableRepository;
use App\Repository\SemestreParcoursRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route('/structure/semestre', name: 'structure_semestre_')
]
class SemestreController extends BaseController
{
    #[Route('/detail/parcours/{parcours}', name: 'detail_parcours')]
    public function detailParcours(
        Request                    $request,
        SemestreParcoursRepository $semestreRepository,
        Parcours                   $parcours
    ): Response {
        $formation = $parcours->getFormation();

        if (null === $formation) {
            throw $this->createNotFoundException('La formation n\'existe pas');
        }

        $dpeParcours = GetDpeParcours::getFromParcours($parcours);

        if (null === $dpeParcours) {
            throw $this->createNotFoundException('Le DPE du parcours n\'existe pas');
        }


        $typeDiplome = $formation->getTypeDiplome();

        if (null === $typeDiplome) {
            throw $this->createNotFoundException('Le type de diplôme n\'existe pas');
        }

        $sems = $semestreRepository->findByParcours($parcours);

        $semestres = [];
        foreach ($sems as $sem) {
            $semestres[$sem->getOrdre()] = $sem;
        }

        $debut = $parcours->getFormation()?->getSemestreDebut();
        return $this->render('structure/semestre/_liste.html.twig', [
            'semestres' => $semestres,
            'parcours' => $parcours,
            'dpeParcours' => $dpeParcours,
            'debut' => $debut,
            'fin' => $typeDiplome->getSemestreFin(),
            'semestreAffiche' => $request->getSession()->get('semestreAffiche'),
            'ueAffichee' => $request->getSession()->get('ueAffichee'),
        ]);
    }

    #[
        Route('/dupliquer/{semestre}/{parcours}', name: 'dupliquer')
    ]
    public function dupliquer(
        Semestre $semestre,
        Parcours $parcours
    ): Response {
        return $this->render('structure/semestre/_dupliquer.html.twig', [
            'semestre' => $semestre,
            'parcours' => $parcours,
            'formation' => $parcours->getFormation()
        ]);
    }

    #[
        Route('/actions/ouverture/{semestre}', name: 'action_ouverture')
    ]
    public function ouvertureSemestre(
        EntityManagerInterface     $entityManager,
        Request                    $request,
        SemestreParcours           $semestre
    ): Response {
        $valeur = JsonRequest::getValueFromRequest($request, 'check');

        $semestre->setOuvert((bool) $valeur);
        $parcours = $semestre->getParcours();

        if (null === $parcours) {
            return JsonReponse::error('Le parcours n\'existe pas');
        }

        $dpeParcours = GetDpeParcours::getFromParcours($parcours);

        if (null === $dpeParcours) {
            return JsonReponse::error('Le DPE du parcours n\'existe pas');
        }

        //parcours les semestres et construire la phrase si le parcours est ouvert, sinon laisser la phrase par défaut
        $phrase = '';
        $tSemestre = [];

        if ($dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE) {
            $phrase = 'Ce parcours ne sera pas proposé pour la campagne ' . $dpeParcours->getCampagneCollecte()?->getLibelle() . '.';
        } else {
            $semestres = $parcours->getSemestreParcours();
            foreach ($semestres as $sem) {
                if ($sem->isOuvert() === false) {
                    $tSemestre[] = $sem->getOrdre();
                }
            }

            $phrase = 'Ce parcours sera proposé pour la campagne ' . $dpeParcours->getCampagneCollecte()?->getLibelle() . '.';
            if (count($tSemestre) > 0) {
                if (count($tSemestre) === 1) {
                    $phrase .= ' Le semestre ' . $tSemestre[0] . ' n\'est pas ouvert.';
                } elseif (count($tSemestre) > 1) {
                    $phrase .= ' Les semestres ' . implode(', ', $tSemestre) . ' ne seront pas ouverts.';
                }
            }
        }

        $parcours->setDescriptifHautPageAutomatique($phrase);

        $entityManager->flush();
        return JsonReponse::success('Semestre modifié');

    }

    #[Route('/actions/{parcours}', name: 'actions')]
    public function init(
        EntityManagerInterface     $entityManager,
        SemestreParcoursRepository $semestreParcoursRepository,
        Request                    $request,
        Parcours                   $parcours
    ): Response {
        $ordre = JsonRequest::getValueFromRequest($request, 'position');
        $action = JsonRequest::getValueFromRequest($request, 'action');
        $semestres = $semestreParcoursRepository->findBy([
            'parcours' => $parcours,
            'ordre' => $ordre
        ]);

        if (count($semestres) > 0) {
            foreach ($semestres as $semestre) {
                $semestre->getSemestre()?->setNonDispense(false);
            }
            $entityManager->flush();
            return JsonReponse::success('Semestre mis à jour');
        }

        switch ($action) {
            case 'init':
                $semestre = new Semestre();
                $semestre->setOrdre($ordre);
                $entityManager->persist($semestre);

                $semestreParcours = new SemestreParcours($semestre, $parcours);
                $semestreParcours->setOrdre($ordre);
                $entityManager->persist($semestreParcours);

                $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
                if (null !== $typeDiplome && $typeDiplome->getNbUeMin() > 0) {
                    for ($i = 1; $i <= $typeDiplome->getNbUeMin(); $i++) {
                        $ue = new Ue();
                        $ue->setSemestre($semestre);
                        $ue->setOrdre($i);
                        $entityManager->persist($ue);
                    }
                }

                $entityManager->flush();
                return JsonReponse::success('Semestre ajouté');
            case 'nonDispense':
                $semestre = new Semestre();
                $semestre->setOrdre($ordre);
                $semestre->setNonDispense(true);
                $entityManager->persist($semestre);

                $semestreParcours = new SemestreParcours($semestre, $parcours);
                $semestreParcours->setOrdre($ordre);
                $entityManager->persist($semestreParcours);
                $entityManager->flush();
                return JsonReponse::success('Semestre marqué comme non dispensé');
        }
    }

    #[Route('/changer/{semestre}/{parcours}', name: 'changer')]
    public function changer(
        Semestre $semestre,
        Parcours $parcours
    ): Response {
        return $this->render('structure/semestre/_changer.html.twig', [
            'semestre' => $semestre,
            'parcours' => $parcours,
            'formation' => $parcours->getFormation()
        ]);
    }

    #[Route('/changer-ajax/{semestre}/{parcours}', name: 'changer_ajax')]
    public function changerAjax(
        EntityManagerInterface     $entityManager,
        SemestreParcoursRepository $semestreParcoursRepository,
        ParcoursRepository         $parcoursRepository,
        Request                    $request,
        Semestre                   $semestre,
        Parcours                   $parcours
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        //todo: on ne peut pas déplacer un semestre tronc commun ? Sauf vers une autre formation?

        //Parcours de destination
        $parcoursDestination = $parcoursRepository->find($data['destination']);
        $ordre = (int)$data['position'];
        if (null !== $parcoursDestination) {
            //on récupère, s'il existe, le semestre à la position de destination
            $semestreDest = $semestreParcoursRepository->findOneBy([
                'parcours' => $parcoursDestination,
                'ordre' => $ordre
            ]);

            //on supprime le semestre de la position de destination
            if (null !== $semestreDest) {
                $sem = $semestreDest->getSemestre();
                if ($sem !== null) {
                    if ($sem->getSemestreParcours()->count() === 1) {
                        //donc uniquement mon parcours, on peut supprimer le semestre et sa structure
                        $entityManager->remove($sem);
                        $entityManager->flush();
                    }
                }
                $semestreParcoursRepository->remove($semestreDest, true);
            }


            foreach ($semestre->getSemestreParcours() as $semestreParcour) {
                if ($semestreParcour->getParcours() === $parcours) {
                    $semestreParcour->setParcours($parcoursDestination);
                    $semestreParcour->setOrdre($ordre);
                    $semestreParcoursRepository->save($semestreParcour, true);

                    // mise à jour du parcours en cascade
                    foreach ($semestre->getUes() as $ue) {
                        foreach ($ue->getElementConstitutifs() as $ec) {
                            if ($ec->getParcours() === $parcours) {
                                $ec->setParcours($parcoursDestination);
                                if ($ec->getFicheMatiere() !== null && $ec->getFicheMatiere()->getParcours() === $parcours) {
                                    $ec->getFicheMatiere()->setParcours($parcoursDestination);
                                }
                            }
                            foreach ($ec->getEcEnfants() as $ecEnfant) {
                                if ($ecEnfant->getParcours() === $parcours) {
                                    $ecEnfant->setParcours($parcoursDestination);
                                    if ($ecEnfant->getFicheMatiere() !== null && $ecEnfant->getFicheMatiere()->getParcours() === $parcours) {
                                        $ecEnfant->getFicheMatiere()->setParcours($parcoursDestination);
                                    }
                                }
                            }
                        }

                        //déplacer chaque enfant
                        foreach ($ue->getUeEnfants() as $ueEnfant) {
                            foreach ($ueEnfant->getElementConstitutifs() as $ec) {
                                if ($ec->getParcours() === $parcours) {
                                    $ec->setParcours($parcoursDestination);
                                    if ($ec->getFicheMatiere() !== null && $ec->getFicheMatiere()->getParcours() === $parcours) {
                                        $ec->getFicheMatiere()->setParcours($parcoursDestination);
                                    }
                                }
                                foreach ($ec->getEcEnfants() as $ecEnfant) {
                                    if ($ecEnfant->getParcours() === $parcours) {
                                        $ecEnfant->setParcours($parcoursDestination);
                                        if ($ecEnfant->getFicheMatiere() !== null && $ecEnfant->getFicheMatiere()->getParcours() === $parcours) {
                                            $ecEnfant->getFicheMatiere()->setParcours($parcoursDestination);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $entityManager->flush();
                }
            }

            return $this->json((new JsonReponse(Response::HTTP_OK, 'Semestre déplacé', []))->getReponse());
        }

        return $this->json((new JsonReponse(Response::HTTP_INTERNAL_SERVER_ERROR, 'Erreur lors du déplacement du semestre', []))->getReponse());
    }

    #[Route('/dupliquer-ajax/{semestre}/{parcours}', name: 'dupliquer_ajax')]
    public function dupliquerAjax(
        Request                            $request,
        EntityManagerInterface             $entityManager,
        FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        SemestreParcoursRepository         $semestreParcoursRepository,
        ParcoursRepository                 $parcoursRepository,
        Semestre                           $semestre,
        Parcours                           $parcours
    ): Response {
        $data = JsonRequest::getFromRequest($request);

        $parcoursDestination = $parcoursRepository->find($data['destination']);
        $ordre = (int)$data['position'];
        if (null !== $parcoursDestination) {
            //on récupère, s'il existe, le semestre à la position de destination
            $semestreDest = $semestreParcoursRepository->findOneBy([
                'parcours' => $parcoursDestination,
                'ordre' => $ordre
            ]);

            //on supprime le semestre de la position de destination
            if (null !== $semestreDest) {
                $sem = $semestreDest->getSemestre();
                if ($sem !== null) {
                    if ($sem->getSemestreParcours()->count() === 1) {
                        foreach ($sem->getUes() as $ue) {
                            foreach ($ue->getElementConstitutifs() as $ec) {
                                $entityManager->remove($ec);
                            }
                            $entityManager->remove($ue);
                        }
                        //donc uniquement mon parcours, on peut supprimer le semestre et sa structure
                        $entityManager->remove($sem);
                        $entityManager->flush();
                    }
                }
                $semestreParcoursRepository->remove($semestreDest, true);
            }


            //on clone le semestre et sa structure
            $newSemestre = clone $semestre;
            $newSemestre->setSemestreOrigineCopie(null);
            $newSemestre->setTroncCommun(false);
            $entityManager->persist($newSemestre);
            // on recopie les UE
            foreach ($semestre->getUes() as $ue) {
                if ($ue->getUeParent() === null) {
                    $newUe = clone $ue;
                    $newUe->setUeOrigineCopie(null);
                    $newUe->setSemestre($newSemestre);
                    $newUe->setUeParent(null);
                    $entityManager->persist($newUe);
                    //on recopie les EC
                    foreach ($ue->getElementConstitutifs() as $ec) {
                        switch ($data['dupliquer']) {
                            case 'mutualise':
                                if ($ec->getEcParent() === null) {
                                    //vérifier si pas déjà mutualisé
                                    $ficheMatiereMutualisable = $ficheMatiereMutualisableRepository->findBy([
                                        'ficheMatiere' => $ec->getFicheMatiere(),
                                        'parcours' => $parcoursDestination
                                    ]);

                                    if (count($ficheMatiereMutualisable) === 0) {
                                        //si pas mutualisé, mutualiser
                                        //le parcours d'origine
                                        $ficheMatiereMutualisable = new FicheMatiereMutualisable();
                                        $ficheMatiereMutualisable->setFicheMatiere($ec->getFicheMatiere());
                                        $ficheMatiereMutualisable->setParcours($parcoursDestination);
                                        $entityManager->persist($ficheMatiereMutualisable);
                                    }

                                    //faire le lien dans les deux UE
                                    $newEc = clone $ec;
                                    $newEc->setEcOrigineCopie(null);
                                    $newEc->setUe($newUe);
                                    $newEc->getMcccs()->clear();
                                    $newEc->setEtatMccc('A Saisir');
                                    $newEc->getCompetences()->clear();
                                    $newEc->setFicheMatiere($ec->getFicheMatiere());
                                    $entityManager->persist($newEc);

                                    //traitement des enfants

                                    foreach ($ec->getEcEnfants() as $ecEnfant) {
                                        $ficheMatiereMutualisable = $ficheMatiereMutualisableRepository->findBy([
                                            'ficheMatiere' => $ecEnfant->getFicheMatiere(),
                                            'parcours' => $parcoursDestination
                                        ]);

                                        if (count($ficheMatiereMutualisable) === 0) {
                                            $ficheMatiereMutualisable = new FicheMatiereMutualisable();
                                            $ficheMatiereMutualisable->setFicheMatiere($ecEnfant->getFicheMatiere());
                                            $ficheMatiereMutualisable->setParcours($parcoursDestination);
                                            $entityManager->persist($ficheMatiereMutualisable);
                                        }

                                        //faire le lien dans les deux UE
                                        $newEcEnfant = clone $ecEnfant;
                                        $newEcEnfant->setEcOrigineCopie(null);
                                        $newEcEnfant->setUe($newUe);
                                        $newEcEnfant->getCompetences()->clear();
                                        $newEcEnfant->getMcccs()->clear();
                                        $newEcEnfant->setEtatMccc('A Saisir');
                                        $newEcEnfant->setEcParent($newEc);
                                        $newEcEnfant->setFicheMatiere($ecEnfant->getFicheMatiere());
                                        $entityManager->persist($newEcEnfant);
                                    }
                                }
                                $entityManager->flush();
                                break;
                            case 'recopie':
                                if ($ec->getEcParent() === null) {
                                    $newEc = clone $ec;
                                    $newEc->setEcOrigineCopie(null);
                                    $newEc->setUe($newUe);
                                    $entityManager->persist($newEc);
                                    if ($ec->getFicheMatiere() !== null) {
                                        $newFm = clone $ec->getFicheMatiere();
                                        $newFm->setFicheMatiereOrigineCopie(null);
                                        $newFm->setParcours($parcoursDestination);
                                        $newFm->setSlug(null);
                                        $newEc->setFicheMatiere($newFm);
                                        $entityManager->persist($newFm);
                                    }
                                    foreach ($ec->getEcEnfants() as $ecEnfant) {
                                        $newEcEnfant = clone $ecEnfant;
                                        $newEcEnfant->setEcOrigineCopie(null);
                                        $newEcEnfant->setUe($newUe);
                                        $newEcEnfant->setEcParent($newEc);
                                        $entityManager->persist($newEcEnfant);
                                        if ($ec->getFicheMatiere() !== null) {
                                            $newFm = clone $ec->getFicheMatiere();
                                            $newFm->setFicheMatiereOrigineCopie(null);
                                            $newFm->setParcours($parcoursDestination);
                                            $newFm->setSlug(null);
                                            $newEcEnfant->setFicheMatiere($newFm);
                                            $entityManager->persist($newFm);
                                        }
                                    }
                                }
                                $entityManager->flush();
                                break;
                        }
                    }

                    // on recopie les UE enfants
                    foreach ($ue->getUeEnfants() as $ueEnfant) {
                        $newUeEnfant = clone $ueEnfant;
                        $newUeEnfant->setUeOrigineCopie(null);
                        $newUeEnfant->setSemestre($newSemestre);
                        $newUeEnfant->setUeParent($newUe);
                        $entityManager->persist($newUeEnfant);
                        //on recopie les EC
                        foreach ($ueEnfant->getElementConstitutifs() as $ec) {
                            switch ($data['dupliquer']) {
                                case 'mutualise':
                                    //vérifier si pas déjà mutualisé
                                    $ficheMatiereMutualisable = $ficheMatiereMutualisableRepository->findBy([
                                        'ficheMatiere' => $ec->getFicheMatiere(),
                                        'parcours' => $parcoursDestination
                                    ]);

                                    if (count($ficheMatiereMutualisable) === 0) {
                                        //si pas mutualisé, mutualiser
                                        //le parcours d'origine
                                        $ficheMatiereMutualisable = new FicheMatiereMutualisable();
                                        $ficheMatiereMutualisable->setFicheMatiere($ec->getFicheMatiere());
                                        $ficheMatiereMutualisable->setParcours($parcoursDestination);
                                        $entityManager->persist($ficheMatiereMutualisable);
                                    }

                                    //faire le lien dans les deux UE
                                    $newEc = clone $ec;
                                    $newEc->setEcOrigineCopie(null);
                                    $newEc->setUe($newUeEnfant);
                                    $entityManager->persist($newEc);
                                    $entityManager->flush();
                                    break;
                                case 'recopie':
                                    if ($ec->getEcParent() === null) {
                                        $newEc = clone $ec;
                                        $newEc->setEcOrigineCopie(null);
                                        $newEc->setUe($newUeEnfant);
                                        $entityManager->persist($newEc);
                                        if ($ec->getFicheMatiere() !== null) {
                                            $newFm = clone $ec->getFicheMatiere();
                                            $newFm->setFicheMatiereOrigineCopie(null);
                                            $newFm->setParcours($parcoursDestination);
                                            $newEc->setFicheMatiere($newFm);
                                            $entityManager->persist($newFm);
                                        }
                                        foreach ($ec->getEcEnfants() as $ecEnfant) {
                                            $newEcEnfant = clone $ecEnfant;
                                            $newEcEnfant->setEcOrigineCopie(null);
                                            $newEcEnfant->setUe($newUeEnfant);
                                            $newEcEnfant->setEcParent($newEc);
                                            $entityManager->persist($newEcEnfant);
                                            if ($ec->getFicheMatiere() !== null) {
                                                $newFm = clone $ec->getFicheMatiere();
                                                $newFm->setFicheMatiereOrigineCopie(null);
                                                $newFm->setParcours($parcoursDestination);
                                                $newEcEnfant->setFicheMatiere($newFm);
                                                $entityManager->persist($newFm);
                                            }
                                        }
                                    }
                                    $entityManager->flush();
                                    break;
                            }
                        }
                    }
                }
            }


            //on ajoute au parcours
            $semestreParcours = new SemestreParcours($newSemestre, $parcoursDestination);
            $semestreParcours->setOrdre($ordre);
            $entityManager->persist($semestreParcours);
            $entityManager->flush();

            return $this->json((new JsonReponse(Response::HTTP_OK, 'Le semestre a été dupliqué', []))->getReponse());
        }

        return $this->json((new JsonReponse(Response::HTTP_INTERNAL_SERVER_ERROR, 'Erreur lors de la duplication du semestre', []))->getReponse());
    }

    #[Route('/mutualiser/{semestre}/{parcours}', name: 'mutualiser')]
    public function mutualiser(
        ComposanteRepository $composanteRepository,
        Semestre             $semestre,
        Parcours             $parcours
    ): Response {
        return $this->render('structure/semestre/_mutualiser.html.twig', [
            'semestre' => $semestre,
            'parcours' => $parcours,
            'composantes' => $composanteRepository->findAll()
        ]);
    }

    #[Route('/{semestre}/mutualise/ajax', name: 'mutualise_add_ajax', methods: [
        'POST',
        'DELETE'
    ])]
    public function mutualiseAjax(
        EntityManagerInterface         $entityManager,
        Request                        $request,
        FormationRepository            $formationRepository,
        ParcoursRepository             $parcoursRepository,
        SemestreMutualisableRepository $semestreMutualisableRepository,
        Semestre                       $semestre,
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        $t = [];
        switch ($data['field']) {
            case 'decrocher':
                $semestre->setSemestreRaccroche(null);
                $entityManager->flush();
                return $this->json(true);
            case 'raccrocher':
                $uem = $semestreMutualisableRepository->find($data['value']);
                if ($uem !== null) {
                    $semestre->setSemestreRaccroche($uem);
                    $entityManager->flush();
                    return $this->json(true);
                }

                return $this->json(
                    ['error' => 'Semestre non trouvé'],
                    500
                );
            case 'liste':
                return $this->render('structure/semestre/_liste_mutualise.html.twig', [
                    'semestres' => $semestreMutualisableRepository->findBy(['semestre' => $semestre]),
                    'semestre' => $semestre
                ]);
            case 'formation':
                $formations = $formationRepository->findByComposantePorteuse($data['value'], $this->getCampagneCollecte());
                foreach ($formations as $formation) {
                    $t[] = [
                        'id' => $formation->getId(),
                        'libelle' => $formation->getDisplayLong()
                    ];
                }
                break;
            case 'parcours':
                $parcours = $parcoursRepository->findBy(['formation' => $data['value']], ['libelle' => 'ASC']);
                foreach ($parcours as $parcour) {
                    $t[] = [
                        'id' => $parcour->getId(),
                        'libelle' => $parcour->getDisplay()
                    ];
                }
                break;
            case 'save':
                if (!isset($data['parcours']) || $data['parcours'] === '') {
                    $formation = $formationRepository->find($data['formation']);
                    if ($formation !== null && $formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
                        $parcours = $formation->getParcours()[0];
                        $this->updateSemestreMutualisable($semestreMutualisableRepository, $semestre, $parcours, $entityManager);
                    }
                } else {
                    if ($data['parcours'] !== 'all') {
                        $parcours = $parcoursRepository->find($data['parcours']);
                        $this->updateSemestreMutualisable($semestreMutualisableRepository, $semestre, $parcours, $entityManager);
                    } else {
                        $formation = $formationRepository->find($data['formation']);
                        if ($formation !== null) {
                            foreach ($formation->getParcours() as $parcours) {
                                $this->updateSemestreMutualisable($semestreMutualisableRepository, $semestre, $parcours, $entityManager);
                            }
                        }
                    }
                }

                return $this->json(true);
            case 'delete':
                $sem = $semestreMutualisableRepository->find($data['sem']);
                //todo: vérifier si pas utilisé

                if ($sem !== null) {
                    $entityManager->remove($sem);
                    $entityManager->flush();
                }

                return $this->json(true);
        }

        return $this->json($t);
    }

    #[Route('/raccrocher/{semestre}/{parcours}', name: 'raccrocher')]
    public function raccrocher(
        SemestreMutualisableRepository $semestreMutualisableRepository,
        Semestre                       $semestre,
        Parcours                       $parcours
    ): Response {
        $semestres = $semestreMutualisableRepository->findBy(['parcours' => $parcours]);

        return $this->render('structure/semestre/_raccrocher.html.twig', [
            'semestre' => $semestre,
            'semestres' => $semestres,
            'parcours' => $parcours
        ]);
    }

    #[Route('/tronc-commun/{semestreParcours}/{parcours}', name: 'definir_tronc_commun')]
    public function definirTroncCommun(
        EntityManagerInterface     $entityManager,
        SemestreParcoursRepository $semestreParcoursRepository,
        SemestreParcours                   $semestreParcours,
        Parcours                   $parcours
    ): Response {
        // tous les parcours
        $allParcours = $parcours->getFormation()?->getParcours();

        $semestreTc = $semestreParcours->getSemestre();

        if($semestreTc === null) {
            return JsonReponse::error('Le semestre n\'existe pas');
        }

        if ($semestreTc->getSemestreRaccroche() !== null) {
            return JsonReponse::error('Le semestre est mutualisé');
        }

        $semestreTc->setTroncCommun(true);
        foreach ($allParcours as $parcour) {
            $sem = $semestreParcoursRepository->findOneBy([
                'ordre' => $semestreParcours->getOrdre(),
                'parcours' => $parcour
            ]);

            if ($sem !== null && $sem->getSemestre() !== null) {
                if ($sem->getSemestre()->isNonDispense() === false) {
                    $sem->setSemestre($semestreTc);
                    //todo: supprimer toutes les données de l'autre semestre ?
                }
            }
        }

        $entityManager->flush();


        return JsonReponse::success('Le semestre a été réinitialisé');
    }

    #[Route('/reinitialiser/{semestre}/{parcours}', name: 'reinitialiser')]
    public function reinitialiser(
        EntityManagerInterface     $entityManager,
        SemestreParcoursRepository $semestreParcoursRepository,
        Semestre                   $semestre,
        Parcours                   $parcours
    ): Response {
        $sem = $semestreParcoursRepository->findOneBy([
            'semestre' => $semestre,
            'parcours' => $parcours
        ]);

        if ($sem === null) {
            return JsonReponse::error('Le semestre n\'existe pas');
        }

        if ($semestre->getSemestreMutualisables()->count() > 0) {
            return JsonReponse::error('Le semestre est mutualisé');
        }

        foreach ($semestre->getUes() as $ue) {
            foreach ($ue->getElementConstitutifs() as $ec) {
                foreach ($ec->getEcEnfants() as $ecEnfant) {
                    $entityManager->remove($ecEnfant);
                }
                $entityManager->remove($ec);
                foreach ($ue->getUeEnfants() as $uee) {
                    foreach ($uee->getElementConstitutifs() as $ecuee) {
                        foreach ($ecuee->getEcEnfants() as $ecEnfant) {
                            $entityManager->remove($ecEnfant);
                        }
                        $entityManager->remove($ecuee);
                    }
                    $entityManager->remove($uee);
                }
            }
            $entityManager->remove($ue);
        }
        $entityManager->remove($semestre);
        $sem->setSemestre(null);
        $entityManager->flush();

        return JsonReponse::success('Le semestre a été réinitialisé');
    }


    #[Route('/deplacer/{semestre}/{parcours}/{sens}', name: 'deplacer', methods: ['GET'])]
    public function deplacer(
        SemestreOrdre    $semestreOrdre,
        SemestreParcours $semestre,
        Parcours         $parcours,
        string           $sens
    ): Response {
        $semestreOrdre->deplacerSemestre($semestre, $parcours, $sens);

        return $this->json(true);
    }

    private function updateSemestreMutualisable(SemestreMutualisableRepository $semestreMutualisableRepository, Semestre $semestre, mixed $parcours, EntityManagerInterface $entityManager): void
    {
        $exist = $semestreMutualisableRepository->findOneBy([
            'semestre' => $semestre,
            'parcours' => $parcours
        ]);

        if ($exist === null) {
            $semestreMutualise = new SemestreMutualisable();
            $semestreMutualise->setSemestre($semestre);
            $semestreMutualise->setParcours($parcours);
            $entityManager->persist($semestreMutualise);
            $entityManager->flush();
        }
    }
}
