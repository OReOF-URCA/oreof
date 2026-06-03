<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\GetFormations;
use App\Classes\MentionProcess;
use App\Classes\verif\FormationState;
use App\Classes\verif\ParcoursState;
use App\DTO\StatsFichesMatieres;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\FormationVersioning;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Entity\UserProfil;
use App\Entity\Constantes;
use App\Enums\TypeModificationDpeEnum;
use App\Events\AddCentreFormationEvent;
use App\Form\FormationSesType;
use App\Repository\ComposanteRepository;
use App\Repository\DomaineRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\MentionRepository;
use App\Repository\ProfilRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\UserRepository;
use App\Service\FormationDiffBuilder;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use App\Service\SecureUploadService;
use App\Utils\Access;
use App\Utils\JsonRequest;
use App\Exception\FileUploadException;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/formation')]
class FormationController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecureUploadService $secureUploadService
    ){
    }

    #[Route('/', name: 'app_formation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('formation/index.html.twig');
    }

    #[Route('/liste-cfvu', name: 'app_formation_liste_cfvu', methods: ['GET'])]
    public function listeCfvu(
        DpeParcoursRepository $dpeParcoursRepository,
    ): Response
    {
        $allparcours = [];
        if ($this->isGranted('SHOW', ['route' => 'app_etablissement', 'subject' => 'etablissement'])) {
            $allparcours = $dpeParcoursRepository->findByCampagneAndTypeValidation($this->getCampagneCollecte(), 'soumis_cfvu');
        }

        return $this->render('validation/_liste.html.twig', [
            'allparcours' => $allparcours ?? [],
            'etape' => 'cfvu',
            'isCfvu' => true,
        ]);
    }

    #[Route('/liste', name: 'app_formation_liste', methods: ['GET'])]
    public function liste(
        UserRepository $userRepository,
        GetFormations         $getFormations,
        MentionRepository     $mentionRepository,
        ComposanteRepository  $composanteRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        MentionProcess                $validationProcess,
        Request               $request,
    ): Response {

        $responsables = $userRepository->findUserWithResponsabilites();

        $tFormations = $getFormations->getFormations(
            $this->getUser(),
            $this->getCampagneCollecte(),
            $request->query->all()
        );

        $nbFormations = count($tFormations);
        //comtper le nombre de parcours par formation
        $nbParcours = 0;
        foreach ($tFormations as $formation) {
            $nbParcours += count($formation->getParcours());
        }

        return $this->render('formation/_liste.html.twig', [
            'nbFormations' => $nbFormations,
            'nbParcours' => $nbParcours,
            'responsables' => $responsables,
            'formations' => $tFormations,
            'mentions' => $mentionRepository->findBy([], ['libelle' => 'ASC']),
            'composantes' => $composanteRepository->findPorteuse(),
            'typeDiplomes' => $typeDiplomeRepository->findBy([], ['libelle' => 'ASC']),
            'params' => $request->query->all(),
            'isCfvu' => false,
            'process' => $validationProcess->getProcess()
        ]);
    }

    #[Route('/detail/parcours/', name: 'app_parcours_formation_detail', methods: ['GET'])]
    public function parcoursFormation(
        Request $request,
        FormationRepository $formationRepository,
    ): Response {
        if ($request->query->has('formation')) {
            $formation = $formationRepository->find($request->query->get('formation'));
            if ($formation === null) {
                throw new Exception('Formation non trouvée');
            }
        } else {
            throw new Exception('Formation non trouvée');
        }

        $parcourss = $formation->getParcours();

        $dpesParcours = [];

        foreach ($parcourss as $parcours) {
            $dpesParcours[$parcours->getId()] = GetDpeParcours::getFromParcours($parcours);
        }

        return $this->render('formation/_parcoursFormation.html.twig', [
            'formation' => $formation,
            'parcours' => $parcourss,
            'dpesParcours' => $dpesParcours,
        ]);
    }

    #[Route('/fiches/liste', name: 'app_fiches_formation_liste', methods: ['GET'])]
    public function fichesFormation(
        GetFormations         $getFormations,
        MentionRepository     $mentionRepository,
        ComposanteRepository  $composanteRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        Request               $request,
    ): Response {

        $tFormations = $getFormations->getFormations(
            $this->getUser(),
            $this->getCampagneCollecte(),
            $request->query->all()
        );

        $stats = [];
        foreach ($tFormations as $formation) {

            $parcourss = $formation->getParcours();
            $stats[$formation->getId()]['stats'] = new StatsFichesMatieres();
            $typeD = $this->typeDiplomeResolver->get($formation->getTypeDiplome());
            foreach ($parcourss as $parcours) {

                $stats[$formation->getId()][$parcours->getId()] = $typeD->calculStructureParcours($parcours, false, false);
                $stats[$formation->getId()]['stats']->addStatsParcours(
                    $stats[$formation->getId()][$parcours->getId()]?->statsFichesMatieresParcours
                );
            }
        }

        return $this->render('formation/_fichesListe.html.twig', [
            'formations' => $tFormations,
            'mentions' => $mentionRepository->findBy([], ['libelle' => 'ASC']),
            'composantes' => $composanteRepository->findPorteuse(),
            'typeDiplomes' => $typeDiplomeRepository->findBy([], ['libelle' => 'ASC']),
            'params' => $request->query->all(),
            'isCfvu' => false,
            'stats' => $stats
        ]);
    }

    #[Route('/liste/{composante}', name: 'app_formation_liste_composante', methods: ['GET'])]
    public function listeComposante(
        MentionProcess        $validationProcess,
        MentionRepository     $mentionRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        ComposanteRepository  $composanteRepository,
        FormationRepository   $formationRepository,
        UserRepository        $userRepository,
        Composante            $composante,
        Request               $request
    ): Response {
        $q = $request->query->get('q') ?? null;

        $formations = $formationRepository->findBySearch(
            $q,
            $this->getCampagneCollecte(),
            $request->query->all(),
            $composante
        );

        $nbParcours = 0;
        foreach ($formations as $formation) {
            $nbParcours += count($formation->getParcours());
        }

        return $this->render('formation/_liste.html.twig', [
            'nbFormations' => count($formations),
            'nbParcours' => $nbParcours,
            'responsables' => $userRepository->findUserWithResponsabilites(),
            'formations' => $formations,
            'params' => $request->query->all(),
            'isCfvu' => false,
            'composantes' => $composanteRepository->findPorteuse(),
            'typeDiplomes' => $typeDiplomeRepository->findAll(),
            'mentions' => $mentionRepository->findAll(),
            'process' => $validationProcess->getProcess()
        ]);
    }

    #[Route('/new', name: 'app_formation_new', methods: ['GET', 'POST'])]
    public function new(
        WorkflowInterface $dpeParcoursWorkflow,
        ProfilRepository $profilRepository,
        MentionRepository    $mentionRepository,
        Request              $request,
        FormationRepository  $formationRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $formation = new Formation($this->getCampagneCollecte());
        $form = $this->createForm(FormationSesType::class, $formation, [
            'action' => $this->generateUrl('app_formation_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (array_key_exists(
                    'mention',
                    $request->request->all()['formation_ses']
                ) && $request->request->all()['formation_ses']['mention'] !== null && $request->request->all()['formation_ses']['mention'] !== 'autre') {
                $mention = $mentionRepository->find($request->request->all()['formation_ses']['mention']);
                $formation->setMentionTexte(null);
                $formation->setMention($mention);
            }

            $formation->addComposantesInscription($formation->getComposantePorteuse());
            $formation->setHasParcours(true);
            $formation->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_PARCOURS);
            $this->entityManager->persist($formation);

            $parcours = new Parcours($formation);
            $parcours->setLibelle('Parcours de formation à définir (création formation)');
            $parcours->setRespParcours($formation->getResponsableMention());

            $this->entityManager->persist($parcours);

            //création d'un DPE => Faudrait créer un parcours.
            $dpeParcours = new DpeParcours();
            $dpeParcours->setParcours($parcours);
            $dpeParcours->setFormation($formation);
            $dpeParcours->setCampagneCollecte($this->getCampagneCollecte());
            $dpeParcours->setVersion('0.1');
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);

            $parcours->addDpeParcour($dpeParcours);
            $this->entityManager->persist($dpeParcours);

            $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
            $uc = new UserProfil();
            $uc->setUser($formation->getResponsableMention());
            $uc->setCampagneCollecte($this->getCampagneCollecte());
            $uc->setFormation($formation);
            $uc->setProfil($profil);
            $this->entityManager->persist($uc);

            if ($formation->getCoResponsable() !== null) {
                $profil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);
                $ucCo = new UserProfil();
                $ucCo->setUser($formation->getCoResponsable());
                $ucCo->setCampagneCollecte($this->getCampagneCollecte());
                $ucCo->setFormation($formation);
                $ucCo->setProfil($profil);
                $this->entityManager->persist($ucCo);
            }

            $this->entityManager->flush();
            $dpeParcoursWorkflow->apply($dpeParcours, 'initialiser');
            $dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');

            return $this->redirectToRoute('app_formation_index');
        }

        return $this->render('formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form->createView()
        ]);
    }

    #[Route('/edit/formation/{slug}', name: 'app_formation_edit_modal', methods: ['GET', 'POST'])]
    public function editModal(
        ProfilRepository $profilRepository,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface   $entityManager,
        MentionRepository        $mentionRepository,
        Request                  $request,
        FormationRepository      $formationRepository,
        Formation                $formation
    ): Response {
        $form = $this->createForm(FormationSesType::class, $formation, [
            'action' => $this->generateUrl('app_formation_edit_modal', ['slug' => $formation->getSlug()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $savedMention = $formation->getMention();
            $savedMentionTexte = $formation->getMentionTexte();

            if (array_key_exists(
                    'mention',
                    $request->request->all()['formation_ses']
                ) && $request->request->all()['formation_ses']['mention'] !== null && $request->request->all()['formation_ses']['mention'] !== 'autre')
            {
                $mention = $mentionRepository->find($request->request->all()['formation_ses']['mention']);
                $formation->setMentionTexte(null);
                $formation->setMention($mention);
            }
            else
            {
                $formation->setMention($savedMention);
                $formation->setMentionTexte($savedMentionTexte);
            }

            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSets();
            $changeSet = $uow->getEntityChangeSet($formation);

            if (isset($changeSet['responsableMention'])) {
                $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
                if ($profil === null) {
                    throw new Exception('Profil ROLE_RESP_FORMATION non trouvé');
                }
                // retirer l'ancien resp des centres et droits et envoyer mail
                $event = new AddCentreFormationEvent($formation, $changeSet['responsableMention'][0], $profil, $this->getCampagneCollecte());
                $eventDispatcher->dispatch($event, AddCentreFormationEvent::REMOVE_CENTRE_FORMATION);

                // ajouter le nouveau resp, ajouter centre et droits et envoyer mail
                $event = new AddCentreFormationEvent($formation, $changeSet['responsableMention'][1], $profil, $this->getCampagneCollecte());
                $eventDispatcher->dispatch($event, AddCentreFormationEvent::ADD_CENTRE_FORMATION);
            }

            $formationRepository->save($formation, true);

            return $this->redirectToRoute('app_formation_edit', ['slug' => $formation->getSlug()]);
        }

        return $this->render('formation/editModal.html.twig', [
            'formation' => $formation,
            'form' => $form->createView()
        ]);
    }

    #[Route('/api', name: 'app_formation_api', methods: ['GET'])]
    public function api(
        MentionRepository     $mentionRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        DomaineRepository     $domaineRepository,
        Request               $request
    ): Response {
        $domaine = $domaineRepository->find($request->query->get('domaine'));

        if ($domaine === null) {
            return $this->json([
                'mentions' => [],
            ]);
        }

        $typeDiplome = $typeDiplomeRepository->find($request->query->get('typeDiplome'));

        return $this->json([
            'mentions' => $mentionRepository->findByDomaineAndTypeDiplomeArray($domaine, $typeDiplome),
            'selectedMention' => $request->query->get('mention')
        ]);
    }

    #[Route('/{slug}', name: 'app_formation_show', methods: ['GET'])]
    public function show(
        Formation               $formation,
        VersioningParcours $versioningParcours,
        VersioningFormation $versioningFormation,
        FormationDiffBuilder $formationDiffBuilder
    ): Response {
        $typeDiplome = $formation->getTypeDiplome();

        if ($typeDiplome === null) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);
        $hasLastVersion = false;
        /**
         * VERSIONING PARCOURS PAR DÉFAUT
         */
        $cssDiff = DiffHelper::getStyleSheet();
        if ($formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
            $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($formation->getParcours()[0]);
            $textDifferencesParcoursCampagne = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($formation->getParcours()[0], true);
            $hasLastVersion = $versioningParcours->hasLastVersion($formation->getParcours()[0]);
        }

        /**
         * VERSIONING FORMATION
         */
        $formationStringDifferences = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
        $formationCampagneStringDifferences = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation, true);

        //Si l'utilisateur peut voir les différences
        $canSeeDifferences = $this->isGranted('RELATED_TO_PARCOURS', $formation);

        // Afficher les comparaisons directement
        $request = Request::createFromGlobals();
        $displayComparaison = $request->query->get('optionDisplay', 'false');

        // Alerte de comparaison entre années : on regarde la chaîne ascendante
        // (versions antérieures ou égales). Une version antérieure modifiée se
        // signale donc sur toutes les versions postérieures, mais pas en amont.
        $comparaisonPair = $formationDiffBuilder->firstDifferingPair($formation);
        $comparaisonAnneeDiff = $comparaisonPair !== null;
        $comparaisonAnneeSourceId = $comparaisonPair ? $comparaisonPair['source']->getId() : null;
        $comparaisonAnneeCibleId = $comparaisonPair ? $comparaisonPair['cible']->getId() : null;
        $comparaisonAnneeSourceLabel = $comparaisonPair ? $formationDiffBuilder->anneeLabel($comparaisonPair['source']) : null;
        $comparaisonAnneeCibleLabel = $comparaisonPair ? $formationDiffBuilder->anneeLabel($comparaisonPair['cible']) : null;

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
            'typeDiplome' => $typeDiplome,
            'typeD' => $typeD,
            'cssDiff' => $cssDiff,
            'stringDifferencesParcoursDefautCampagne' => $textDifferencesParcoursCampagne ?? [],
            'stringDifferencesParcoursDefaut' => $textDifferencesParcours ?? [],
            'stringDifferencesFormation' => $formationStringDifferences ?? [],
            'stringDifferencesFormationCampagne' => $formationCampagneStringDifferences ?? [],
            'versioningParcours' => $versioningParcours,
            'hasLastVersion' => $hasLastVersion,
            'displayComparaison' => $displayComparaison,
            'canSeeDifferences' => $canSeeDifferences,
            'comparaisonAnneeDiff' => $comparaisonAnneeDiff,
            'comparaisonAnneeSourceId' => $comparaisonAnneeSourceId,
            'comparaisonAnneeCibleId' => $comparaisonAnneeCibleId,
            'comparaisonAnneeSourceLabel' => $comparaisonAnneeSourceLabel,
            'comparaisonAnneeCibleLabel' => $comparaisonAnneeCibleLabel,
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_formation_edit', methods: ['GET', 'POST'])]
    public function edit(
        VersioningParcours $versioningParcours,
        ParcoursState       $parcoursState,
        FormationState      $formationState,
        Request             $request,
        Formation           $formation,
    ): Response {

        if (
            !(
                $this->isGranted('EDIT', ['route' => 'app_formation', 'subject' => $formation]) ||
                $this->isGranted('EDIT', ['route' => 'app_composante', 'subject' => $formation]) ||
                $this->isGranted('EDIT', ['route' => 'app_etablissement', 'subject' => $formation]) ||
                $this->isGranted('ROLE_ADMIN')
            )
            || !Access::isOuvert($formation)
        ) {
            if ($formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
                if (!$this->isGranted(
                    'EDIT',
                    [
                        'route' => 'app_parcours',
                        'subject' => $formation->getParcours()->first(),
                    ]
                )) {
                    return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
                }
            } else {
                return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
            }
        }

        $formationState->setFormation($formation);
        $typeD = $this->typeDiplomeResolver->get($formation->getTypeDiplome());
        if ($formation->getParcours()?->first() !== false) {
            $parcoursState->setParcours($formation->getParcours()?->first());
        }

        if ($formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
            $hasLastVersion = $versioningParcours->hasLastVersion($formation->getParcours()[0]);
            $dpeParcours = GetDpeParcours::getFromParcours($formation->getParcours()[0]);
        }

        return $this->render('formation/edit.html.twig', [
            'formation' => $formation,
            'selectedStep' => $request->query->get('step', 1),
            'typeDiplome' => $formation->getTypeDiplome(),
            'parcoursState' => $parcoursState,
            'formationState' => $formationState,
            'typeD' => $typeD,
            'dpeParcours' => $dpeParcours ?? null,
            'hasLastVersion' => $hasLastVersion ?? null
        ]);
    }

    #[Route('/{id}', name: 'app_formation_delete', methods: ['DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request                $request,
        Formation              $formation,
        FormationRepository    $formationRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $formation->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            foreach ($formation->getParcours() as $parcours) {
                foreach ($parcours->getBlocCompetences() as $blocs) {
                    $entityManager->remove($blocs);
                }

                foreach ($parcours->getSemestreParcours() as $semestreParcour) {
                    $semestre = $semestreParcour->getSemestre();
                    if ($semestre !== null) {
                        foreach ($semestre->getUes() as $ue) {
                            foreach ($ue->getElementConstitutifs() as $ec) {
                                $entityManager->remove($ec);
                            }
                            if ($semestre->isTroncCommun() === false) {
                                $entityManager->remove($ue);
                            }
                        }
                        if ($semestre->isTroncCommun() === false) {
                            $entityManager->remove($semestre);
                        }
                    }

                    $entityManager->remove($semestreParcour);
                }

                foreach ($parcours->getFicheMatiereParcours() as $fiche) {
                    $entityManager->remove($fiche);
                }
                $entityManager->flush();
                $entityManager->remove($parcours);
            }
            foreach ($formation->getBlocCompetences() as $blocs) {
                $entityManager->remove($blocs);
            }
            $formationRepository->remove($formation, true);

            return $this->json(true);
        }

        return $this->json(false);
    }

    #[Route('/{slug}/maquette_iframe', name: 'app_formation_maquette_iframe')]
    public function getFormationMaquetteIframe(Formation $formation, CalculStructureParcours $calcul) : Response
    {
        $listeParcours = [];

        foreach ($formation->getParcours() as $parcours) {
            $listeParcours[] = $calcul->calcul($parcours);
        }

        return $this->render('formation/maquette_iframe.html.twig', [
            'listeParcours' => $listeParcours
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/mention/list', name: 'app_formation_liste_id')]
    public function getParcoursListForFormations(
        EntityManagerInterface $entityManager
    ) : Response {

        $formations = $entityManager->getRepository(Formation::class)->findBy(['dpe' => 1]);

        return $this->render("formation/parcours_list_with_id.html.twig", [
            'formations' => $formations
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{slug}/versioning/save', name: "app_formation_versioning_save")]
    public function saveFormationIntoJson(
        Formation $formation,
        VersioningFormation $versioningFormationService,
        Filesystem $filesystem
    ): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            /** @var User $utilisateur */
            $utilisateur = $this->getUser();
            $now = new DateTimeImmutable();
            $dateHeure = $now->format('d-m-Y_H-i-s');
            $versioningFormationService->saveVersionOfFormation($formation, $now, true);
            // Log
            $successMessage = "[{$dateHeure}] La formation a bien été sauvegardée ({$formation->getSlug()})"
            . "\nUtilisateur : {$utilisateur->getPrenom()} {$utilisateur->getNom()} - ID : {$utilisateur->getUsername()}\n";
            $filesystem->appendToFile(__DIR__ . "/../../versioning_json/success_log/save_formation_success.log", $successMessage);

            $this->addFlashBag('success', 'La formation a bien été sauvegardée.');
            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        } catch (Exception $e) {
            $errorMessage = "[{$dateHeure}] Le versioning de la formation a rencontré une erreur."
                . "\nUtilisateur : {$utilisateur->getPrenom()} {$utilisateur->getNom()} - ID : {$utilisateur->getUsername()}"
                ."\nMessage : {$e->getMessage()}\n";
            $filesystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/save_formation_error.log", $errorMessage);

            $this->addFlashBag('error', 'Une erreur est survenue lors de la sauvegarde.');
            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/versioning/view', name: 'app_formation_versioning_view')]
    public function viewFormationVersion(
        FormationVersioning $versionFormation,
        VersioningFormation $versionFormationService,
        VersioningParcours $versionParcoursService,
        Filesystem $filesystem,
        EntityManagerInterface $entityManager
    ): \Symfony\Component\HttpFoundation\RedirectResponse|Response
    {
        try {
            $formation = $versionFormationService->loadFormationFromVersion($versionFormation);
            $typeD = $this->typeDiplomeResolver->get($versionFormation->getFormation()?->getTypeDiplome());
            $dateHeureVersion = $versionFormation->getVersionTimestamp()->format('d/m/Y à H:i');

            $parcoursVersionArray = [];
            foreach ($versionFormation->getFormation()->getParcours() as $p) {
                $lastVersion = $entityManager->getRepository(ParcoursVersioning::class)->findLastVersion($p);
                if (count($lastVersion) > 0) {
                    $parcoursVersionArray[] = $lastVersion[0];
                }
            }

            $parcoursVersionArray = array_map(
                fn ($version) => $versionParcoursService->loadParcoursFromVersion($version),
                $parcoursVersionArray
            );

            return $this->render('formation/show.versioning.html.twig', [
                'typeD' => $typeD,
                'typeDiplome' => $versionFormation->getFormation()->getTypeDiplome(),
                'formation' => $formation,
                'isVersioningView' => true,
                'parcoursVersionArray' => $parcoursVersionArray,
                'dateHeureVersion' => $dateHeureVersion,
                // 'isBut' => $versionFormation->getFormation()->getTypeDiplome()->getLibelleCourt() === "BUT"
            ]);

        } catch (Exception $e) {
            $now = new DateTime();
            $dateHeure = $now->format('d-m-Y_H-i-s');
            $errorMessage = "[{$dateHeure}] La visualisation de version de la formation a rencontré une erreur."
                . "\nFormation : {$versionFormation->getFormation()->getDisplayLong()} - ID : {$versionFormation->getFormation()->getId()}"
                . "\nMessage : {$e->getMessage()}\n";
            $filesystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/view_formation_error.log", $errorMessage);

            $this->addFlashBag('error', 'Une erreur est survenue lors de la visualisation');
            return $this->redirectToRoute('app_formation_show', ['slug' => $versionFormation->getFormation()->getSlug()]);
        }
    }

}
