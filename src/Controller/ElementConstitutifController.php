<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\EcOrdre;
use App\Classes\GetElementConstitutif;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\TypeEc;
use App\Entity\Ue;
use App\Events\McccUpdateEvent;
use App\Form\EcStep4Type;
use App\Form\ElementConstitutifEnfantType;
use App\Form\ElementConstitutifType;
use App\Form\FicheMatiereStep4Type;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/element/constitutif')]
class ElementConstitutifController extends BaseController
{
    #[Route('/', name: 'app_element_constitutif_index', methods: ['GET'])]
    public function index(FicheMatiereRepository $ficheMatiereRepository): Response
    {
        return $this->render('element_constitutif/index.html.twig', [
            'element_constitutifs' => $ficheMatiereRepository->findAll(),
        ]);
    }

    #[Route('/type-ec/{ue}/{parcours}', name: 'app_element_constitutif_type_ec', methods: ['GET'])]
    public function typeEc(
        ElementConstitutifRepository $elementConstitutifRepository,
        Request                      $request,
        FicheMatiereRepository       $ficheMatiereRepository,
        NatureUeEcRepository         $natureUeEcRepository,
        Ue                           $ue,
        Parcours                     $parcours
    ): Response {
        if ($request->query->has('ec')) {
            $ec = $elementConstitutifRepository->find($request->query->get('ec'));
        }

        if ($request->query->has('delete')) {
            $ec = $elementConstitutifRepository->find($request->query->get('delete'));
            $elementConstitutifRepository->remove($ec, true);
            return $this->json(true);
        }

        $natureEc = $natureUeEcRepository->find($request->query->get('choix'));
        if ($natureEc !== null) {
            $matieres[] = $ficheMatiereRepository->findByParcours($parcours, $this->getCampagneCollecte());
            $matieres[] = $ficheMatiereRepository->findAllByHd($this->getCampagneCollecte());
            $matieres = array_merge(...$matieres);

            if ($natureEc->isChoix() === true) {
                return $this->render('element_constitutif/_type_ec_matieres.html.twig', [
                    'ec' => $ec ?? null,
                    'matieres' => $matieres
                ]);
            }

            if ($natureEc->isLibre() === true) {
                return $this->render('element_constitutif/_type_ec_matieres_libre.html.twig', [
                    'ec' => $ec ?? null,
                ]);
            }

            return $this->render('element_constitutif/_type_ec_matiere.html.twig', [
                'ec' => $ec ?? null,
                'matieres' => $matieres
            ]);
        }

        throw new RuntimeException('Nature EC non trouvée');
    }

    #[Route('/new/{ue}/{parcours}', name: 'app_element_constitutif_new', methods: ['GET', 'POST'])]
    public function new(
        NatureUeEcRepository         $natureUeEcRepository,
        EcOrdre                      $ecOrdre,
        Request                      $request,
        TypeEcRepository             $typeEcRepository,
        FicheMatiereRepository       $ficheMatiereRepository,
        ElementConstitutifRepository $elementConstitutifRepository,
        Ue                           $ue,
        Parcours                     $parcours
    ): Response {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $elementConstitutif = new ElementConstitutif();
        $elementConstitutif->setFicheMatiere(null);
        $elementConstitutif->setParcours($parcours);

        $elementConstitutif->setModaliteEnseignement($parcours?->getModalitesEnseignement());
        $elementConstitutif->setUe($ue);
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplôme non trouvé');
        }

