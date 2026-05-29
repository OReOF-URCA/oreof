<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereWizardController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Mailer;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Notification;
use App\Entity\Parcours;
use App\Form\FicheMatiereStep1Type;
use App\Form\FicheMatiereStep2Type;
use App\Form\FicheMatiereStep3Type;
use App\Form\FicheMatiereStep4HdType;
use App\Form\FicheMatiereStep4Type;
use App\Repository\BlocCompetenceRepository;
use App\Repository\ButCompetenceRepository;
use App\Repository\ComposanteRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\TypeEpreuveRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiche-matiere/step')]
class FicheMatiereWizardController extends BaseController
{
    #[Route('/', name: 'app_fiche_matiere_wizard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('fiche_matiere/index.html.twig');
    }

    /**
     * @throws TypeDiplomeNotFoundException
     */
    #[Route('/{id}/synthese', name: 'app_fiche_matiere_wizard_synthese', methods: ['GET'])]
    public function synthese(
        FicheMatiere $ficheMatiere,
    ): Response {
        return $this->render('fiche_matiere/_synthese_ec.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/{ficheMatiere}/1', name: 'app_fiche_matiere_wizard_step_1', methods: ['GET'])]
    public function step1(FicheMatiere $ficheMatiere): Response
    {
        if ($ficheMatiere->getParcours() !== null && $ficheMatiere->getParcours()->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
            $isBut = true;
        } else {
            $isBut = false;
        }
        $isScol = $this->isGranted('CAN_ETABLISSEMENT_SCOLARITE_ALL', $this->getuser());
        $form = $this->createForm(FicheMatiereStep1Type::class, $ficheMatiere, [
            'isBut' => $isBut,
            'isScol' => $isScol,
        ]);
        return $this->render('fiche_matiere_wizard/_step1.html.twig', [
            'form' => $form->createView(),
            'ficheMatiere' => $ficheMatiere,
            'isBut' => $isBut,
            'isScol' => $isScol,

        ]);
    }

    #[Route('/{ficheMatiere}/mutualise', name: 'app_fiche_matiere_wizard_step_1_mutualise', methods: ['GET'])]
    public function mutualise(FicheMatiere $ficheMatiere): Response
    {
        return $this->render('fiche_matiere_wizard/_step1_mutualise.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/{ficheMatiere}/mutualise/add', name: 'app_fiche_matiere_wizard_step_1_mutualise_add', methods: ['GET'])]
    public function mutualiseAdd(
        ComposanteRepository $composanteRepository,
        FicheMatiere         $ficheMatiere,
    ): Response {
        $composantes = $composanteRepository->findAll();//todo: filtrer dans les formations

        return $this->render('fiche_matiere_wizard/_step1_mutualise_add.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'composantes' => $composantes
        ]);
    }

    #[Route('/{ficheMatiere}/parcours-porteur', name: 'app_fiche_matiere_wizard_change_parcours_porteur', methods: ['GET'])]
    public function changeParcoursPorteur(
        FormationRepository $formationRepository,
        FicheMatiere        $ficheMatiere,
    ): Response
    {
        return $this->render('fiche_matiere_wizard/_change_parcours_porteur.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'formations' => $this->getAvailableFormationsForParcoursPorteur($formationRepository, $ficheMatiere),
        ]);
    }

    #[Route('/{ficheMatiere}/parcours-porteur/ajax', name: 'app_fiche_matiere_wizard_change_parcours_porteur_ajax', methods: ['POST'])]
    public function changeParcoursPorteurAjax(
        Request                            $request,
        EntityManagerInterface             $entityManager,
        FormationRepository                $formationRepository,
        ParcoursRepository                 $parcoursRepository,
        ElementConstitutifRepository       $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
    ): Response
    {
        $data = JsonRequest::getFromRequest($request);

        switch ($data['field'] ?? null) {
            case 'parcours':
                $formation = isset($data['value']) ? $formationRepository->find($data['value']) : null;
                if (!$this->isFormationAvailableForParcoursPorteur($formationRepository, $ficheMatiere, $formation)) {
                    return $this->json([]);
                }

                $t = [];
                foreach ($parcoursRepository->findByFormation($formation) as $parcours) {
                    $t[] = [
                        'id' => $parcours->getId(),
                        'libelle' => $parcours->isParcoursDefaut() ? Parcours::PARCOURS_DEFAUT : $parcours->getDisplay(),
                    ];
                }

                return $this->json($t);

            case 'save':
                $formation = isset($data['formation']) ? $formationRepository->find($data['formation']) : null;
                if (!$this->isFormationAvailableForParcoursPorteur($formationRepository, $ficheMatiere, $formation)) {
                    return $this->json([
                        'success' => false,
                        'message' => 'La mention sélectionnée n\'est pas disponible pour cette fiche matière.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $parcours = $this->resolveParcoursPorteurCible(
                    $formation,
                    $data['parcours'] ?? null,
                    $parcoursRepository
                );

                if ($parcours === null || $parcours->getFormation() !== $formation) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Veuillez sélectionner un parcours valide.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                if ($ficheMatiere->getParcours()?->getId() === $parcours->getId()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le parcours sélectionné est déjà le parcours porteur de cette fiche matière.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $ancienParcours = $ficheMatiere->getParcours();
                $ancienParcoursMutualise = false;

                $liaisonCible = $ficheMatiereParcoursRepository->findOneBy([
                    'ficheMatiere' => $ficheMatiere,
                    'parcours' => $parcours,
                ]);
                if ($liaisonCible !== null) {
                    $entityManager->remove($liaisonCible);
                }

                if ($ancienParcours !== null) {
                    $usagesAncienParcours = $elementConstitutifRepository->findBy([
                        'ficheMatiere' => $ficheMatiere,
                        'parcours' => $ancienParcours,
                    ]);

                    if (count($usagesAncienParcours) > 0) {
                        $this->updateFicheMatiereMutualisable(
                            $ficheMatiereParcoursRepository,
                            $ficheMatiere,
                            $ancienParcours,
                            $entityManager
                        );
                        $ancienParcoursMutualise = true;
                    }
                }

                $ficheMatiere->setParcours($parcours);
                $composante = $parcours->getFormation()?->getComposantePorteuse();
                if ($composante !== null && !$ficheMatiere->getComposante()->contains($composante)) {
                    $ficheMatiere->addComposante($composante);
                    $composante->addFicheMatiere($ficheMatiere);
                }

                $entityManager->flush();

                $ficheMatiere->setEnseignementMutualise(
                    $ficheMatiereParcoursRepository->count(['ficheMatiere' => $ficheMatiere]) > 0
                );
                $entityManager->flush();

                return $this->json([
                    'success' => true,
                    'ancienParcoursMutualise' => $ancienParcoursMutualise,
                    'message' => sprintf(
                        'Le parcours porteur est désormais "%s / %s".',
                        $parcours->getFormation()?->getDisplay() ?? 'Formation inconnue',
                        $parcours->isParcoursDefaut() ? Parcours::PARCOURS_DEFAUT : $parcours->getDisplay()
                    ),
                ]);
        }

        return $this->json([]);
    }

    #[Route('/{ficheMatiere}/mutualise/impacts', name: 'app_fiche_matiere_wizard_step_1_mutualise_impacts', methods: ['GET'])]
    public function mutualiseImpacts(FicheMatiere $ficheMatiere): Response
    {
        return $this->render('fiche_matiere_wizard/_step1_mutualise_impacts.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/{ficheMatiere}/mutualise/remove-all', name: 'app_fiche_matiere_wizard_step_1_mutualise_remove_all', methods: ['DELETE'])]
    public function mutualiseRemoveAll(
        ElementConstitutifRepository       $ecRepository,
        EntityManagerInterface             $entityManager,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        Mailer                             $mailer,
        FicheMatiere                       $ficheMatiere,
    ): Response
    {
        $ecUtilsateurs = $ecRepository->findBy(['ficheMatiere' => $ficheMatiere]);
        $liaisons = $ficheMatiereParcoursRepository->findBy(['ficheMatiere' => $ficheMatiere]);

        $notifications = [];
        // suppression des EC utilisant la fiche, on gère les notif sur les deux
        foreach ($ecUtilsateurs as $ec) {
            $parcours = $ec->getParcours();
            if ($parcours !== null) {
                $resp = $parcours->getRespParcours();
                if ($resp !== null) {
                    $userKey = $resp->getId() ?? spl_object_id($resp);
                    if (!array_key_exists($userKey, $notifications)) {
                        $notifications[$userKey] = [
                            'user' => $resp,
                            'parcours' => [],
                        ];
                    }

                    $notifications[$userKey]['parcours'][$parcours->getId() ?? spl_object_id($parcours)] = $parcours;
                }
                $ec->setFicheMatiere(null);

            }
        }

        $notifications = [];
        foreach ($liaisons as $liaison) {
            $parcours = $liaison->getParcours();
            if ($parcours !== null) {
                $resp = $parcours->getRespParcours();
                if ($resp !== null) {
                    $userKey = $resp->getId() ?? spl_object_id($resp);
                    if (!array_key_exists($userKey, $notifications)) {
                        $notifications[$userKey] = [
                            'user' => $resp,
                            'parcours' => [],
                        ];
                    }

                    $notifications[$userKey]['parcours'][$parcours->getId() ?? spl_object_id($parcours)] = $parcours;
                }
            }
            $entityManager->remove($liaison);
        }

        $ficheMatiere->setEnseignementMutualise(false);
        $entityManager->flush();

        $mailErrors = 0;
        foreach ($notifications as $notificationData) {
            $user = $notificationData['user'];
            $parcoursImpactes = array_values($notificationData['parcours']);
            $listeParcours = array_map(static fn($parcours) => $parcours->getDisplay(), $parcoursImpactes);

            $notification = new Notification();
            $notification->setDestinataire($user);
            $notification->setTitle('Mutualisation supprimée');
            $notification->setBody(sprintf(
                'La fiche matière "%s" n\'est plus mutualisée avec %d parcours : %s. Veuillez vérifier vos éléments constitutifs.',
                $ficheMatiere->getLibelle() ?? 'sans libellé',
                count($listeParcours),
                implode(', ', $listeParcours)
            ));
            $notification->setPayload([
                'ficheMatiere' => $ficheMatiere->getId(),
                'parcours' => array_map(static fn($parcours) => $parcours->getId(), $parcoursImpactes),
            ]);
            $entityManager->persist($notification);

            if ($user->getEmail() !== null) {
                try {
                    $mailer->initEmail();
                    $mailer->setTemplate('mails/mutualisation/fiche_demutualisee.html.twig', [
                        'user' => $user,
                        'ficheMatiere' => $ficheMatiere,
                        'parcoursImpactes' => $parcoursImpactes,
                    ]);
                    $mailer->sendMessage(
                        [$user->getEmail()],
                        '[ORéOF] Suppression de mutualisation d\'une fiche matière'
                    );
                } catch (\Throwable) {
                    ++$mailErrors;
                }
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'count' => count($liaisons),
            'notified' => count($notifications),
            'mailErrors' => $mailErrors,
        ]);
    }

    #[Route('/{ficheMatiere}/mutualise/ajax', name: 'app_fiche_matiere_wizard_step_1_mutualise_add_ajax', methods: [
        'POST',
        'DELETE'
    ])]
    public function mutualiseAjax(
        EntityManagerInterface             $entityManager,
        ComposanteRepository               $composanteRepository,
        Request                            $request,
        FormationRepository                $formationRepository,
        ParcoursRepository                 $parcoursRepository,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        $t = [];
        switch ($data['field']) {
            case 'formation':
                $composante = $composanteRepository->find($data['value']);
                if ($composante === null) {
                    return $this->json([]);
                }
                $formations = $formationRepository->findByComposante($composante, $this->getCampagneCollecte());
                foreach ($formations as $formation) {
                    $t[] = [
                        'id' => $formation->getId(),
                        'libelle' => $formation->getDisplayLong()
                    ];
                }
                break;
            case 'parcours':
                $formation = $formationRepository->find($data['value']);
                if ($formation === null) {
                    return $this->json([]);
                }
                $parcours = $parcoursRepository->findByFormation($formation);
                foreach ($parcours as $parcour) {
                    $t[] = [
                        'id' => $parcour->getId(),
                        'libelle' => $parcour->getDisplay()
                    ];
                }
                break;
            case 'save':

                if ($data['formation'] === 'all') {
                    $composante = $composanteRepository->find($data['composante']);
                    if ($composante !== null and !$ficheMatiere->getComposante()->contains($composante)) {
                        $ficheMatiere->addComposante($composante);
                        $composante->addFicheMatiere($ficheMatiere);
                        $entityManager->flush();
                    }
                    return $this->json(true);
                }

                if (!isset($data['parcours']) || $data['parcours'] === '') {
                    $formation = $formationRepository->find($data['formation']);
                    if ($formation !== null && $formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
                        $parcours = $formation->getParcours()[0];
                        $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                    }
                } else {
                    if ($data['parcours'] !== 'all') {
                        $parcours = $parcoursRepository->find($data['parcours']);
                        $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                    } else {
                        $formation = $formationRepository->find($data['formation']);
                        if ($formation !== null) {
                            foreach ($formation->getParcours() as $parcours) {
                                $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                            }
                        }
                    }
                }


                return $this->json(true);
            case 'delete':
                $fiche = $ficheMatiereParcoursRepository->find($data['fiche']);
                if ($fiche !== null) {
                    $entityManager->remove($fiche);
                    $entityManager->flush();
                }
                //todo: supprimer les EC qui utilisent ?

                return $this->json(true);
        }

        return $this->json($t);
    }

    #[Route('/{ficheMatiere}/2', name: 'app_fiche_matiere_wizard_step_2', methods: ['GET'])]
    public function step2(
        FicheMatiere $ficheMatiere,
    ): Response {
        $form = $this->createForm(FicheMatiereStep2Type::class, $ficheMatiere);


        return $this->render('fiche_matiere_wizard/_step2.html.twig', [
            'form' => $form->createView(),
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/{ficheMatiere}/3', name: 'app_fiche_matiere_wizard_step_3', methods: ['GET'])]
    public function step3(
        ButCompetenceRepository  $butCompetenceRepository,
        BlocCompetenceRepository $blocCompetenceRepository,
        FicheMatiere             $ficheMatiere,
    ): Response {
        $form = $this->createForm(FicheMatiereStep3Type::class, $ficheMatiere);
        $isBut = false;
        if ($ficheMatiere->getParcours() !== null && $ficheMatiere->getParcours()->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
            $isBut = true;
            $ecBccs = [];
            $ecComps = [];

            foreach ($ficheMatiere->getApprentissagesCritiques() as $competence) {
                $ecComps[] = $competence->getId();
                $ecBccs[] = $competence->getNiveau()?->getCompetence()?->getId();
            }

            $bccs = $butCompetenceRepository->findBy(['formation' => $ficheMatiere->getParcours()->getFormation()], ['numero' => 'ASC']);
        } else {
            $ecBccs = [];
            $ecComps = [];

            foreach ($ficheMatiere->getCompetences() as $competence) {
                $ecComps[] = $competence->getId();
                $ecBccs[] = $competence->getBlocCompetence()?->getId();
            }


            if ($ficheMatiere->getParcours() !== null) {
                $bccs = $blocCompetenceRepository->findByParcours($ficheMatiere->getParcours());
            } else {
                $bccs = $blocCompetenceRepository->findBy(['parcours' => null]);
            }
        }


        return $this->render('fiche_matiere_wizard/_step3.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'form' => $form->createView(),
            'bccs' => $bccs,
            'isBut' => $isBut,
            'ecBccs' => array_flip(array_unique($ecBccs)),
            'ecComps' => array_flip($ecComps),
        ]);
    }

    #[Route('/{ficheMatiere}/4/{type}', name: 'app_fiche_matiere_wizard_step_4', methods: ['GET'])]
    public function step4(
        TypeEpreuveRepository        $typeEpreuveRepository,

        FicheMatiere        $ficheMatiere,
        string              $type,
    ): Response {
        $parcours = [];
        $ecProprietaire = null;
        foreach ($ficheMatiere->getElementConstitutifs() as $ec) {
            if ($ec->getParcours() !== null) {
                $parcours[] = $ec->getParcours();
            }

            if ($ec->getParcours() === $ficheMatiere->getParcours()) {
                $ecProprietaire = $ec;
            }
        }

        if ($type === 'hd') {
            $form = $this->createForm(FicheMatiereStep4HdType::class, $ficheMatiere);

            return $this->render('fiche_matiere_wizard/_step4Hd.html.twig', [
                'ficheMatiere' => $ficheMatiere,
                'form' => $form->createView(),
            ]);
        }

        $typeDiplome = $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome();

        if ($type === 'but') {
            $form = $this->createForm(FicheMatiereStep4Type::class, $ficheMatiere);
            $typeD = $this->typeDiplomeResolver->get($typeDiplome);

            return $this->render('fiche_matiere_wizard/_step4But.html.twig', [
                'mcccs' => $typeD->getMcccs($ficheMatiere),
                'ficheMatiere' => $ficheMatiere,
                'form' => $form->createView(),
            ]);
        }



        if ($type === 'other') {
            $typeD = $this->typeDiplomeResolver->get($typeDiplome);
            return $this->render('fiche_matiere_wizard/_step4Other.html.twig', [
                'ficheMatiere' => $ficheMatiere,
                'parcours' => $parcours,
                'mcccs' => $typeD->getDisplayMccc($typeD->getMcccs($ficheMatiere), $ficheMatiere->getTypeMccc() ?? ''),
                'ecProprietaire' => $ecProprietaire,
                'typeMccc' => $ficheMatiere->getTypeMccc(),
                'typeD' => $typeD,
                'templateForm' => $typeD !== null ? $typeD::TEMPLATE_FORM_MCCC : '',
            ]);
        }

        throw new \InvalidArgumentException('Type de fiche matière non géré : ' . $type);
    }

    private function updateFicheMatiereMutualisable(
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
        mixed                              $parcours,
        EntityManagerInterface             $entityManager
    ): void {
        $exist = $ficheMatiereParcoursRepository->findOneBy([
            'ficheMatiere' => $ficheMatiere,
            'parcours' => $parcours
        ]);

        if ($exist === null) {
            $ficheMatiereParcours = new FicheMatiereMutualisable();
            $ficheMatiereParcours->setFicheMatiere($ficheMatiere);
            $ficheMatiereParcours->setParcours($parcours);
            $entityManager->persist($ficheMatiereParcours);
            $entityManager->flush();
        }
    }

    private function getAvailableFormationsForParcoursPorteur(
        FormationRepository $formationRepository,
        FicheMatiere        $ficheMatiere,
    ): array
    {
        $typeDiplome = $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome();

        if ($typeDiplome !== null) {
            return $formationRepository->findByDpeAndTypeDiplome($this->getCampagneCollecte(), $typeDiplome);
        }

        return $formationRepository->findByCampagneCollecte($this->getCampagneCollecte());
    }

    private function isFormationAvailableForParcoursPorteur(
        FormationRepository    $formationRepository,
        FicheMatiere           $ficheMatiere,
        ?\App\Entity\Formation $formation,
    ): bool
    {
        if ($formation === null) {
            return false;
        }

        foreach ($this->getAvailableFormationsForParcoursPorteur($formationRepository, $ficheMatiere) as $availableFormation) {
            if ($availableFormation->getId() === $formation->getId()) {
                return true;
            }
        }

        return false;
    }

    private function resolveParcoursPorteurCible(
        ?\App\Entity\Formation $formation,
        mixed                  $parcoursId,
        ParcoursRepository     $parcoursRepository,
    ): ?\App\Entity\Parcours
    {
        if ($formation === null) {
            return null;
        }

        if ($parcoursId !== null && $parcoursId !== '') {
            return $parcoursRepository->find($parcoursId);
        }

        return $formation->defaultParcours();
    }
}
