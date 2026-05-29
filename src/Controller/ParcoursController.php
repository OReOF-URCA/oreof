<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ParcoursController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\ParcoursDupliquer;
use App\Classes\verif\ParcoursState;
use App\Entity\CampagneCollecte;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Entity\User;
use App\Entity\Constantes;
use App\Enums\ConfigurationPublicationEnum;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Events\AddCentreParcoursEvent;
use App\Form\ParcoursType;
use App\Repository\ElementConstitutifRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Service\LheoXML;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use App\Utils\JsonRequest;
use DateTimeImmutable;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Jfcherng\Diff\DiffHelper;
use JsonException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Route('/parcours')]
class ParcoursController extends BaseController
{
    public function __construct(
        private WorkflowInterface $dpeParcoursWorkflow,
        private readonly EntityManagerInterface $entityManager,
        private readonly \App\Service\SecureUploadService $secureUploadService,
    ) {
    }

    #[Route('/', name: 'app_parcours_index', methods: ['GET'])]
    /** @deprecated  */
    public function index(): Response
    {

        return $this->render('parcours/index.html.twig');
    }

    #[Route('/liste', name: 'app_parcours_liste', methods: ['GET'])]
    public function liste(
        ParcoursRepository $parcoursRepository,
        Request            $request,
    ): Response {
        $sort = $request->query->get('sort') ?? 'libelle';
        $direction = $request->query->get('direction') ?? 'asc';
        $q = $request->query->get('q') ?? null;


        $parcours = $parcoursRepository->findParcours(
            $this->getCampagneCollecte(),
            [$sort => $direction, 'recherche' => $q]
        );


        $tParcours = [];

        if ($this->isGranted('ROLE_ADMIN') ||
            $this->isGranted('SHOW', ['route' => 'app_parcours', 'subject' => 'parcours'])) {
            $tParcours = $parcours;
        } else {
            foreach ($parcours as $p) {
                if ($this->isGranted('EDIT', ['route' => 'app_parcours', 'subject' => $p])
                    && ($p->getRespParcours() === $this->getUser() || $p->getCoResponsable() === $this->getUser())) {
                    $tParcours[] = $p;
                }
            }
        }


        return $this->render('parcours/_liste.html.twig', [
            'parcours' => $tParcours,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new/{formation}', name: 'app_parcours_new', methods: ['GET', 'POST'])]
    public function new(
        ProfilRepository $profRepository,
        EventDispatcherInterface $eventDispatcher,
        Request                  $request,
        ParcoursRepository       $parcoursRepository,
        Formation                $formation
    ): Response {
//        $this->denyAccessUnlessGranted('EDIT', [
//            'route' => 'app_formation',
//            'subject' => $formation
//        ]);

        $parent = null;
        $parcour = new Parcours($formation);
        if ($request->query->has('parent') && $request->query->get('parent') !== 'null') {
            $parent = $parcoursRepository->find($request->query->get('parent'));
            if (null === $parent) {
                throw $this->createNotFoundException();
            }
            $parcour->setParcoursParent($parent);
        }

        $parcour->setModalitesEnseignement(null);
        $form = $this->createForm(ParcoursType::class, $parcour, [
            'formation' => $formation,
            'action' => $this->generateUrl('app_parcours_new', [
                'formation' => $formation->getId(),
                'parent' => $parent?->getId(),
            ]),
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFiles = $form->get('logo')->getData();
            if ($logoFiles) {
                $hasFormatError = false;
                $hasSizeError = false;
                foreach ($logoFiles as $logoFile) {
                    try {
                        $uploaded = $this->secureUploadService->upload($logoFile, 'logos');
                        $parcour->addLogo($uploaded->getStoredFilename());
                    } catch (FileUploadException $e) {
                        if (str_contains($e->getMessage(), 'volumineux')) {
                            $hasSizeError = true;
                        } else {
                            $hasFormatError = true;
                        }
                    }
                }
                if ($hasSizeError) {
                    $this->addFlashBag(Constantes::FLASHBAG_ERROR, 'Un ou plusieurs fichier(s) est/sont trop lourd(s) (10 Mo max)');
                }
                if ($hasFormatError) {
                    $this->addFlashBag(Constantes::FLASHBAG_ERROR, 'Un ou plusieurs fichier(s) n\'est/ne sont pas au bon format (PNG/JPEG/JPG uniquement)');
                }
            }
            $dpeParcours = new DpeParcours();
            $dpeParcours->setParcours($parcour);
            $dpeParcours->setFormation($formation);
            $dpeParcours->setCampagneCollecte($this->getCampagneCollecte());
            $dpeParcours->setVersion('0.1');
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
            $this->dpeParcoursWorkflow->apply($dpeParcours, 'initialiser');
            $this->dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');
            $parcour->addDpeParcour($dpeParcours);

            $this->entityManager->persist($dpeParcours);

            $dpeDemande = new DpeDemande();
            $dpeDemande->setParcours($parcour);
            $dpeDemande->setFormation($formation);
            $dpeDemande->setCampagneCollecte($this->getCampagneCollecte());
            $dpeDemande->setNiveauDemande('P');
            $dpeDemande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $dpeDemande->setArgumentaireDemande('Création d\'un nouveau parcours');
            $dpeDemande->setNiveauModification(TypeModificationDpeEnum::CREATION);
            $dpeDemande->setAuteur($this->getUser());

            $this->entityManager->persist($dpeDemande);

            $this->entityManager->flush();
            $parcour->addDpeParcour($dpeParcours);
            $parcoursRepository->save($parcour, true);
            $respParcoursProfil = $profRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
            if (null !== $respParcoursProfil && $parcour->getRespParcours() !== null) {
                $event = new AddCentreParcoursEvent($parcour, $parcour->getRespParcours(), $respParcoursProfil, $this->getCampagneCollecte());
                $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
            }

            if ($parcour->getCoResponsable() !== null) {
                $coRespParcoursProfil = $profRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);
                if (null !== $coRespParcoursProfil) {
                    $event = new AddCentreParcoursEvent($parcour, $parcour->getCoResponsable(), $coRespParcoursProfil, $this->getCampagneCollecte());
                    $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
                }
            }

            $this->addFlashBag('success', 'Le parcours a été créé');

            return $this->json(true);
        }

        return $this->render('parcours/new.html.twig', [
            'parcour' => $parcour,
            'form' => $form->createView(),
            'parent' => $parent,
            'texte' => $parent ? 'option' : 'parcours',
        ]);
    }

    #[Route('/edit/modal/{parcours}', name: 'app_parcours_edit_modal', methods: ['GET', 'POST'])]
    public function editModal(
        ProfilRepository $profRepository,
        EventDispatcherInterface $eventDispatcher,
        Request                  $request,
        EntityManagerInterface   $entityManager,
        ParcoursRepository       $parcoursRepository,
        Parcours                 $parcours
    ): Response {
        $form = $this->createForm(ParcoursType::class, $parcours, [
            'formation' => $parcours->getFormation(),
            'action' => $this->generateUrl('app_parcours_edit_modal', [
                'parcours' => $parcours->getId(),
            ]),
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $parent = $parcours->getParcoursParent();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSets();
            $changeSet = $uow->getEntityChangeSet($parcours);

            if (isset($changeSet['respParcours'])) {
                $profil = $profRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
                if (null === $profil) {
                    throw $this->createNotFoundException('Profil non existant : ROLE_RESP_PARCOURS');
                }
                // retirer l'ancien resp des centres et droits et envoyer mail
                $event = new AddCentreParcoursEvent($parcours, $changeSet['respParcours'][0], $profil, $this->getCampagneCollecte());
                $eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                // ajouter le nouveau resp, ajouter centre et droits et envoyer mail
                $event = new AddCentreParcoursEvent($parcours, $changeSet['respParcours'][1], $profil, $this->getCampagneCollecte());
                $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
            }

            $logoFiles = $form->get('logo')->getData();
            if ($logoFiles) {
                $hasFormatError = false;
                $hasSizeError = false;
                foreach ($logoFiles as $logoFile) {
                    try {
                        $uploaded = $this->secureUploadService->upload($logoFile, 'logos');
                        $logos = $parcours->getLogo() ?? [];
                        $logos[] = $uploaded->getStoredFilename();
                        $parcours->setLogo($logos);
                    } catch (FileUploadException $e) {
                        if (str_contains($e->getMessage(), 'volumineux')) {
                            $hasSizeError = true;
                        } else {
                            $hasFormatError = true;
                        }
                    }
                }
                if ($hasSizeError) {
                    $this->addFlashBag(Constantes::FLASHBAG_ERROR, 'Un ou plusieurs fichier(s) est/sont trop lourd(s) (10 Mo max)');
                }
                if ($hasFormatError) {
                    $this->addFlashBag(Constantes::FLASHBAG_ERROR, 'Un ou plusieurs fichier(s) n\'est/ne sont pas au bon format (PNG/JPEG/JPG uniquement)');
                }
            }
            $parcoursRepository->save($parcours, true);

            return $this->json(true);
        }

        return $this->render('parcours/new.html.twig', [
            'parcour' => $parcours,
            'form' => $form->createView(),
            'texte' => $parent ? 'option' : 'parcours',
        ]);
    }

    #[Route('/{id}', name: 'app_parcours_show', methods: ['GET'])]
    public function show(
        Parcours            $parcours,
        LheoXML             $lheoXML,
        VersioningParcours $versioningParcours,
        VersioningFormation $versioningFormation,
        EntityManagerInterface $entityManager,
    ): Response {
        $formation = $parcours->getFormation();
        if ($formation === null) {
            throw $this->createNotFoundException();
        }
        $typeDiplome = $formation->getTypeDiplome();
        if ($typeDiplome === null) {
            throw $this->createNotFoundException();
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);

        // Entre versions JSON
        $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours);
        $textDifferencesFormation = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
        // Entre N et N+1
        $textDiffParcoursCampagne = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours, true);
        $textDiffFormationCampagne = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation, true);
        $hasLastVersionCampagne = count($textDiffParcoursCampagne) > 0 || count($textDiffFormationCampagne) > 0;
        // Si l'utilisateur peut voir les différences
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        $canSeeDifferences = $this->isGranted('RELATED_TO_PARCOURS', $parcours);

        $version = $versioningParcours->hasLastVersion($parcours);

        $cssDiff = DiffHelper::getStyleSheet();

        // Afficher les comparaisons directement
        $request = Request::createFromGlobals();
        $displayComparaison = $request->query->get('optionDisplay', 'false');

        // Ordre des semestres manquants
        $missingSemestre = [];

        // Si le parcours est en alternance sans les premiers semestres
        // on met un lien vers le parcours de base
        $parcoursDeBase = null;
        if($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_ALTERNANCE
            && $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT'
        ) {
            $parcoursDeBase = $entityManager->getRepository(Parcours::class)
                ->findParcoursDeBaseAlternance(
                    $parcours->getLibelle(),
                    GetDpeParcours::getFromParcours($parcours)?->getCampagneCollecte()?->getId()
                );
            $parcoursDeBase = count($parcoursDeBase) > 0 ? $parcoursDeBase[0] : null;

            $missingSemestre = $entityManager->getRepository(Parcours::class)
            ->findParcoursAlternanceHasMissingSemestre($parcours);
        }

        return $this->render('parcours/show.html.twig', [
            'parcours' => $parcours,
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'formation' => $formation,
            'typeDiplome' => $typeDiplome,
            'hasParcours' => $formation->isHasParcours(),
            'typeD' => $typeD,
            'lheoXML' => $lheoXML,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'stringDifferencesFormation' => $textDifferencesFormation,
            'stringDifferencesParcoursCampagne' => $textDiffParcoursCampagne,
            'stringDifferencesFormationCampagne' => $textDiffFormationCampagne,
            'hasLastVersion' => $versioningParcours->hasLastVersion($parcours) || $hasLastVersionCampagne,
            'cssDiff' => $cssDiff,
            'version' => $version,
            'parcoursDeBase' => $parcoursDeBase,
            'missingSemestre' => $missingSemestre,
            'displayComparaison' => $displayComparaison,
            'canSeeDifferences' => $canSeeDifferences
        ]);
    }

    #[Route('/{id}/edit', name: 'app_parcours_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request             $request,
        ParcoursState       $parcoursState,
        Parcours            $parcour,
        VersioningParcours $versioningParcours,
    ): Response {

        $request->getSession()->set('semestreAffiche', $request->query->get('semestre') ?? null);
        $request->getSession()->set('ueAffichee', $request->query->get('ue') ?? null);

        $dpeParcours = GetDpeParcours::getFromParcours($parcour);

        if ($dpeParcours === null) {
            throw $this->createNotFoundException();
        }

        $canSeeDifferences = false;

        if (!(
            $this->isGranted('EDIT', ['route' => 'app_parcours', 'subject' => $dpeParcours->getParcours()]) ||
            $this->isGranted('EDIT', ['route' => 'app_formation', 'subject' => $dpeParcours])
        )) {
            return $this->redirectToRoute('app_parcours_show', ['id' => $parcour->getId()]);
        }

        $canSeeDifferences = true;
        $version = $versioningParcours->hasLastVersion($parcour);

        $parcoursState->setParcours($parcour);
        $typeDiplome = $parcour->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw $this->createNotFoundException('Type de diplôme non trouvé pour le parcours.');
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);
        return $this->render('parcours/edit.html.twig', [
            'dpeParcours' => $dpeParcours,
            'parcours' => $parcour,
            'typeDiplome' => $typeDiplome,
            'typeD' => $typeD,
            'formation' => $parcour->getFormation(),
            'parcoursState' => $parcoursState,
            'step' => $request->query->get('step') ?? 0,
            'version' => $version,
            'canSeeDifferences' => $canSeeDifferences
        ]);
    }

    #[Route('/{id}/dupliquer/modal', name: 'app_parcours_dupliquer_modal', methods: ['GET'])]
    public function dupliquerModal(
        EntityManagerInterface $entityManager,
        Parcours               $parcour,
    ): Response {
        return $this->render('parcours/_dupliquer.html.twig', [
            'parcours' => $parcour,
        ]);
    }

    #[Route('/{id}/dupliquer', name: 'app_parcours_dupliquer', methods: ['POST'])]
    public function dupliquer(
        Request           $request,
        ParcoursDupliquer $parcoursDupliquer,
        Parcours          $parcours,
    ): Response {
        $typeDuplication = JsonRequest::getValueFromRequest($request, 'dupliquer');

        if ($typeDuplication === 'recopie') {
            return $parcoursDupliquer->recopie($parcours, $this->getCampagneCollecte());
        }

        if ($typeDuplication === 'mutualise') {
            // return $parcoursDupliquer->recopieAvecMutualise($parcours);
            return JsonReponse::error('Mutualisation non implémentée');
        }

        return JsonReponse::error('Modalite de recopie inconnue');
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_parcours_delete', methods: ['DELETE'])]
    public function delete(
        ElementConstitutifRepository $elementConstitutifRepository,
        EntityManagerInterface       $entityManager,
        Request                      $request,
        Parcours                     $parcour,
        ParcoursRepository           $parcoursRepository
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $parcour->getId(), JsonRequest::getValueFromRequest($request, 'csrf'))) {
            foreach ($parcour->getSemestreParcours() as $sp) {
                $entityManager->remove($sp);
            }

            foreach ($parcour->getSemestreParcours() as $semestreParcour) {
                if ($semestreParcour->getSemestre()?->isTroncCommun() === false) {
                    //todo: supprimer le tronc commun s'il n'est plus utilisé
                    foreach ($semestreParcour->getSemestre()?->getUes() as $ue) {
                        foreach ($ue->getElementConstitutifs() as $ec) {
                            $entityManager->remove($ec);
                        }
                        $entityManager->remove($ue);
                    }
                    //todo: supprimer le semestre s'il n'est plus utilisé
                    $entityManager->remove($semestreParcour->getSemestre());
                    $entityManager->remove($semestreParcour);
                }

                foreach ($parcour->getFicheMatieres() as $ficheMatiere) {
                    //todo: gérer si la fiche est mutualisée
                    $entityManager->remove($ficheMatiere);
                }
            }

            $ecs = $elementConstitutifRepository->findByParcours($parcour);
            foreach ($ecs as $ec) {
                foreach ($ec->getEcEnfants() as $ece) {
                    $entityManager->remove($ece);
                }

                $entityManager->remove($ec);
            }

            foreach ($parcour->getFicheMatiereParcours() as $ficheMatiereParcour) {
                $entityManager->remove($ficheMatiereParcour);
            }

            foreach ($parcour->getSemestreMutualisables() as $semestreMutualisable) {
                $entityManager->remove($semestreMutualisable);
            }

            foreach ($parcour->getUeMutualisables() as $ueMutualisable) {
                $entityManager->remove($ueMutualisable);
            }

            foreach ($parcour->getDpeParcours() as $dpe) {
                $entityManager->remove($dpe);
            }

            $parcoursRepository->remove($parcour, true);

            return $this->json(true);
        }

        return $this->json(false);
    }

    #[Route('/{parcours}/maquette_iframe', name: 'app_parcours_maquette_iframe')]
    public function getMaquetteIframe(
        VersioningParcours $versioningParcours,
        Parcours                     $parcours,
        EntityManagerInterface       $em,
    ): Response {

        return $this->getValidatedMaquetteIframe($parcours, $versioningParcours, $em);
    }

    #[Route('/{parcours}/versioning/maquette_iframe', 'app_versioning_parcours_maquette_iframe')]
    public function getValidatedMaquetteIframe(
        Parcours $parcours,
        VersioningParcours $versioningParcours,
        EntityManagerInterface $entityManager
    ) : Response {
        $lastVersion = $entityManager
            ->getRepository(ParcoursVersioning::class)
            ->findLastCfvuVersion($parcours)[0] ?? false;

        if($lastVersion) {
            $dto = $versioningParcours->loadParcoursFromVersion($lastVersion)['dto'];

            $ficheMatiereRepo = $entityManager->getRepository(FicheMatiere::class);

            return $this->render('parcours/maquette_iframe.html.twig', [
                'parcours' => $dto,
                'parcoursData' => $parcours,
                'ficheMatiereRepo' => $ficheMatiereRepo,
                'isVersioning' => true
            ]);
        }

        return $this->render('parcours/maquette_iframe.html.twig', [
            'parcours' => false
        ]);
    }

    #[Route('/{parcours}/export-xml-lheo', name: 'app_parcours_export_xml_lheo')]
    public function getXmlLheoFromParcours(Parcours $parcours, LheoXML $lheoXML): Response
    {
        $xml = $lheoXML->generateLheoXMLFromParcours($parcours, true);
        // Validation
        libxml_use_internal_errors(true);
        $isValid = $lheoXML->validateLheoSchema($xml);
        $xml_errors = [];
        if (!$isValid) {
            foreach (libxml_get_errors() as $error) {
                $xml_errors[] = $lheoXML->decodeErrorMessages($error->message);
            }
        }
        libxml_clear_errors();
        // Si le XML généré est valide, on le renvoie
        if ($isValid) {
            return new Response($xml, 200, ['Content-Type' => 'application/xml']);
        } // Sinon, on avertit le client
        else {
            return $this->render('lheo/error.html.twig', [
                'errors' => $xml_errors
            ]);
        }
    }

    #[Route('/{parcours}/export-json-urca', name: 'app_parcours_export_json_urca')]
    public function getJsonExportUrca(
        Parcours            $parcours,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw $this->createNotFoundException('Type de diplôme non trouvé pour le parcours.');
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);

        $ects = $typeD->calculStructureParcours($parcours)->heuresEctsFormation->sommeFormationEcts;

        // Gestion de la localisation
        // Vide par défaut : -
        $localisationMetadata = ["-"];
        // Si l'on a une ville sur le parcours
        if($parcours->getLocalisation()?->getLibelle() !== null) {
            $localisationMetadata = [$parcours->getLocalisation()?->getLibelle()];
        }
        // Sinon on prend au niveau de la composante
        else {
            $villeArray = $parcours->getFormation()?->getLocalisationMention()?->toArray();
            if(count($villeArray) > 0) {
                $localisationMetadata = array_map(
                    fn ($ville) => $ville->getLibelle(),
                    $villeArray
                );
            }
        }

        // Gestion de 'faculte-ecole-institut'
        // ---> Il peut y avoir plusieurs composantes d'inscription au niveau de la formation
        // "-" par défaut
        $faculteEcoleInstitut = ["-"];
        // Si on a au niveau du parcours
        if($parcours->getComposanteInscription()?->getLibelle() !== null) {
            if ($parcours->getComposanteInscription()?->getComposanteParent() === null) {
                $faculteEcoleInstitut = [$parcours->getComposanteInscription()?->getLibelle()];
            } else {
                $faculteEcoleInstitut = [$parcours->getComposanteInscription()?->getComposanteParent()?->getLibelle()];
            }
        }
        // Sinon, on prend les composantes d'inscription de la formation
        else {
            if(count($parcours->getFormation()?->getComposantesInscription()->toArray()) > 0) {
                $faculteEcoleInstitut = array_map(
                    fn ($composanteInscription) => $composanteInscription->getLibelle(),
                    $parcours->getFormation()?->getComposantesInscription()->toArray()
                );
            }
        }

        $typeF = [];
        $typeF[] = $typeDiplome?->getLibelle() ?? '-';

        $lasTypeP = [
            TypeParcoursEnum::TYPE_PARCOURS_LAS1,
            TypeParcoursEnum::TYPE_PARCOURS_LAS23,
            TypeParcoursEnum::TYPE_PARCOURS_LAS123
        ];

        $isDiplomeInge = in_array($typeDiplome?->getLibelleCourt() ?? '-', ['DI', 'CMI', 'CPI'], true);

        if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_CPI || $isDiplomeInge) {
            $typeF[] = "Diplôme d’ingénieur / CMI / CPI";
        } elseif (in_array($parcours->getTypeParcours(), $lasTypeP, true)) {
            $typeF[] = 'Licence Accès Santé';
        }

        $data = [
            'description' => "",
            'ects' => $ects ?? 0,
            'metadata' => [
                'domaine' => $parcours->getFormation()?->getDomaine()?->getLibelle() ?? '-',
                'type-formation' => $typeF,
                'localisation' => $localisationMetadata,
                'faculte-ecole-institut' => $faculteEcoleInstitut,
                'public-concerne' => $parcours->getRegimeInscription() ?? [], //Certains sont des tableaux, d'autres en JSON
                'niveau-francais' => $parcours->getNiveauFrancais()?->libelle() ?? '-',
            ],
            'xml-lheo' => $this->generateUrl('app_parcours_export_xml_lheo', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'fiche-pdf' => $this->generateUrl('app_parcours_export', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'maquette-pdf' => $this->generateUrl('app_parcours_mccc_export', ['parcours' => $parcours->getId(), '_format' => 'pdf'], UrlGenerator::ABSOLUTE_URL),
            'maquette-json' => $this->generateUrl('app_parcours_export_maquette_json', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return new JsonResponse($data);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{parcours}/versioning/json_data', name: 'app_parcours_versioning_json_data')]
    public function displayParcoursJsonData(Parcours $parcours): Response
    {
        // Définition du serializer
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $serializer = new Serializer(
            [
            new DateTimeNormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer($classMetadataFactory, propertyTypeExtractor: new ReflectionExtractor()),
        ],
            [new JsonEncoder()]
        );
        try {
            // Création de la réponse JSON au client
            $json = $serializer->serialize($parcours, 'json', [
                AbstractObjectNormalizer::GROUPS => ['parcours_json_versioning'],
                'circular_reference_limit' => 2,
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
            ]);
            return new Response($json, 200, ['Content-Type' => 'application/json']);
        } catch (Exception $e) {
            // Si erreur lors de la serialization
            return new Response(json_encode([
                'error' => 'Une erreur interne est survenue.',
                'message' => "{$e->getMessage()}",
            ]), 422, ['Content-Type' => 'application/json']);
        }
    }

    #[IsGranted('ROLE_SES')]
    #[Route('/{parcours}/versioning/save', name: 'app_parcours_versioning_save')]
    public function saveParcoursIntoJson(
        Parcours               $parcours,
        Filesystem             $fileSystem,
        VersioningParcours $versioningParcours
    ): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            $now = new DateTimeImmutable('now');
            $dateHeure = $now->format('d-m-Y_H-i-s');
            $versioningParcours->saveVersionOfParcours($parcours, $now, true);

            /** @var User $user */
            $user = $this->getUser();
            $successLogTxt = "[{$dateHeure}] Le parcours {$parcours->getId()} a été versionné avec succès. ";
            $successLogTxt .= "Utilisateur : {$user->getPrenom()} {$user->getNom()} ({$user->getUsername()})\n";
            $fileSystem->appendToFile(__DIR__ . "/../../versioning_json/success_log/save_parcours_success.log", $successLogTxt);
            // Message de réussite + redirection
            $this->addFlashBag('success', 'La version du parcours à bien été sauvegardée.');
            if($parcours->isParcoursDefaut() === false) {
                return $this->redirectToRoute('app_parcours_show', ['id' => $parcours->getId()]);
            }
            return $this->redirectToRoute('app_formation_show', ['slug' => $parcours->getFormation()->getSlug()]);
        } catch (Exception $e) {
            // Log error
            $logTxt = "[{$dateHeure}] Le versioning du parcours : {$parcours->getId()} a rencontré une erreur.\n{$e->getMessage()}\n";
            $fileSystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/save_parcours_error.log", $logTxt);

            $this->addFlashBag('error', "Une erreur est survenue lors de la sauvegarde.");
            return $this->redirectToRoute('app_parcours_show', ['id' => $parcours->getId()]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{parcours_versioning}/versioning/view', name: 'app_parcours_versioning_view')]
    public function parcoursVersion(
        ParcoursVersioning $parcours_versioning,
        Filesystem $fileSystem,
        VersioningParcours $versioningParcours,
    ): Response {
        try {
            $loadedVersion = $versioningParcours->loadParcoursFromVersion($parcours_versioning);

            return $this->render('parcours/show_version.html.twig', [
                'parcours' => $loadedVersion['parcours'],
                'formation' => $loadedVersion['parcours']->getFormation(),
                'typeDiplome' => $loadedVersion['parcours']->getTypeDiplome(),
                'typeD' => $this->typeDiplomeResolver->get($loadedVersion['parcours']->getTypeDiplome()),
                'dto' => $loadedVersion['dto'],
                'hasParcours' => $loadedVersion['parcours']->getFormation()->isHasParcours(),
                // 'isBut' => $loadedVersion['parcours']->getTypeDiplome()->getLibelleCourt() === 'BUT',
                'dateVersion' => $loadedVersion['dateVersion'],
                'isVersioning' => true,
                'parcoursVersioning' => $parcours_versioning,
                'diffStructureCampagne' => null
            ]);
        } catch (Exception $e) {
            $now = new DateTimeImmutable('now');
            $dateHeure = $now->format('d-m-Y_H-i-s');
            // Log error
            $logTxt = "[{$dateHeure}] La visualisation de la version : {$parcours_versioning->getId()} a rencontré une erreur.\n{$e->getMessage()}\n";
            $fileSystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/view_parcours_error.log", $logTxt);

            $this->addFlashBag('error', "La visualisation de la version a rencontré une erreur.");
            return $this->redirectToRoute('app_parcours_show', ['id' => $parcours_versioning->getParcours()?->getId()]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/check/lheo_invalid_list', name: 'app_parcours_lheo_invalid_list')]
    public function getInvalidXmlLheoList(
        LheoXML            $lheoXML,
        ParcoursRepository $parcoursRepo
    ): Response {
//        $parcoursList = [
//            ...$parcoursRepo->findByTypeValidation($this->getCampagneCollecte(), 'valide_pour_publication'),
//            ...$parcoursRepo->findByTypeValidation($this->getCampagneCollecte(), 'publie'),
//            ...$parcoursRepo->findByTypeValidation($this->getCampagneCollecte(), 'valide_a_publier')
//        ];

        $parcoursList = $parcoursRepo->findAllByCampagneCollecte($this->getCampagneCollecte());

        $errorArray = [];
        foreach ($parcoursList as $p) {
            $erreursChampsParcours = $lheoXML->checkTextValuesAreLongEnough($p);
            if ($lheoXML->isValidLHEO($p) === false || count($erreursChampsParcours) > 0) {
                $xmlErrorArray = [];
                foreach (libxml_get_errors() as $xmlError) {
                    $xmlErrorArray[] = $lheoXML->decodeErrorMessages($xmlError->message);
                }
                $xmlErrorArray = array_merge($xmlErrorArray, $erreursChampsParcours);
                $errorArray[] = [
                    'id' => $p->getId(),
                    'parcours_libelle' => $p->getLibelle(),
                    'etatParcours' => GetDpeParcours::getFromParcours($p)?->getEtatValidation(),
                    'formation_libelle' => $p->getFormation()?->getMention()?->getLibelle(),
                    'type_formation_libelle' => $p->getFormation()?->getTypeDiplome()?->getLibelle(),
                    'xml_errors' => $xmlErrorArray
                ];
                libxml_clear_errors();
            }
        }

        return $this->render('lheo/list.html.twig', [
            'errorArray' => $errorArray
        ]);
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/{idParcours}/mccc/export/pdf', 'app_parcours_valid_mccc_pdf_export')]
    public function getMcccAsPdfForParcours(
        int $idParcours,
        EntityManagerInterface $entityManager
    ) : Response|JsonResponse {

        if(is_integer($idParcours) === false) {
            throw $this->createNotFoundException();
        }

        $dpe = $entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1]);
        if ($dpe === null) {
            return new Response(null, 500);
        }
        $annee = $dpe->getAnnee();

        try {
            $file = file_get_contents(__DIR__ . "/../../mccc-export/MCCC-Parcours-{$idParcours}-{$annee}.pdf");
            if($file) {
                return new Response(
                    $file,
                    200,
                    [
                        'Content-Type' => 'application/pdf'
                    ]
                );
            }
        } catch (Exception $error) {
            return new Response(
                json_encode(
                    ['error' => "Le parcours n'a pas été trouvé"]
                ),
                404,
                [
                    'Content-Type' => 'application/json'
                ]
            ) ;
        }
    }

    #[Route('/{parcours}/export-json-urca/cfvu_valid', name: 'app_parcours_export_json_urca_cfvu_valid')]
    public function getJsonExportUrcaCfvuValid(
        Parcours $parcours,
        EntityManagerInterface $entityManager,
        VersioningParcours $versioningParcours
    ): Response {

        $parcoursVersion = $entityManager
            ->getRepository(ParcoursVersioning::class)
            ->findLastCfvuVersion($parcours);
        if(count($parcoursVersion) === 0) {
            throw $this->createNotFoundException('Version not found.');
        }

        $parcoursVersion = $parcoursVersion[0];

        $versionData = $versioningParcours->loadParcoursFromVersion($parcoursVersion);
        $parcoursVersionData = $versionData['parcours'];
        $dtoVersionData = $versionData['dto'];


        $typeDiplome = $parcoursVersionData->getFormation()?->getTypeDiplome();
        $ects = $dtoVersionData->heuresEctsFormation->sommeFormationEcts;

        // Gestion de la localisation
        // Vide par défaut : -
        $localisationMetadata = ["-"];
        // Si l'on a une ville sur le parcours
        if($parcoursVersionData->getLocalisation()?->getLibelle() !== null) {
            $localisationMetadata = [$parcoursVersionData->getLocalisation()?->getLibelle()];
        }
        // Sinon on prend au niveau de la composante
        else {
            $villeArray = $parcoursVersionData->getFormation()?->getLocalisationMention()?->toArray();
            if(count($villeArray) > 0) {
                $localisationMetadata = array_map(
                    fn ($ville) => $ville->getLibelle(),
                    $villeArray
                );
            }
        }

        // Gestion de 'faculte-ecole-institut'
        // ---> Il peut y avoir plusieurs composantes d'inscription au niveau de la formation
        // "-" par défaut
        $faculteEcoleInstitut = ["-"];
        // Si on a au niveau du parcours
        if($parcoursVersionData->getComposanteInscription()?->getLibelle() !== null) {

            $composanteInscriptionParent = $entityManager->getRepository(Parcours::class)
                ->findOneById($parcoursVersion->getParcours()->getId())
                ->getComposanteInscription()
                ?->getComposanteParent();

            if ($composanteInscriptionParent === null) {
                $faculteEcoleInstitut = [$parcoursVersionData->getComposanteInscription()?->getLibelle()];
            } else {
                $faculteEcoleInstitut = [$composanteInscriptionParent->getLibelle()];
            }
        }
        // Sinon, on prend les composantes d'inscription de la formation
        else {
            if(count($parcoursVersionData->getFormation()?->getComposantesInscription()->toArray()) > 0) {
                $faculteEcoleInstitut = array_map(
                    fn ($composanteInscription) => $composanteInscription->getLibelle(),
                    $parcoursVersionData->getFormation()?->getComposantesInscription()->toArray()
                );
            }
        }

        $typeF = [];
        $typeF[] = $typeDiplome?->getLibelle() ?? '-';

        $typeParcours = $entityManager->getRepository(Parcours::class)
            ->findOneById($parcoursVersion->getParcours()->getId())
            ->getTypeParcours();

        $lasTypeP = [
            TypeParcoursEnum::TYPE_PARCOURS_LAS1,
            TypeParcoursEnum::TYPE_PARCOURS_LAS23,
            TypeParcoursEnum::TYPE_PARCOURS_LAS123
        ];

        $isDiplomeInge = in_array($typeDiplome?->getLibelleCourt() ?? '-', ['DI', 'CMI', 'CPI'], true);

        if ($typeParcours === TypeParcoursEnum::TYPE_PARCOURS_CPI || $isDiplomeInge) {
            $typeF[] = "Diplôme d’ingénieur / CMI / CPI";
        } elseif (in_array($typeParcours, $lasTypeP, true)) {
            $typeF[] = 'Licence Accès Santé';
        }

        $data = [
            'description' => "",
            'ects' => $ects ?? 0,
            'metadata' => [
                'domaine' => $parcoursVersionData->getFormation()?->getDomaine()?->getLibelle() ?? '-',
                'type-formation' => $typeF,
                'localisation' => $localisationMetadata,
                'faculte-ecole-institut' => $faculteEcoleInstitut,
                'public-concerne' => $parcoursVersionData->getRegimeInscription() ?? [], //Certains sont des tableaux, d'autres en JSON
                'niveau-francais' => $parcoursVersionData->getNiveauFrancais()?->libelle() ?? '-',
            ],
            'xml-lheo' => $this->generateUrl('app_parcours_export_xml_lheo', ['parcours' => $parcoursVersion->getParcours()->getId()], UrlGenerator::ABSOLUTE_URL),
            'fiche-pdf' => $this->generateUrl('app_parcours_export_pdf_versioning', ['parcours' => $parcours->getId()], UrlGenerator::ABSOLUTE_URL),
            'maquette-pdf' => $this->generateUrl('app_parcours_mccc_export_cfvu_valid', ['parcours' => $parcoursVersion->getParcours()->getId(), 'format' => 'simplifie'], UrlGenerator::ABSOLUTE_URL),
            'maquette-json' => $this->generateUrl('app_parcours_export_maquette_json_validee_cfvu', ['parcours' => $parcoursVersion->getParcours()->getId()], UrlGenerator::ABSOLUTE_URL),
        ];

        return new JsonResponse($data);
    }

    #[Route('/{parcours}/annee-suivante-light/export-json-urca', name: 'app_parcours_export_json_urca_annee_suivante_light')]
    public function getJsonExportUrcaAnneeSuivanteLight(
        Parcours            $parcours,
        EntityManagerInterface $entityManager
    ): Response {
        $optionsArray = GetDpeParcours::getFromParcours($parcours)
            ->getCampagneCollecte()
            ->getPublicationOptions() ?? [];

        // Pas de PDF pour N+1
        $urlMaquettePdf = "#";

        // Afficher la maquette de N pour N+1
        // S'il n'y a pas de parcours précédent
        // envoyer un json minimaliste
        $parcoursOrigineVersion = [];
        if($parcours->getParcoursOrigineCopie()){
            $parcoursOrigineVersion = $entityManager->getRepository(ParcoursVersioning::class)
                ->findLastCfvuVersion($parcours->getParcoursOrigineCopie());
        }
        if($parcours->getParcoursOrigineCopie()?->getId() && count($parcoursOrigineVersion) > 0){
            $urlMaquetteJson = $this->generateUrl(
                    'app_parcours_export_maquette_json_validee_cfvu',
                ['parcours' => $parcours->getParcoursOrigineCopie()->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
            );
        }
        else {
            $urlMaquetteJson = $this->generateUrl(
                'app_parcours_export_maquette_json_minimum',
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if ($typeDiplome === null) {
            throw $this->createNotFoundException('Type de diplôme non trouvé pour le parcours.');
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);

        $ects = 0;
        if(isset($typeD->calculStructureParcours($parcours)->heuresEctsFormation->sommeFormationEcts)){
            $ects = $typeD->calculStructureParcours($parcours)->heuresEctsFormation->sommeFormationEcts;
        }

        // Gestion de la localisation
        // Vide par défaut : -
        $localisationMetadata = ["-"];
        // Si l'on a une ville sur le parcours
        if($parcours->getLocalisation()?->getLibelle() !== null) {
            $localisationMetadata = [$parcours->getLocalisation()?->getLibelle()];
        }
        // Sinon on prend au niveau de la composante
        else {
            $villeArray = $parcours->getFormation()?->getLocalisationMention()?->toArray();
            if(count($villeArray) > 0) {
                $localisationMetadata = array_map(
                    fn ($ville) => $ville->getLibelle(),
                    $villeArray
                );
            }
        }

        // Gestion de 'faculte-ecole-institut'
        // ---> Il peut y avoir plusieurs composantes d'inscription au niveau de la formation
        // "-" par défaut
        $faculteEcoleInstitut = ["-"];
        // Si on a au niveau du parcours
        if($parcours->getComposanteInscription()?->getLibelle() !== null) {
            if ($parcours->getComposanteInscription()?->getComposanteParent() === null) {
                $faculteEcoleInstitut = [$parcours->getComposanteInscription()?->getLibelle()];
            } else {
                $faculteEcoleInstitut = [$parcours->getComposanteInscription()?->getComposanteParent()?->getLibelle()];
            }
        }
        // Sinon, on prend les composantes d'inscription de la formation
        else {
            if(count($parcours->getFormation()?->getComposantesInscription()->toArray()) > 0) {
                $faculteEcoleInstitut = array_map(
                    fn ($composanteInscription) => $composanteInscription->getLibelle(),
                    $parcours->getFormation()?->getComposantesInscription()->toArray()
                );
            }
        }

        $typeF = [];
        $typeF[] = $typeDiplome?->getLibelle() ?? '-';

        $lasTypeP = [
            TypeParcoursEnum::TYPE_PARCOURS_LAS1,
            TypeParcoursEnum::TYPE_PARCOURS_LAS23,
            TypeParcoursEnum::TYPE_PARCOURS_LAS123
        ];

        $isDiplomeInge = in_array($typeDiplome?->getLibelleCourt() ?? '-', ['DI', 'CMI', 'CPI'], true);

        if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_CPI || $isDiplomeInge) {
            $typeF[] = "Diplôme d’ingénieur / CMI / CPI";
        } elseif (in_array($parcours->getTypeParcours(), $lasTypeP, true)) {
            $typeF[] = 'Licence Accès Santé';
        }

        $data = [
            'description' => "",
            'ects' => $ects ?? 0,
            'metadata' => [
                'domaine' => $parcours->getFormation()?->getDomaine()?->getLibelle() ?? '-',
                'type-formation' => $typeF,
                'localisation' => $localisationMetadata,
                'faculte-ecole-institut' => $faculteEcoleInstitut,
                'public-concerne' => $parcours->getRegimeInscription() ?? [], //Certains sont des tableaux, d'autres en JSON
                'niveau-francais' => $parcours->getNiveauFrancais()?->libelle() ?? '-',
            ],
            'xml-lheo' => $this->generateUrl('app_parcours_export_xml_lheo', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'fiche-pdf' => $this->generateUrl('app_parcours_export', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'maquette-pdf' => $urlMaquettePdf,
            'maquette-json' => $urlMaquetteJson,
        ];

        return new JsonResponse($data);
    }

    #[Route('/{id}/upload-logo', name: 'app_parcours_upload_logo', methods: ['POST'])]
    public function uploadLogo(Request $request, Parcours $parcours): JsonResponse
    {
        $this->denyAccessUnlessGranted('EDIT', ['route' => 'app_parcours', 'subject' => $parcours]);

        $files = $request->files->get('logo');

        if (!$files) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun fichier reçu'], 400);
        }

        $hasFormatError = false;
        $hasSizeError = false;

        foreach ($files as $file) {
            try {
                $uploaded = $this->secureUploadService->upload($file, 'logos');
                $logos = $parcours->getLogo() ?? [];
                $logos[] = $uploaded->getStoredFilename();
                $parcours->setLogo($logos);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'volumineux')) {
                    $hasSizeError = true;
                } else {
                    $hasFormatError = true;
                }
            }
        }

        if ($hasFormatError || $hasSizeError) {
            $errors = [];
            if ($hasSizeError) $errors[] = 'Un ou plusieurs fichier(s) est/sont trop lourd(s) (10 Mo max)';
            if ($hasFormatError) $errors[] = 'Un ou plusieurs fichier(s) n\'est/ne sont pas au bon format (PNG/JPEG/JPG uniquement)';
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/logos', name: 'app_parcours_logos', methods: ['GET'])]
    public function logos(Parcours $parcours, Request $request): Response
    {
        return $this->render('parcours/_logos.html.twig', [
            'parcours' => $parcours,
            'editable' => $request->query->getBoolean('editable'),
        ]);
    }

    #[Route('/{id}/delete-logo', name: 'app_parcours_delete_logo', methods: ['DELETE'])]
    public function deleteLogo(Request $request, Parcours $parcours): JsonResponse
    {
        $this->denyAccessUnlessGranted('EDIT', ['route' => 'app_parcours', 'subject' => $parcours]);

        $data = json_decode($request->getContent(), true);
        $filename = $data['filename'] ?? null;

        if (!$filename) {
            return new JsonResponse(['success' => false, 'error' => 'Nom de fichier manquant'], 400);
        }

        $logos = $parcours->getLogo() ?? [];

        if (!in_array($filename, $logos)) {
            return new JsonResponse(['success' => false, 'error' => 'Fichier introuvable'], 404);
        }

        $logos = array_values(array_filter($logos, fn($l) => $l !== $filename));
        $parcours->setLogo($logos);
        $this->entityManager->flush();

        $this->secureUploadService->delete('logos', $filename);

        return new JsonResponse(['success' => true]);
    }


    // Route directe vers un logo pour alimenter l'API
    #[Route('/{id}/logo/{filename}', name: 'app_parcours_logo', methods: ['GET'])]
    public function logo(Parcours $parcours, string $filename): Response
    {
        $filePath = $this->secureUploadService->resolveStoredFilePath('logos', $filename);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($filePath);
    }
}