        $form = $this->createForm(ElementConstitutifType::class, $elementConstitutif, [
            'action' => $this->generateUrl(
                'app_element_constitutif_new',
                ['ue' => $ue->getId(), 'parcours' => $parcours->getId(), 'element' => $request->query->get('element')]
            ),
            'typeDiplome' => $typeDiplome,
            'formation' => $parcours->getFormation(),
            'isAdmin' => $isAdmin
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('typeEcTexte')->getData() !== null && $form->get('typeEc')->getData() === null) {
                $tu = new TypeEc();
                $tu->setLibelle($form->get('typeEcTexte')->getData());
                $tu->addTypeDiplome($typeDiplome);
                $tu->setFormation($parcours->getFormation());
                $typeEcRepository->save($tu, true);
                $elementConstitutif->setTypeEc($tu);
            }

            if ($elementConstitutif->getNatureUeEc()?->isChoix() === false and $elementConstitutif->getNatureUeEc()?->isLibre() === false) {
                if (str_starts_with($request->request->get('ficheMatiere'), 'id_')) {
                    $ficheMatiere = $ficheMatiereRepository->find((int)str_replace(
                        'id_',
                        '',
                        $request->request->get('ficheMatiere')
                    ));
                } else {
                    $ficheMatiere = new FicheMatiere();
                    $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());
                    $ficheMatiere->setLibelle($request->request->get('ficheMatiereLibelle'));
                    $ficheMatiere->setParcours($parcours);
                    $ficheMatiereRepository->save($ficheMatiere, true);
                }
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setFicheMatiere($ficheMatiere);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            } elseif ($elementConstitutif->getNatureUeEc()?->isChoix() === true) {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setLibelle($request->request->get('ficheMatiereLibre'));
                $elementConstitutif->setFicheMatiere(null);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
                //on récupère le champs matières, on découpe selon la ,. Si ca commence par "id_", on récupère la matière, sinon on créé la matière
                $matieres = explode(',', $request->request->get('matieres'));
                $natureEc = $natureUeEcRepository->findOneBy(['choix' => false, 'libre' => false, 'type' => 'ec']);
                foreach ($matieres as $matiere) {
                    $ec = new ElementConstitutif();
                    $nextSousEc = $ecOrdre->getOrdreEnfantSuivant($elementConstitutif);
                    $ec->setEcParent($elementConstitutif);
                    $ec->setParcours($parcours);
                    $ec->setModaliteEnseignement($parcours?->getModalitesEnseignement());
                    $ec->setUe($ue);
                    $ec->setNatureUeEc($natureEc);

                    if (str_starts_with($matiere, 'id_')) {
                        $ficheMatiere = $ficheMatiereRepository->find((int)str_replace('id_', '', $matiere));
                    } else {
                        $ficheMatiere = new FicheMatiere();
                        $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());
                        $ficheMatiere->setLibelle(str_replace('ac_', '', $matiere));
                        $ficheMatiere->setParcours($parcours); //todo: ajouter le semestre
                        $ficheMatiereRepository->save($ficheMatiere, true);
                    }

                    $ec->setFicheMatiere($ficheMatiere);
                    $ec->setOrdre($nextSousEc);
                    $ec->genereCode();
                    $elementConstitutifRepository->save($ec, true);
                }
            } else {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setTexteEcLibre($request->request->get('ficheMatiereLibre'));
                $elementConstitutif->setLibelle($request->request->get('ficheMatiereLibreLibelle'));
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            }

            return $this->json(true);
        }

        return $this->render('element_constitutif/new.html.twig', [
            'elementConstitutif' => $elementConstitutif,
            'form' => $form->createView(),
            'ue' => $ue,
            'parcours' => $parcours,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/new-enfant/{ue}/{parcours}', name: 'app_element_constitutif_new_enfant', methods: ['GET', 'POST'])]
    public function newEnfant(
        NatureUeEcRepository         $natureUeEcRepository,
        TypeEcRepository             $typeEcRepository,
        EcOrdre                      $ecOrdre,
        Request                      $request,
        FicheMatiereRepository       $ficheMatiereRepository,
        ElementConstitutifRepository $elementConstitutifRepository,
        Ue                           $ue,
        Parcours                     $parcours
    ): Response {
        $ecParent = $elementConstitutifRepository->find($request->query->get('element'));

        if ($ecParent === null) {
            throw new RuntimeException('Element constitutif parent non trouvé');
        }

        $elementConstitutif = new ElementConstitutif();
        $elementConstitutif->setParcours($parcours);

        $elementConstitutif->setModaliteEnseignement($parcours?->getModalitesEnseignement());
        $elementConstitutif->setUe($ue);
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplôme non trouvé');
        }

        $form = $this->createForm(ElementConstitutifEnfantType::class, $elementConstitutif, [
            'action' => $this->generateUrl(
                'app_element_constitutif_new_enfant',
                ['ue' => $ue->getId(), 'parcours' => $parcours->getId(), 'element' => $request->query->get('element')]
            ),
            'typeDiplome' => $typeDiplome,
            'formation' => $parcours->getFormation(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //            $typeEc = $typeEcRepository->find($request->request->get('typeEc'));
            //            $elementConstitutif->setTypeEc($typeEc);

            $natureEc = $natureUeEcRepository->findOneBy(['choix' => false, 'libre' => false, 'type' => 'ec']);
            $elementConstitutif->setNatureUeEc($natureEc);

            if ($form->get('typeEcTexte')->getData() !== null && $form->get('typeEc')->getData() === null) {
                $tu = new TypeEc();
                $tu->setLibelle($form->get('typeEcTexte')->getData());
                $tu->addTypeDiplome($typeDiplome);
                $tu->setFormation($parcours->getFormation());
                $typeEcRepository->save($tu, true);
                $elementConstitutif->setTypeEc($tu);
            }

            if (str_starts_with($request->request->get('ficheMatiere'), 'id_')) {
                $ficheMatiere = $ficheMatiereRepository->find((int)str_replace(
                    'id_',
                    '',
                    $request->request->get('ficheMatiere')
                ));
            } else {
                $ficheMatiere = new FicheMatiere();
                $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());

                $ficheMatiere->setLibelle($request->request->get('ficheMatiereLibelle'));
                $ficheMatiere->setParcours($parcours);
                $ficheMatiereRepository->save($ficheMatiere, true);
            }

            $elementConstitutif->setFicheMatiere($ficheMatiere);
            $elementConstitutif->setEcParent($ecParent->getEcParent());
            //si up => $ecParent->getOrdre() + 1; si down => $ecParent->getOrdre()
            if ($request->query->get('sens', 'after') === 'avant') {
                $ordre = $ecParent->getOrdre();
            } else {
                $ordre = $ecParent->getOrdre() + 1;
            }
            $ecOrdre->decalerEnfant($ecParent->getEcParent(), $ordre);
            $elementConstitutif->setOrdre($ordre);
            $elementConstitutif->genereCode();

            $elementConstitutifRepository->save($elementConstitutif, true);

            return $this->json(true);
        }
        $matieres[] = $ficheMatiereRepository->findByParcours($parcours, $this->getCampagneCollecte());
        $matieres[] = $ficheMatiereRepository->findAllByHd($this->getCampagneCollecte());
        $matieres = array_merge(...$matieres);

        return $this->render('element_constitutif/new_enfant.html.twig', [
            'element_constitutif' => $elementConstitutif,
            'form' => $form->createView(),
            'ue' => $ue,
            'parcours' => $parcours,
            'typeEcs' => $typeEcRepository->findByTypeDiplomeAndFormation($typeDiplome, $parcours->getFormation()),
            'matieres' => $matieres
        ]);
    }

    #[Route('/{id}/edit/{parcours}', name: 'app_element_constitutif_edit', methods: ['GET', 'POST'])]
    public function edit(
        NatureUeEcRepository $natureUeEcRepository,
        EcOrdre                      $ecOrdre,
        FicheMatiereRepository       $ficheMatiereRepository,
        EntityManagerInterface       $entityManager,
        Request                      $request,
        ElementConstitutifRepository $elementConstitutifRepository,
        TypeEcRepository             $typeEcRepository,
        ElementConstitutif           $elementConstitutif,
        Parcours                     $parcours
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplôme non trouvé');
        }

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isBut = $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT';
        $form = $this->createForm(ElementConstitutifType::class, $elementConstitutif, [
            'action' => $this->generateUrl(
                'app_element_constitutif_edit',
                ['id' => $elementConstitutif->getId(), 'parcours' => $parcours->getId()]
            ),
            'typeDiplome' => $typeDiplome,
            'formation' => $parcours->getFormation(),
            'isAdmin' => $isAdmin || $isBut
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('typeEcTexte')->getData() !== null && $form->get('typeEc')->getData() === null) {
                $tu = new TypeEc();
                $tu->setLibelle($form->get('typeEcTexte')->getData());
                $tu->addTypeDiplome($typeDiplome);
                $typeEcRepository->save($tu, true);
                $elementConstitutif->setTypeEc($tu);
            }

            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSets();
            $changeSet = $uow->getEntityChangeSet($elementConstitutif);


            if (isset($changeSet['natureUeEc'])) {
                if ($changeSet['natureUeEc'][0]->isChoix() === true) {
                    // c'était une EC à choix, on supprime les choix.
                    foreach ($elementConstitutif->getEcEnfants() as $ecEnfant) {
                        $elementConstitutif->removeEcEnfant($ecEnfant);
                        $entityManager->remove($ecEnfant);
                    }
                    $entityManager->flush();
                }

                if ($changeSet['natureUeEc'][0]->isLibre() === true) {
                    // c'était une EC libre, on efface le commentaire
                    $elementConstitutif->setTexteEcLibre(null);
                }
            }

            if ($elementConstitutif->getNatureUeEc()?->isChoix() === false and $elementConstitutif->getNatureUeEc()?->isLibre() === false) {
                if (str_starts_with($request->request->get('ficheMatiere'), 'id_')) {
                    $ficheMatiere = $ficheMatiereRepository->find((int)str_replace(
                        'id_',
                        '',
                        $request->request->get('ficheMatiere')
                    ));
                } else {
                    $ficheMatiere = new FicheMatiere();
                    $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());
                    $ficheMatiere->setLibelle($request->request->get('ficheMatiereLibelle'));
                    $ficheMatiere->setParcours($parcours); //todo: ajouter le semestre
                    $ficheMatiereRepository->save($ficheMatiere, true);
                }
                $elementConstitutif->setFicheMatiere($ficheMatiere);
            } elseif ($elementConstitutif->getNatureUeEc()?->isLibre() === true) {
                $elementConstitutif->setFicheMatiere(null);
                $elementConstitutif->setTexteEcLibre($request->request->get('ficheMatiereLibre'));
                $elementConstitutif->setLibelle($request->request->get('ficheMatiereLibreLibelle'));
                //todo: supprimer MCCC...

            } elseif ($elementConstitutif->getNatureUeEc()?->isChoix() === true) {
                $natureEc = $natureUeEcRepository->findOneBy(['choix' => false, 'libre' => false, 'type' => 'ec']);
                $elementConstitutif->setLibelle($request->request->get('ficheMatiereLibre'));
                $elementConstitutif->setFicheMatiere(null);
                //on récupère le champs matières, on découpe selon la ,. Si ca commence par "id_", on récupère la matière, sinon on créé la matière
                $matieres = explode(',', $request->request->get('matieres'));
                foreach ($matieres as $matiere) {
                    //on vérifie si pas déjà existant
                    if (str_starts_with($matiere, 'id_')) {
                        $ficheMatiere = $ficheMatiereRepository->find((int)str_replace('id_', '', $matiere));
                    } else {
                        $ficheMatiere = new FicheMatiere();
                        $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());
                        $ficheMatiere->setLibelle(trim(str_replace('ac_', '', $matiere)));
                        $ficheMatiere->setParcours($parcours); //todo: ajouter le semestre
                        $ficheMatiereRepository->save($ficheMatiere, true);
                    }

                    $existe = $elementConstitutifRepository->findOneBy([
                        'ficheMatiere' => $ficheMatiere,
                        'ecParent' => $elementConstitutif
                    ]);

                    if (!$existe && $ficheMatiere->getLibelle() !== '' && $ficheMatiere->getLibelle() !== null) {
                        //sinon on ajoute
                        $ec = new ElementConstitutif();
                        $nextSousEc = $ecOrdre->getOrdreEnfantSuivant($elementConstitutif);
                        $ec->setEcParent($elementConstitutif);
                        $ec->setParcours($parcours);
                        $ec->setModaliteEnseignement($parcours?->getModalitesEnseignement());
                        $ec->setUe($elementConstitutif->getUe());
                        $ec->setNatureUeEc($natureEc);
                        $ec->setFicheMatiere($ficheMatiere);
                        $ec->setOrdre($nextSousEc);
                        $ec->genereCode();
                        $elementConstitutifRepository->save($ec, true);
                    }
                }
            }

            $elementConstitutif->genereCode();
            $entityManager->flush();

            return $this->json(true);
        }

        return $this->render('element_constitutif/edit.html.twig', [
            'elementConstitutif' => $elementConstitutif,
            'form' => $form->createView(),
            'ue' => $elementConstitutif->getUe(),
            'parcours' => $parcours,
            'isAdmin' => $isAdmin,
            'isBut' => $isBut
        ]);
    }

    #[
        Route('/{id}/edit-enfant/{parcours}/{ue}', name: 'app_element_constitutif_edit_enfant', methods: [
            'GET',
            'POST'
        ])]
    public function editEnfant(
        TypeEcRepository             $typeEcRepository,
        NatureUeEcRepository         $natureUeEcRepository,
        FicheMatiereRepository       $ficheMatiereRepository,
        Request                      $request,
        ElementConstitutifRepository $elementConstitutifRepository,
        ElementConstitutif           $elementConstitutif,
        Parcours                     $parcours,
        Ue                           $ue
    ): Response {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($request->isMethod('POST')) {
            if ($request->request->has('ficheMatiereLibre') && $request->request->get('ficheMatiereLibre') !== '') {
                $natureEc = $natureUeEcRepository->find(9);
                $elementConstitutif->setNatureUeEc($natureEc);
                $elementConstitutif->setTexteEcLibre($request->request->get('ficheMatiereLibre'));
                $elementConstitutif->setLibelle($request->request->get('ficheMatiereLibreLibelle'));
                $elementConstitutif->setFicheMatiere(null);
            } else {
                if (str_starts_with($request->request->get('ficheMatiere'), 'id_')) {
                    $ficheMatiere = $ficheMatiereRepository->find((int)str_replace(
                        'id_',
                        '',
                        $request->request->get('ficheMatiere')
                    ));
                } else {
                    $ficheMatiere = new FicheMatiere();
                    $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());
                    $ficheMatiere->setLibelle($request->request->get('ficheMatiereLibelle'));
                    $ficheMatiere->setParcours($parcours); //todo: ajouter le semestre
                    $ficheMatiereRepository->save($ficheMatiere, true);
                }
                $elementConstitutif->setFicheMatiere($ficheMatiere);
            }

            $typeEc = $typeEcRepository->find($request->request->get('typeEc'));
            $elementConstitutif->setTypeEc($typeEc);

            $elementConstitutifRepository->save($elementConstitutif, true);

            return $this->json(true);
        }

        $matieres[] = $ficheMatiereRepository->findByParcours($parcours, $this->getCampagneCollecte());
        $matieres[] = $ficheMatiereRepository->findAllByHd($this->getCampagneCollecte());
        $matieres = array_merge(...$matieres);


        return $this->render('element_constitutif/_editEnfant.html.twig', [
            'element_constitutif' => $elementConstitutif,
            'parcours' => $parcours,
            'ue' => $ue,
            'typeEcs' => $typeEcRepository->findByTypeDiplomeAndFormation($typeDiplome, $parcours->getFormation()),
            'matieres' => $matieres,
            'isAdmin' => $isAdmin
        ]);
    }

    #[Route('/{id}/structure-ec/{parcours}', name: 'app_element_constitutif_structure', methods: ['GET', 'POST'])]
    public function structureEc(
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface       $entityManager,
        Request                      $request,
        ElementConstitutif           $elementConstitutif,
        Parcours                     $parcours
    ): Response {
        //        if ($this->isGranted(
        //            'ROLE_FORMATION_EDIT_MY',
        //            $elementConstitutif->getParcours()->getFormation()
        //        )) { //todo: ajouter le workflow...

        $isParcoursProprietaire = $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $parcours->getId() || ($elementConstitutif->getNatureUeEc()?->isChoix() && $elementConstitutif->getParcours()?->getId() === $parcours->getId());

        //todo: deprecated $raccroche ?
        $raccroche = $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() !== $parcours->getId();
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $ecHeures = $getElement->getFicheMatiereHeures();

        $form = $this->createForm(EcStep4Type::class, $ecHeures, [
            'isModal' => true,
            'data_class' => $ecHeures::class,
            'modalite' => $parcours->getModalitesEnseignement(),
            'action' => $this->generateUrl(
                'app_element_constitutif_structure',
                [
                    'id' => $elementConstitutif->getId(),
                    'parcours' => $parcours->getId()
                ]
            ),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $originalHeuresToText = $this->heuresToTexte($getElement->getFicheMatiereHeures());
            if (array_key_exists('heuresEnfantsIdentiques', $request->request->all()['ec_step4'])) {
                if ($elementConstitutif->getEcParent() !== null) {
                    $elementConstitutif->getEcParent()->setHeuresEnfantsIdentiques((bool)$request->request->all()['ec_step4']['heuresEnfantsIdentiques']);
                } else {
                    $elementConstitutif->setHeuresEnfantsIdentiques((bool)$request->request->all()['ec_step4']['heuresEnfantsIdentiques']);
                }
            } else {
                $elementConstitutif->setHeuresEnfantsIdentiques(false);
            }

            if (array_key_exists('sansHeure', $request->request->all())) {
                $elementConstitutif->setSansHeure($request->request->all()['sansHeure'] === 'on');
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
                    $newHeuresToText = $this->heuresToTexte($ecHeures);
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
                    $newHeuresToText = $this->heuresToTexte($elementConstitutif);
                }
            }

            $entityManager->flush();

            //evenement pour MCCC sur EC mis à jour
            $event = new McccUpdateEvent($elementConstitutif, $parcours);
            $event->setNewStructure($originalHeuresToText, $newHeuresToText);
            $eventDispatcher->dispatch($event, McccUpdateEvent::UPDATE_MCCC);

            return $this->json(true);
        }

        $editable = $request->query->get('editable', true);
        return $this->render('element_constitutif/_structureEcModal.html.twig', [
            'ec' => $elementConstitutif,
            'form' => $form->createView(),
            'raccroche' => $raccroche,
            'editable' => $editable,
            'parcours' => $parcours,
            'isParcoursProprietaire' => $isParcoursProprietaire,
            'modalite' => $parcours->getModalitesEnseignement()
        ]);
    }

    #[Route('/{id}/structure-ec-non-editable', name: 'app_element_constitutif_structure_non_editable', methods: ['GET', 'POST'])]
    public function structureEcNonEditable(
        ElementConstitutif $elementConstitutif,
    ): Response {

        $ec = new GetElementConstitutif($elementConstitutif, $elementConstitutif->getParcours());
        $ects = $ec->getFicheMatiereEcts();

        return $this->render('element_constitutif/_structureEcNonEditable.html.twig', [
            'ec' => $ec->getFicheMatiereHeures(),
            'modalite' => $elementConstitutif->getParcours()?->getModalitesEnseignement(),
            'ects' => $ects
        ]);
    }

    #[Route('/{id}/structure-but', name: 'app_element_constitutif_structure_but', methods: ['GET', 'POST'])]
    public function structureBut(
        Request                $request,
        FicheMatiereRepository $ficheMatiereRepository,
        FicheMatiere           $ficheMatiere
    ): Response {
        $form = $this->createForm(FicheMatiereStep4Type::class, $ficheMatiere, [
            'action' => $this->generateUrl(
                'app_element_constitutif_structure_but',
                ['id' => $ficheMatiere->getId()]
            ),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ficheMatiereRepository->save($ficheMatiere, true);

            return $this->json(true);
        }

        return $this->render('element_constitutif/_structureEcModalBut.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/{ue}/deplacer/{sens}', name: 'app_element_constitutif_deplacer', methods: ['GET'])]
    public function deplacer(
        EcOrdre            $ecOrdre,
        ElementConstitutif $elementConstitutif,
        Ue                 $ue,
        string             $sens
    ): Response {
        $ecOrdre->deplacerElementConstitutif($elementConstitutif, $sens, $ue);

        return $this->json(true);
    }

    #[Route('/{id}', name: 'app_element_constitutif_delete', methods: ['DELETE'])]
    public function delete(
        EcOrdre                      $ecOrdre,
        EntityManagerInterface       $entityManager,
        Request                      $request,
        ElementConstitutif           $elementConstitutif,
        ElementConstitutifRepository $elementConstitutifRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $elementConstitutif->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            foreach ($elementConstitutif->getMcccs() as $mccc) {
                $elementConstitutif->removeMccc($mccc);
                $entityManager->remove($mccc);
            }

            foreach ($elementConstitutif->getEcEnfants() as $enfant) {
                $entityManager->remove($enfant);
            }
            if ($elementConstitutif->getEcParent() === null) {
                $ecOrdre->removeElementConstitutif($elementConstitutif);
            } else {
                $ecOrdre->removeElementConstitutifEnfant($elementConstitutif);
            }
            $entityManager->remove($elementConstitutif);
            $entityManager->flush();


            return $this->json(true);
        }

        return $this->json(false, 500);
    }

    private function heuresToTexte(ElementConstitutif|FicheMatiere $elt): string
    {
        return $elt->getVolumeCmPresentiel().$elt->getVolumeTdPresentiel().$elt->getVolumeTpPresentiel().$elt->getVolumeCmDistanciel().$elt->getVolumeTdDistanciel().$elt->getVolumeTpDistanciel().$elt->getVolumeTe();
    }

}
