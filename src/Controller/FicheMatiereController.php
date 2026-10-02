<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\verif\FicheMatiereState;
use App\DTO\StructureEc;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereVersioning;
use App\Entity\Parcours;
use App\Entity\User;
use App\Form\FicheMatiereType;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\LangueRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\Repository\UeRepository;
use App\Service\VersioningFicheMatiere;
use App\TypeDiplome\McccDisplayInterface;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Jfcherng\Diff\DiffHelper;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\DTO\TranslatableKey;

#[Route('/fiche/matiere')]
class FicheMatiereController extends BaseController
{
    #[Route('/new', name: 'app_fiche_matiere_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        UeRepository $ueRepository,
        EntityManagerInterface $entityManager,
        LangueRepository $langueRepository,
        Request $request,
    ): Response {
        $ficheMatiere = new FicheMatiere();
        $ficheMatiere->setCampagneCollecte($this->getCampagneCollecte());

        if ($request->query->has('ue')) {
            $ue = $ueRepository->find($request->query->get('ue'));
            $ficheMatiere->setParcours($ue->getSemestre()?->getSemestreParcours()->first()->getParcours());
        } else {
            $ue = null;
            $ficheMatiere->setHorsDiplome(true);
        }

        //todo: initialiser les modalités par rapport au parcours

        $form = $this->createForm(FicheMatiereType::class, $ficheMatiere, [
            'action' => $this->generateUrl('app_fiche_matiere_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $langueFr = $langueRepository->findOneBy(['codeIso' => 'fr']);
            if ($langueFr !== null) {
                $ficheMatiere->addLangueDispense($langueFr);
                $langueFr->addFicheMatiere($ficheMatiere);
                $ficheMatiere->addLangueSupport($langueFr);
                $langueFr->addLanguesSupportsFicheMatiere($ficheMatiere);
            }
            $entityManager->persist($ficheMatiere);
            $entityManager->flush();

            return $turboStream->streamToastSuccess(
               'fiche_matiere.new.success', true
            ); //todo: gérer le refresh de la liste
        }


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('fiche_matiere.new.title', [], 'modal'),
            $ficheMatiere->isHorsDiplome() ? 'Fiche matière hors diplôme' : 'Fiche matière de l\'UE ' . $ue?->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{slug}', name: 'app_fiche_matiere_show', methods: ['GET'])]
    public function show(
        ElementConstitutifRepository $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                 $ficheMatiere,
        VersioningFicheMatiere       $ficheMatiereVersioningService,
        TypeDiplomeRepository        $typeDiplomeRepository,
    ): Response {

        $bccs = [];
        foreach ($ficheMatiere->getCompetences() as $competence) {
            if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
            }
            $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
        }

        if ($ficheMatiere->getParcours() !== null) {
            $formation = $ficheMatiere->getParcours()?->getFormation();
            $typeDiplome = $formation->getTypeDiplome();
            $typeD = $this->typeDiplomeResolver->get($typeDiplome);
        } else {
            $typeDiplome = $typeDiplomeRepository->findOneBy(['libelle_court' => 'L']);
        }

        if ($typeDiplome === null) {
            throw new TypeDiplomeNotFoundException();
        }

        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome);

        $cssDiff = DiffHelper::getStyleSheet();
        $textDifferences = $ficheMatiereVersioningService
            ->getStringDifferencesWithBetweenFicheMatiereAndLastVersion($ficheMatiere);

        $ficheMatiereParcours = $ficheMatiereMutualisableRepository->findByFicheMatieres($ficheMatiere);
        $ecParcours = $elementConstitutifRepository->findByFicheMatiereParcours($ficheMatiere);

        if (!$typeD instanceof McccDisplayInterface) {
            throw new RuntimeException('Ce type de diplôme ne prend pas en charge cet affichage MCCC.');
        }

        return $this->render('fiche_matiere/show.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'ficheMatiereParcours' => $ficheMatiereParcours,
            'ecParcours' => $ecParcours,
            'formation' => $formation,
            'typeEpreuves' => $typeD->getTypeEpreuves(),
            'typeD' => $typeD,
            'typeDiplome' => $typeDiplome,
            'ects' => $ficheMatiere->getEcts(),
            'mcccs' => $typeD->getDisplayMccc($typeD->getMcccs($ficheMatiere), $ficheMatiere->getTypeMccc() ?? ''),
            'bccs' => $bccs,
            'typeMccc' => $ficheMatiere->getTypeMccc(),
            'stringDifferences' => $textDifferences,
            'cssDiff' => $cssDiff
        ]);
    }

    #[Route('/{elementConstitutif}/show-parcours', name: 'app_fiche_matiere_detail_parcours', methods: ['GET'])]
    public function showParcours(
        ElementConstitutif $elementConstitutif
    ): Response {

        if ($elementConstitutif->isFicheFromParcours() === true) {
            $competences = $elementConstitutif->getFicheMatiere()->getCompetences();
        } else {
            $competences = $elementConstitutif->getCompetences();
        }

        $bccs = [];
        foreach ($competences as $competence) {
            if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
            }
            $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
        }


        return $this->render('fiche_matiere/_showParcours.html.twig', [
            'ficheMatiere' => $elementConstitutif->getFicheMatiere(),
            'bccs' => $bccs
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_fiche_matiere_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere $ficheMatiere,
        FicheMatiereState $ficheMatiereState,
    ): Response {
        //todo: a revoir...
        if (!($this->isGranted(
                'EDIT',
            [
                'route' => 'app_fiche_matiere',
                'subject' => $ficheMatiere,
            ]
            ) || $this->isGranted(
                'EDIT',
                [
                    'route' => 'app_fiche_matiere',
                    'subject' => $ficheMatiere->getParcours(),
                ]
            )
            || $this->isGranted(
                'EDIT',
                [
                    'route' => 'app_fiche_matiere',
                    'subject' => $ficheMatiere->getParcours()?->getFormation(),
                ]
            ))) {
            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
        }

        if ($ficheMatiere->getParcours() !== null) {
            $dpeParcours = GetDpeParcours::getFromParcours($ficheMatiere->getParcours());
        } else {
            $dpeParcours = null;
        }

        $ficheMatiereState->setFicheMatiere($ficheMatiere);

        $referer = $request->headers->get('referer');

        if ($referer === null || false === str_contains($referer, 'parcours')) {
            $source = 'liste';
        } else {
            $source = 'parcours';
            $link = $referer.'?step=4';
        }
        return $this->render('fiche_matiere/edit.html.twig', [
            'fiche_matiere' => $ficheMatiere,
            'ficheMatiereState' => $ficheMatiereState,
            'source' => $source,
            'dpeParcours' => $dpeParcours,
            'link' => $link ?? null,
        ]);
    }

    #[Route('/{slug}/dupliquer', name: 'app_fiche_matiere_dupliquer', methods: ['POST', 'GET'])]
    public function dupliquer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere $ficheMatiere,
        EntityManagerInterface $entityManager,
    ): Response {
        $newFicheMatiere = clone $ficheMatiere;
        $newFicheMatiere->setFicheMatiereOrigineCopie(null);
        $newFicheMatiere->setLibelle($ficheMatiere->getLibelle() . '-copie');
        $newFicheMatiere->setSlug(null);
        $entityManager->persist($newFicheMatiere);
        $entityManager->flush();

        foreach ($ficheMatiere->getFicheMatiereParcours() as $parcours) {
            //on duplique les parcours de mutualisation
            $newFicheMatiereParcours = clone $parcours;
            $newFicheMatiereParcours->setFicheMatiere($newFicheMatiere);
            $entityManager->persist($newFicheMatiereParcours);
            $entityManager->flush();
        }

        return $this->json(true);
    }

    #[Route('/{slug}', name: 'app_fiche_matiere_delete', methods: ['POST', 'DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere $ficheMatiere,
        FicheMatiereRepository $ficheMatiereRepository
    ): Response {
        $token = $request->request->get('_token')
            ?? $request->request->get('csrf_token')
            ?? JsonRequest::getValueFromRequest($request, 'csrf')
            ?? JsonRequest::getValueFromRequest($request, '_token');

        if ($this->isCsrfTokenValid('delete' . $ficheMatiere->getId(), $token)) {

            if ($ficheMatiere->getElementConstitutifs()->count() > 0) {
                if ($request->isXmlHttpRequest() || str_contains((string)$request->headers->get('Accept'), 'application/json')) {
                    return JsonReponse::error('Impossible de supprimer la fiche matière car elle est utilisée par au moins un élément constitutif.');
                }
                $this->addFlashBag('error', 'Impossible de supprimer la fiche matière car elle est utilisée par au moins un élément constitutif.');
                return $this->redirectToRoute('structure_fiche_matiere_index');
            }

            if ($ficheMatiere->getFicheMatiereParcours()->count() > 0) {
                if ($request->isXmlHttpRequest() || str_contains((string)$request->headers->get('Accept'), 'application/json')) {
                    return JsonReponse::error('Impossible de supprimer la fiche matière car elle est potentiellement mutualisée avec d\'autres parcours.');
                }
                $this->addFlashBag('error', 'Impossible de supprimer la fiche matière car elle est potentiellement mutualisée avec d\'autres parcours.');
                return $this->redirectToRoute('structure_fiche_matiere_index');
            }

            foreach ($ficheMatiere->getMcccs() as $mccc) {
                $ficheMatiere->removeMccc($mccc);
                $entityManager->remove($mccc);
            }

            foreach ($ficheMatiere->getHistoriqueFicheMatieres() as $historiqueFicheMatiere) {
                $ficheMatiere->removeHistoriqueFicheMatiere($historiqueFicheMatiere);
                $entityManager->remove($historiqueFicheMatiere);
            }
            //todo: gérer si champs dans fiche matière copie ?
            $ficheMatiereRepository->remove($ficheMatiere, true);

            if ($request->isXmlHttpRequest() || str_contains((string)$request->headers->get('Accept'), 'application/json')) {
                return JsonReponse::success('La fiche matière a bien été supprimée.');
            }

            $this->addFlashBag('success', 'La fiche matière a bien été supprimée.');
            return $this->redirectToRoute('structure_fiche_matiere_index');
        }

        if ($request->isXmlHttpRequest() || str_contains((string)$request->headers->get('Accept'), 'application/json')) {
            return $this->json(false, 400);
        }

        $this->addFlashBag('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('structure_fiche_matiere_index');
    }

    #[Route('/{ec}/{parcours}/{ects}/maquette_iframe', name: 'app_fiche_matiere_maquette_iframe')]
    public function getMaquetteIframe(ElementConstitutif $ec, Parcours $parcours, float $ects) : Response
    {

        $ficheMatiere = $ec->getFicheMatiere();

        if (null === $ficheMatiere) {
            throw $this->createNotFoundException('Fiche matière non trouvée pour cet élément constitutif.');
        }

        $isBUT = $ficheMatiere->getParcours()?->getTypeDiplome()?->getLibelleCourt() === 'BUT';
        $structureEC = new StructureEc($ec, $parcours, $isBUT, true, false);

        return $this->render('fiche_matiere/maquette_iframe.html.twig', [
            'fiche_matiere' => $ficheMatiere,
            'typeDiplome' => $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome(),
            'formation' => $ficheMatiere->getParcours()?->getFormation(),
            'maquetteOrigineURL' => $parcours ? $this->generateUrl('app_parcours_maquette_iframe', ['parcours' => $parcours->getId()]) : "#",
            'heuresEctsEc' => $structureEC->heuresEctsEc,
            'ects' => $ects,
            'isBUT' => $isBUT
        ]);
    }

    #[Route('/versioning/{volCmPres}/{volTdPres}/{volTpPres}/{volCmDist}/{volTdDist}/{volTpDist}/{volTe}/{parcours}/{ects}/{slug}/maquette_iframe', name: 'app_fiche_matiere_versioning_maquette_iframe')]
    public function getMaquetteIframeVersioning(
        float $volCmPres,
        float $volTdPres,
        float $volTpPres,
        float $volCmDist,
        float $volTdDist,
        float $volTpDist,
        float $volTe,
        string $slug,
        Parcours $parcours,
        float $ects,
        EntityManagerInterface $entityManager
    ) : Response {
        $ficheMatiere = $entityManager->getRepository(FicheMatiere::class)->findOneBySlug($slug);

        return $this->render('fiche_matiere/maquette_iframe.html.twig', [
            'fiche_matiere' => $ficheMatiere,
            'typeDiplome' => $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome(),
            'formation' => $ficheMatiere->getParcours()?->getFormation(),
            'maquetteOrigineURL' => $parcours ? $this->generateUrl('app_parcours_maquette_iframe', ['parcours' => $parcours->getId()]) : "#",
            // $parcours ? $this->generateUrl('app_versioning_parcours_maquette_iframe', ['parcours' => $parcours->getId()]) : "#",
            'ects' => $ects,
            'heuresEctsEc' => [
                'volCmPres' => $volCmPres,
                'volTdPres' => $volTdPres,
                'volTpPres' => $volTpPres,
                'volCmDist' => $volCmDist,
                'volTdDist' => $volTdDist,
                'volTpDist' => $volTpDist,
                'volTe' => $volTe
            ],
            'isVersioning' => true
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{slug}/versioning/save', name: 'app_fiche_matiere_versioning_save', methods: ['GET'])]
    public function saveFicheMatiereIntoJson(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere $ficheMatiere,
        EntityManagerInterface $entityManager,
        Filesystem $fileSystem,
        VersioningFicheMatiere $ficheMatiereVersioningService
    ): RedirectResponse
    {
        try {
            // Date / Heure
            $now = new DateTimeImmutable('now');
            $dateHeure = $now->format('d-m-Y_H-i-s');
            // Sauvegarde
            $ficheMatiereVersioningService->saveFicheMatiereVersion($ficheMatiere, $now);
            $entityManager->flush();
            // Ajout dans les logs
            /**
             * @var User $user
             */
            $user = $this->getUser();
            $successLogTxt = "[{$dateHeure}] La fiche matière {$ficheMatiere->getSlug()} a été versionnée avec succès. ";
            $successLogTxt .= "Utilisateur : {$user->getPrenom()} {$user->getNom()} ({$user->getUsername()})\n";
            $fileSystem->appendToFile(__DIR__ . "/../../versioning_json/success_log/save_fiche_matiere_success.log", $successLogTxt);
            // Redirection
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'La fiche matière a bien été sauvegardée.',
            ]);
            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
        } catch (Exception $e) {
            // Log error
            $logTxt = "[{$dateHeure}] Le versioning de la fiche matière : "
                . "{$ficheMatiere->getSlug()} - ID : {$ficheMatiere->getId()}"
                . " - a rencontré une erreur.\nMessage : {$e->getMessage()}\n";
            $fileSystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/save_fiche_matiere_error.log", $logTxt);

            $this->addFlash('toast', [
                'type' => 'error',
                'text' => "Une erreur est survenue lors de la sauvegarde."
            ]);
            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/versioning/view', name: 'app_fiche_matiere_versioning_view')]
    public function getJsonVersion(
        FicheMatiereVersioning $ficheMatiereVersioning,
        VersioningFicheMatiere $ficheMatiereVersioningService,
        Filesystem $filesystem,
        ElementConstitutifRepository $elementConstitutifRepository,
        TypeEpreuveRepository $typeEpreuveRepository,
    ): RedirectResponse|Response
    {
        $sourceFicheMatiere = $ficheMatiereVersioning->getFicheMatiere();

        try {
            $version = $ficheMatiereVersioningService->loadFicheMatiereVersion($ficheMatiereVersioning);
            $ficheMatiere = $version['ficheMatiere'];
            $ficheMatiereParcours = $version['ficheMatiere']->getFicheMatiereParcours();
            $typeD = $version['ficheMatiere']->getParcours()->getFormation()->getTypeDiplome();
            $templateFormArray = [
                'Licence' => 'licence.html.twig',
                'Bachelor Universitaire de Technologie' => 'but.html.twig',
                'Master MEEF' => 'meef.html.twig'
            ];
            $mcccTypeDiplome = $this->typeDiplomeResolver->fromTypeDiplome($typeD);
            $templateForm = array_key_exists($typeD->getLibelle(), $templateFormArray) ? $templateFormArray[$typeD->getLibelle()] : [];
            $bccs = [];
            foreach ($ficheMatiere->getCompetences() as $competence) {
                if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                    $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                    $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
                }
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
            }

            $ecParcours = $elementConstitutifRepository->findByFicheMatiereParcours($ficheMatiereVersioning->getFicheMatiere());
            $typeEpreuves = $typeEpreuveRepository->findByTypeDiplome($typeD);

            return $this->render('fiche_matiere/show.versioning.html.twig', [
                'ficheMatiere' => $ficheMatiere,
                'formation' => $ficheMatiere->getParcours()?->getFormation(),
                'typeDiplome' => $ficheMatiere->getParcours()?->getFormation()->getTypeDiplome(),
                'bccs' => $bccs,
                'dateHeure' => $version['dateVersion'],
                'cssDiff' => "",
                'ficheMatiereParcours' => $ficheMatiereParcours,
                'templateForm' => $templateForm,
                'typeMccc' => $ficheMatiere->getTypeMccc(),
                'ecParcours' => $ecParcours,
                'mcccs' => $mcccTypeDiplome->getMcccs($ficheMatiere),
                'typeEpreuves' => $typeEpreuves
            ]);
        } catch (Exception $e) {
            // Log error
            $now = new DateTimeImmutable();
            $dateHeure = $now->format('d-m-Y_H-i-s');
            $logTxt = "[{$dateHeure}] La visualisation de la version de la fiche matière : "
            . "{$sourceFicheMatiere->getSlug()}"
            . " - a rencontré une erreur.\nMessage : {$e->getMessage()}\n";
            $filesystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/view_fiche_matiere_error.log", $logTxt);
            $this->addFlash('toast', [
                'type' => 'error',
                'text' => "Une erreur est survenue lors de la visualisation."
            ]);
            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $sourceFicheMatiere->getSlug()]);
        }
    }

    #[Route('/recherche/parcours/{parcours}/{keyword}', name: 'app_fiche_matiere_search')]
    public function getFicheMatiereForParcoursAndKeyword(
        FicheMatiereRepository $ficheMatiereRepository,
        TurboStreamResponseFactory $turboStream,
        Parcours $parcours,
        string $keyword = ""
    ): Response
    {
        $associatedFicheMatiere = $ficheMatiereRepository->findForParcoursWithKeyword($parcours, $keyword);

        $count = count($associatedFicheMatiere);
        $title = $count > 1
            ? $count . ' fiches matières associées'
            : ($count === 1 ? '1 fiche matière associée' : 'Aucune fiche matière associée');

        return $turboStream->streamOpenModalFromTemplates(
            $title,
            'Parcours : ' . $parcours->getDisplay(),
            'search/_associated_fiches.html.twig',
            [
                'fichesMatieres' => $associatedFicheMatiere,
                'keyword' => $keyword,
            ],
            '_ui/_footer_cancel.html.twig'
        );
    }

}
