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
use App\Classes\GetElementConstitutif;
use App\Classes\JsonReponse;
use App\Classes\verif\FicheMatiereState;
use App\DTO\StructureEc;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereVersioning;
use App\Entity\Parcours;
use App\Entity\TypeEpreuve;
use App\Form\FicheMatiereType;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\LangueRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\Repository\UeRepository;
use App\Service\VersioningFicheMatiere;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\JsonRequest;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiche/matiere')]
class FicheMatiereController extends BaseController
{
    #[Route('/new', name: 'app_fiche_matiere_new', methods: ['GET', 'POST'])]
    public function new(
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

            return $this->json(true);
        }

        return $this->render('fiche_matiere/new.html.twig', [
            'fiche_matiere' => $ficheMatiere,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{slug}', name: 'app_fiche_matiere_show', methods: ['GET'])]
    public function show(
        ElementConstitutifRepository $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        FicheMatiere                 $ficheMatiere,
        VersioningFicheMatiere       $ficheMatiereVersioningService
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
            $formation = null;
            $typeDiplome = null;
            $typeD = $this->typeDiplomeResolver->get(null);
        }


        $cssDiff = DiffHelper::getStyleSheet();
        $textDifferences = $ficheMatiereVersioningService
            ->getStringDifferencesWithBetweenFicheMatiereAndLastVersion($ficheMatiere);

        $ficheMatiereParcours = $ficheMatiereMutualisableRepository->findByFicheMatieres($ficheMatiere);
        $ecParcours = $elementConstitutifRepository->findByFicheMatiereParcours($ficheMatiere);

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
                    'subject' => 'formation',
                ]
            ) || $this->isGranted(
                'EDIT',
                [
                    'route' => 'app_fiche_matiere',
                    'subject' => 'parcours',
                ]
            ) || $this->isGranted(
                'EDIT',
                [
                    'route' => 'app_fiche_matiere_hd',
                    'subject' => 'etablissement',
                ]
            ) || $this->isGranted('ROLE_ADMIN'))) {
            return $this->redirectToRoute('app_fiche_matiere_show', ['slug' => $ficheMatiere->getSlug()]);
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

    #[Route('/{slug}/dupliquer', name: 'app_fiche_matiere_dupliquer', methods: ['GET'])]
    public function dupliquer(
        FicheMatiere $ficheMatiere,
        ElementConstitutifRepository $elementConstitutifRepository,
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

    #[Route('/{slug}', name: 'app_fiche_matiere_delete', methods: ['DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request $request,
        FicheMatiere $ficheMatiere,
        FicheMatiereRepository $ficheMatiereRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $ficheMatiere->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {

            if ($ficheMatiere->getElementConstitutifs()->count() > 0) {
                return JsonReponse::error('Impossible de supprimer la fiche matière car elle est utilisée par au moins un élément constitutif.');
            }

            if ($ficheMatiere->getFicheMatiereParcours()->count() > 0) {
                return JsonReponse::error('Impossible de supprimer la fiche matière car elle est potentiellement mutualisée avec d\'autres parcours.');
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

            return JsonReponse::success('La fiche matière a bien été supprimée.');
        }

        return $this->json(false);
    }

    #[Route('/{ec}/{parcours}/{ects}/maquette_iframe', name: 'app_fiche_matiere_maquette_iframe')]
    public function getMaquetteIframe(ElementConstitutif $ec, Parcours $parcours, float $ects) : Response
    {

        $ficheMatiere = $ec->getFicheMatiere();
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
            return $this->redirectToRoute('app_fiche_matiere_show', ['slug' => $ficheMatiere->getSlug()]);
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
            return $this->redirectToRoute('app_fiche_matiere_show', ['slug' => $ficheMatiere->getSlug()]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/versioning/view', name: 'app_fiche_matiere_versioning_view')]
    public function getJsonVersion(
        FicheMatiereVersioning $ficheMatiereVersioning,
        VersioningFicheMatiere $ficheMatiereVersioningService,
        Filesystem $filesystem,
        EntityManagerInterface $em,
        LicenceTypeDiplome $licenceTypeD,
        ButTypeDiplome $butTypeD,
        MeefTypeDiplome $meefTypeD
    ): RedirectResponse|Response
    {
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
            $mcccTypeDiplome = [
                'Licence' => $licenceTypeD,
                'Bachelor Universitaire de Technologie' => $butTypeD,
                'Master MEEF' => $meefTypeD
            ];
            $templateForm = array_key_exists($typeD->getLibelle(), $templateFormArray) ? $templateFormArray[$typeD->getLibelle()] : [];
            $mcccTypeDiplome = array_key_exists($typeD->getLibelle(), $mcccTypeDiplome) ? $mcccTypeDiplome[$typeD->getLibelle()] : [];
            $bccs = [];
            foreach ($ficheMatiere->getCompetences() as $competence) {
                if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                    $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                    $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
                }
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
            }

            $ecParcours = $em->getRepository(ElementConstitutif::class)
                ->findByFicheMatiereParcours($ficheMatiereVersioning->getFicheMatiere());
            $typeEpreuves = $em->getRepository(TypeEpreuve::class)->findByTypeDiplome($typeD);

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
            . "{$ficheMatiere->getSlug()}"
            . " - a rencontré une erreur.\nMessage : {$e->getMessage()}\n";
            $filesystem->appendToFile(__DIR__ . "/../../versioning_json/error_log/view_fiche_matiere_error.log", $logTxt);
            $this->addFlash('toast', [
                'type' => 'error',
                'text' => "Une erreur est survenue lors de la visualisation."
            ]);
            return $this->redirectToRoute('app_fiche_matiere_show', ['slug' => $ficheMatiere->getSlug()]);
        }
    }

    #[Route('/recherche/parcours/{parcours}/{keyword}', name: 'app_fiche_matiere_search')]
    public function getFicheMatiereForParcoursAndKeyword(
        EntityManagerInterface $entityManager,
        Parcours $parcours,
        string $keyword = ""
    ): JsonResponse
    {
        $associatedFicheMatiere = $entityManager
            ->getRepository(FicheMatiere::class)
            ->findForParcoursWithKeyword($parcours, $keyword);

        return new JsonResponse(
            $associatedFicheMatiere,
            200,
            ['Content-Type' => 'application/json'],
            false
        );
    }

}
