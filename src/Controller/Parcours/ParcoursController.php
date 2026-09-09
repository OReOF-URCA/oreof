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
use App\Service\Validation\SemesterValidationRefresher;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
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
        Parcours                   $parcours
    ): Response
    {
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
        $dto = $typeD->calculStructureParcours($parcours);

        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'dto' => $dto,
        ]));
    }

    #[Route('/{parcours}', name: 'voir', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function voir(
        Parcours               $parcours,
        LheoXML                $lheoXML,
        VersioningParcours     $versioningParcours,
        VersioningFormation    $versioningFormation,
        EntityManagerInterface $entityManager,
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

        $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours);
        $textDifferencesFormation = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
//        $version = $versioningParcours->hasLastVersion($parcours);
//
//        $cssDiff = DiffHelper::getStyleSheet();


//        // Ordre des semestres manquants
//        $missingSemestre = [];
//
//        // Si le parcours est en alternance sans les premiers semestres
//        // on met un lien vers le parcours de base
//        $parcoursDeBase = null;
//        if($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_ALTERNANCE
//            && $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT'
//        ) {
//            $parcoursDeBase = $entityManager->getRepository(Parcours::class)
//                ->findParcoursDeBaseAlternance(
//                    $parcours->getLibelle(),
//                    GetDpeParcours::getFromParcours($parcours)?->getCampagneCollecte()?->getId()
//                );
//            $parcoursDeBase = count($parcoursDeBase) > 0 ? $parcoursDeBase[0] : null;
//
//            $missingSemestre = $entityManager->getRepository(Parcours::class)
//                ->findParcoursAlternanceHasMissingSemestre($parcours);
//        }
        return $this->render('parcours_v2/voir.html.twig', [
            'parcours' => $parcours,
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'formation' => $formation,
            'typeDiplome' => $typeDiplome,
            'hasParcours' => $formation->isHasParcours(),
            'typeD' => $typeD,
            'lheoXML' => $lheoXML,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'stringDifferencesFormation' => $textDifferencesFormation,
//            'hasLastVersion' => $versioningParcours->hasLastVersion($parcours),
//            'cssDiff' => $cssDiff,
//            'version' => $version,
//            'parcoursDeBase' => $parcoursDeBase,
//            'missingSemestre' => $missingSemestre
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

        $dto = $typeD->calculStructureParcours($parcours);

        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => 'semestre',
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'dto' => $dto
        ]));
    }

    #[Route('/{parcours}/modifier/semestre/{semestreParcours}/validation', name: 'semestre_validation')]
    public function semestreValidation(
        TurbostreamResponseFactory $turboStream,
        TypeDIplomeResolver         $typeDiplomeResolver,
        Parcours                    $parcours,
        SemestreParcours            $semestreParcours,
        SemesterValidationRefresher $refresher
    ): Response
    {
        // refresh du semestre si dirty
        $refresher->forceRefresh($semestreParcours, $parcours);

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);


        dump($dtoSemestre);

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

        $dto = $typeD->calculStructureParcours($parcours); //todo: un dto plus léger pour juste la structure...

        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('parcours_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => $tabView,
            'dto' => $dto
        ]));
    }
}
