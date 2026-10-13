<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\Parcours;

use App\Classes\GetDpeParcours;
use App\Controller\BaseController;
use App\Entity\Annee;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Enums\ValidationStatusEnum;
use App\Form\ParcoursStep1Type;
use App\Form\ParcoursStep2Type;
use App\Form\ParcoursStep3Type;
use App\Form\ParcoursStep5Type;
use App\Form\ParcoursStep6Type;
use App\Form\ParcoursStep7Type;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Repository\ParcoursTabStateRepository;
use App\Repository\ValidationIssueRepository;
use App\Service\LheoXML;
use App\Service\Parcours\ParcoursAccessExplainer;
use App\Service\Parcours\ParcoursDeleteService;
use App\Service\Validation\SemesterValidationRefresher;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Access;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parcours/v2', name: 'parcours_v2_')]
class ParcoursController extends BaseController
{
    #[Route('/{parcours}/modifier', name: 'modifier')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function modifier(
        Request                    $request,
        ParcoursTabStateRepository $statesRepo,
        TypeDiplomeResolver        $typeDiplomeResolver,
        BreadcrumbService          $breadcrumb,
        ParcoursAccessExplainer    $accessExplainer,
        Parcours                   $parcours
    ): Response
    {
        if (!$this->isGranted('EDIT', $parcours)) {
            $reason = $accessExplainer->explainEditDenial($parcours);
            $this->addFlash('danger', $reason);

            $url = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);
            $response = $this->redirect($url);
            $response->headers->set('Turbo-Location', $url);

            return $response;
        }

        if ($parcours->getFormation() !== null) {
            $breadcrumb->add(
                $parcours->getFormation()->getDisplay(),
                'formation_v2_voir',
                ['slug' => $parcours->getFormation()->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_voir',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Modifier le parcours');

        $tabStates = $statesRepo->indexByTabKey($parcours);

        $parameters = [
            'tab' => 'presentation',
            'parcours' => $parcours,
            'form' => $this->createForm(ParcoursStep1Type::class, $parcours),
            'titre' => 'Présentation du parcours',
            'texte_help' => 'Indiquez les éléments du parcours',
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'tabStates' => $tabStates,
            'typeDiplome' => $parcours->getFormation()?->getTypeDiplome(),
        ];

        // Si Turbo charge un frame, ne calcule pas la structure complète
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('parcours_v2/tabs/_presentation.html.twig', [
                'parcours' => $parcours,
                'form' => $parameters['form']->createView(),
                'titre' => $parameters['titre'],
                'texte_help' => $parameters['texte_help'],
                'tabStates' => $tabStates,
            ]);
        }

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dto = $typeD->calcul($parcours);

        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'dto' => $dto,
        ]));
    }

    #[Route('/{parcours}', name: 'voir', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function voir(
        Request                $request,
        Parcours               $parcours,
        LheoXML                $lheoXML,
        VersioningParcours     $versioningParcours,
        VersioningFormation    $versioningFormation,
        BreadcrumbService      $breadcrumb,
    ): Response
    {
        $formation = $parcours->getFormation();
        if ($formation === null) {
            throw $this->createNotFoundException();
        }
        $typeDiplome = $formation->getTypeDiplome();
        if ($typeDiplome === null) {
            throw $this->createNotFoundException();
        }

        $breadcrumb->add(
            $formation->getDisplay(),
            'formation_v2_voir',
            ['slug' => $formation->getSlug()]
        );
        $breadcrumb->add(
            $parcours->getDisplay()
        );

        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome);

        $cssDiff = DiffHelper::getStyleSheet();
        $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours);
        $textDifferencesParcoursCampagne = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours, true);
        $textDifferencesFormation = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
        $textDifferencesFormationCampagne = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation, true);
        $hasLastVersion = $versioningParcours->hasLastVersion($parcours);

        $diffCountLastVersion = 0;
        foreach (array_merge($textDifferencesFormation, $textDifferencesParcours) as $fieldDiff) {
            if (is_string($fieldDiff) && trim(strip_tags($fieldDiff)) !== '') {
                $diffCountLastVersion++;
            }
        }

        $diffCountCampagne = 0;
        foreach (array_merge($textDifferencesFormationCampagne, $textDifferencesParcoursCampagne) as $fieldDiff) {
            if (is_string($fieldDiff) && trim(strip_tags($fieldDiff)) !== '') {
                $diffCountCampagne++;
            }
        }

        $canSeeDifferences = $this->isGranted('RELATED_TO_PARCOURS', $parcours);
        $displayComparaison = $request->query->get('optionDisplay', 'false');

        return $this->render('parcours_v2/voir.html.twig', [
            'parcours' => $parcours,
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'formation' => $formation,
            'typeDiplome' => $typeDiplome,
            'hasParcours' => $formation->isHasParcours(),
            'typeD' => $typeD,
            'lheoXML' => $lheoXML,
            'cssDiff' => $cssDiff,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'stringDifferencesParcoursCampagne' => $textDifferencesParcoursCampagne,
            'stringDifferencesFormation' => $textDifferencesFormation,
            'stringDifferencesFormationCampagne' => $textDifferencesFormationCampagne,
            'diffCountLastVersion' => $diffCountLastVersion,
            'diffCountCampagne' => $diffCountCampagne,
            'hasLastVersion' => $hasLastVersion,
            'displayComparaison' => $displayComparaison,
            'canSeeDifferences' => $canSeeDifferences,
        ]);
    }

    #[Route('/{parcours}/modifier/annee/{annee}', name: 'annee')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function annee(
        Parcours          $parcours,
        Annee             $annee,
        BreadcrumbService $breadcrumb
    ): Response
    {
        if (!$this->isGranted('EDIT', $parcours)) {
            $this->addFlash('danger', 'Le parcours est verrouillé ou vous n\'avez pas les droits de modification.');

            $url = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);
            $response = $this->redirect($url);
            $response->headers->set('Turbo-Location', $url);

            return $response;
        }

        if ($parcours->getFormation() !== null) {
            $breadcrumb->add(
                $parcours->getFormation()->getDisplay(),
                'formation_v2_voir',
                ['slug' => $parcours->getFormation()->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_modifier',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Année ' . $annee->getOrdre());

        return $this->render('parcours_v2/tabs/_annee.html.twig', [
            'annee' => $annee,
            'parcours' => $parcours
        ]);
    }

    #[Route('/{parcours}/modifier/semestre/{semestreParcours}', name: 'semestre')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function semestre(
        ValidationIssueRepository   $validationIssueRepository,
        Request                     $request,
        TypeDiplomeResolver         $typeDiplomeResolver,
        Parcours                    $parcours,
        SemestreParcours            $semestreParcours,
        SemesterValidationRefresher $refresher,
        BreadcrumbService           $breadcrumb
    ): Response
    {
        if (!$this->isGranted('EDIT', $parcours)) {
            $this->addFlash('danger', 'Le parcours est verrouillé ou vous n\'avez pas les droits de modification.');

            $url = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);
            $response = $this->redirect($url);
            $response->headers->set('Turbo-Location', $url);

            return $response;
        }

        if ($parcours->getFormation() !== null) {
            $breadcrumb->add(
                $parcours->getFormation()->getDisplay(),
                'formation_v2_voir',
                ['slug' => $parcours->getFormation()->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_modifier',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Semestre ' . $semestreParcours->getOrdre());

        // refresh du semestre si dirty
        $refresher->refreshIfDirty($semestreParcours, $parcours);

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);

        $parameters = [
            'semestreParcours' => $semestreParcours,
            'semestre' => $dtoSemestre,
            'parcours' => $parcours,
            'typeD' => $typeD,
            'validationsIssues' => $validationIssueRepository->findBySemestre($dtoSemestre->semestre->getId())
        ];

        // Si la requête vient d'un Turbo Frame (header `Turbo-Frame` présent), renvoyer uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('parcours_v2/tabs/_semestre.html.twig', $parameters);
        }

        $dto = $typeD->calcul($parcours);

        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => 'semestre',
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'dto' => $dto
        ]));
    }

    #[Route('/{parcours}/modifier/semestre/{semestreParcours}/validation', name: 'semestre_validation')]
    public function semestreValidation(
        TurboStreamResponseFactory $turboStream,
        TypeDiplomeResolver         $typeDiplomeResolver,
        Parcours                    $parcours,
        SemestreParcours            $semestreParcours,
        SemesterValidationRefresher $refresher
    ): Response
    {
        // refresh du semestre si dirty
        $refresher->forceRefresh($semestreParcours, $parcours);

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);


        //dump($dtoSemestre);

        switch ($dtoSemestre->semestre->getValidationStatus()) {
            case ValidationStatusEnum::VALID:
                $toastMessage = 'Semestre vérifié et conforme';
                $toastType = 'success';
                break;
            case ValidationStatusEnum::INVALID:
            case ValidationStatusEnum::INCOMPLETE:
                $toastMessage = 'Semestre non conforme';
                $toastType = 'danger';
                break;

        }

        $parameters = [
            'semestreParcours' => $semestreParcours,
            'semestre' => $dtoSemestre,
            'parcours' => $parcours,
            'toastMessage' => $toastMessage ?? '',
            'toastType' => $toastType ?? null,
        ];

        return $turboStream->stream('parcours_v2/turbo/semestre_validation.stream.html.twig', $parameters);
    }

    #[Route('/{parcours}/modifier/tabs/{tab}', name: 'tabs')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function tabs(
        TypeDiplomeResolver        $typeDiplomeResolver,
        ParcoursTabStateRepository $statesRepo,
        Parcours                   $parcours,
        string                     $tab,
        Request                    $request,
        BreadcrumbService          $breadcrumb
    ): Response
    {
        if (!$this->isGranted('EDIT', $parcours)) {
            $this->addFlash('danger', 'Le parcours est verrouillé ou vous n\'avez pas les droits de modification.');

            $url = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);
            $response = $this->redirect($url);
            $response->headers->set('Turbo-Location', $url);

            return $response;
        }

        if ($parcours->getFormation() !== null) {
            $breadcrumb->add(
                $parcours->getFormation()->getDisplay(),
                'formation_v2_voir',
                ['slug' => $parcours->getFormation()->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_voir',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Modifier le parcours');

        $validTabs = ['presentation_formation', 'presentation', 'maquette', 'descriptif', 'admission', 'et_apres', 'configuration'];
        if (!in_array($tab, $validTabs, true)) {
            throw $this->createNotFoundException('Onglet inconnu : ' . $tab);
        }

        $form = null;
        $tabView = $tab;
        $titre = null;
        $texte_help = null;

        switch ($tab) {
            case 'presentation_formation':
                $titre = 'Présentation de la formation';
                $texte_help = 'Indiquez les éléments de la formation, commun aux parcours';
                break;
            case 'presentation':
                $form = $this->createForm(ParcoursStep1Type::class, $parcours);
                $titre = 'Présentation du parcours';
                $texte_help = 'Indiquez les éléments du parcours';
                break;
            case 'maquette':
                $form = $this->createForm(ParcoursStep3Type::class, $parcours, [
                    'typeDiplome' => $parcours->getFormation()?->getTypeDiplome()
                ]);
                $titre = 'Structure de la maquette';
                $texte_help = '...??...';
                break;
            case 'descriptif':
                $form = $this->createForm(ParcoursStep2Type::class, $parcours, [
                    'typeDiplome' => $parcours->getTypeDiplome()
                ]);
                $titre = 'Descriptif du parcours';
                $texte_help = 'Description du parcours';
                break;
            case 'admission':
                $form = $this->createForm(ParcoursStep5Type::class, $parcours);
                $titre = 'Admission au parcours';
                $texte_help = '...';
                break;
            case 'et_apres':
                $form = $this->createForm(ParcoursStep6Type::class, $parcours);
                $titre = 'Et après le parcours';
                $texte_help = '...';
                break;
            case 'configuration':
                $form = $this->createForm(ParcoursStep7Type::class, $parcours);
                $titre = 'Configuration du parcours';
                $texte_help = '...';
                break;
        }

        $tabStates = $statesRepo->indexByTabKey($parcours);
        $typeD = $typeDiplomeResolver->fromParcours($parcours);

        $parameters = [
            'parcours' => $parcours,
            'form' => $form?->createView(),
            'titre' => $titre,
            'typeD' => $typeD,
            'texte_help' => $texte_help,
            'tabStates' => $tabStates,
            'typeDiplome' => $parcours->getFormation()?->getTypeDiplome(),
        ];

        // Si la requête vient d'un Turbo Frame (header `Turbo-Frame` présent), renvoyer uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('parcours_v2/tabs/_' . $tabView . '.html.twig', $parameters);
        }

        $dto = $typeD->calcul($parcours); //todo: un dto plus léger pour juste la structure...

        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => $tabView,
            'dto' => $dto
        ]));
    }

    #[Route('/{parcours}/supprimer', name: 'supprimer', methods: ['GET'])]
    public function supprimer(
        Parcours $parcours,
        Request $request,
        ParcoursDeleteService $deleteService,
        BreadcrumbService $breadcrumb,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $formation = $parcours->getFormation();
        if ($formation !== null) {
            $breadcrumb->add(
                $formation->getDisplay(),
                'formation_v2_voir',
                ['slug' => $formation->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_voir',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Suppression définitive');

        $analysis = $deleteService->analyze($parcours);

        $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->isXmlHttpRequest();

        if ($isTurbo) {
            return $turboStream->streamOpenModalFromTemplates(
                'Supprimer définitivement le parcours',
                $parcours->getLibelle(),
                'parcours_v2/_modal_delete.html.twig',
                [
                    'parcours' => $parcours,
                    'formation' => $formation,
                    'analysis' => $analysis,
                ],
                'parcours_v2/_modal_delete_footer.html.twig',
                [
                    'parcours' => $parcours,
                ]
            );
        }

        return $this->render('parcours_v2/supprimer.html.twig', [
            'parcours' => $parcours,
            'formation' => $formation,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/{parcours}/supprimer', name: 'supprimer_post', methods: ['POST'])]
    public function supprimerPost(
        Parcours $parcours,
        Request $request,
        ParcoursDeleteService $deleteService,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->isXmlHttpRequest();

        $csrfToken = (string)$request->request->get('_token', $request->request->get('csrf_token', ''));
        if (!$this->isCsrfTokenValid('parcours_delete_' . $parcours->getId(), $csrfToken)) {
            if ($isTurbo) {
                return $turboStream->streamToastError('Token CSRF invalide ou expiré.');
            }
            $this->addFlash('danger', 'Token CSRF invalide ou expiré.');
            return $this->redirectToRoute('parcours_v2_supprimer', ['parcours' => $parcours->getId()]);
        }

        $analysis = $deleteService->analyze($parcours);
        $confirmationText = trim((string)$request->request->get('confirmation_text', ''));
        $expectedText = trim($parcours->getLibelle() ?? '');

        if (mb_strtoupper($confirmationText) !== mb_strtoupper($expectedText) && mb_strtoupper($confirmationText) !== 'SUPPRIMER') {
            $msg = sprintf('Le texte saisi (« %s ») ne correspond pas au libellé du parcours (« %s ») ou à « SUPPRIMER ». La suppression a été annulée.', $confirmationText, $expectedText);
            if ($isTurbo) {
                return $turboStream->streamToastError($msg);
            }
            $this->addFlash('danger', $msg);
            return $this->redirectToRoute('parcours_v2_supprimer', ['parcours' => $parcours->getId()]);
        }

        $force = (bool)$request->request->get('force', false);
        if ($analysis->hasSharedDependencies() && !$force) {
            $msg = 'Des dépendances partagées ont été détectées. Vous devez cocher la confirmation explicite de forçage pour supprimer ce parcours.';
            if ($isTurbo) {
                return $turboStream->streamToastError($msg);
            }
            $this->addFlash('danger', $msg);
            return $this->redirectToRoute('parcours_v2_supprimer', ['parcours' => $parcours->getId()]);
        }

        $formation = $parcours->getFormation();
        $formationSlug = $formation?->getSlug();
        $libelle = $parcours->getLibelle();
        $parcoursId = $parcours->getId();

        $deleteService->delete($parcours, $force);

        $flashMsg = sprintf('Le parcours « %s » a été définitivement supprimé.', $libelle);
        $this->addFlash('success', $flashMsg);

        $referer = (string)$request->headers->get('referer', '');
        if ($formationSlug !== null && str_contains($referer, '/offre/')) {
            $redirectUrl = $this->generateUrl('offre_v2_configurer', ['slug' => $formationSlug]);
        } elseif ($formationSlug !== null) {
            $redirectUrl = $this->generateUrl('formation_v2_voir', ['slug' => $formationSlug]);
        } else {
            $redirectUrl = $this->generateUrl('offre_v2_index');
        }

        if ($isTurbo) {
            return $turboStream->stream('parcours_v2/turbo/delete_success.stream.html.twig', [
                'redirectUrl' => $redirectUrl,
                'parcoursId' => $parcoursId,
                'toastMessage' => $flashMsg,
            ]);
        }

        return $this->redirect($redirectUrl);
    }
}
