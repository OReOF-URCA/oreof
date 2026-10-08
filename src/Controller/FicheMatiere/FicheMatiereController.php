<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\FicheMatiere;

use App\Controller\BaseController;
use App\Entity\FicheMatiere;
use App\Form\FicheMatiereStep1Type;
use App\Form\FicheMatiereStep1bType;
use App\Form\FicheMatiereStep2Type;
use App\Form\FicheMatiereStep3Type;
use App\Form\FicheMatiereStep4Type;
use App\Form\FicheMatiereStep4HdType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Repository\BlocCompetenceRepository;
use App\Repository\ButCompetenceRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereTabStateRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\VersioningFicheMatiere;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\McccDisplayInterface;
use Jfcherng\Diff\DiffHelper;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiche-matiere/v2', name: 'fiche_matiere_v2_')]
class FicheMatiereController extends BaseController
{
    #[Route('/{slug}/modifier', name: 'modifier')]
    #[Breadcrumb(menuKey: 'offre.detail_fiches')]
    public function modifier(
        Request                        $request,
        FicheMatiereTabStateRepository $statesRepo,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                   $ficheMatiere,
        BreadcrumbService              $breadcrumb
    ): Response {
        $breadcrumb->add($ficheMatiere->getLibelle());
        $breadcrumb->add('Modifier');

        if (!$ficheMatiere->isModifiable()) {
            $this->addFlash('danger', 'La fiche matière est verrouillée et ne peut pas être modifiée.');

            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
        }

        $tabStates = $statesRepo->indexByTabKey($ficheMatiere);
        $referer = $request->headers->get('referer');

        if ($referer === null || false === str_contains($referer, 'parcours')) {
            $source = 'liste';
        } else {
            //todo: gérer le retour et gérer retour vers hors diploôme
            $source = 'parcours';
            $link = $referer . '?step=4';
        }

        $parameters = [
            'tab' => 'identite',
            'source' => $source,
            'fiche_matiere' => $ficheMatiere,
            'form' => $this->createForm(FicheMatiereStep1Type::class, $ficheMatiere),
            'titre' => 'Identité de la fiche matière',
            'texte_help' => 'Indiquez les éléments d\'identification de la fiche matière',
            'tabStates' => $tabStates,
        ];

        // Si Turbo charge un frame, ne calcule pas la structure complète
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('fiche_matiere_v2/tabs/_identite.html.twig', [
                'fiche_matiere' => $ficheMatiere,
                'form' => $parameters['form']->createView(),
                'titre' => $parameters['titre'],
                'texte_help' => $parameters['texte_help'],
                'tabStates' => $tabStates,
            ]);
        }

        return $this->render('fiche_matiere_v2/modifier.html.twig', array_merge($parameters, []));
    }

    #[Route('/{slug}', name: 'voir', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'offre.detail_fiches')]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                       $ficheMatiere,
        ElementConstitutifRepository       $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        TypeDiplomeRepository              $typeDiplomeRepository,
        VersioningFicheMatiere             $ficheMatiereVersioningService,
        BreadcrumbService                  $breadcrumb
    ): Response {
        $breadcrumb->add($ficheMatiere->getLibelle());


        $formation = $ficheMatiere->getParcours()?->getFormation();

        $bccs = [];
        foreach ($ficheMatiere->getCompetences() as $competence) {
            if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
            }
            $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
        }

        if ($formation !== null) {
            $typeDiplome = $formation->getTypeDiplome();
        } else {
            $typeDiplome = $typeDiplomeRepository->findOneBy(['libelle_court' => 'L']);
        }

        if ($typeDiplome === null) {
            throw new TypeDiplomeNotFoundException();
        }

        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome);
        $mcccs = [];
        if ($ficheMatiere->getTypeMccc() !== null) {
            if (!$typeD instanceof McccDisplayInterface) {
                throw new \RuntimeException('Ce type de diplôme ne prend pas en charge cet affichage MCCC.');
            }
            $mcccs = $typeD->getDisplayMccc($typeD->getMcccs($ficheMatiere), $ficheMatiere->getTypeMccc());
        }

        $cssDiff = DiffHelper::getStyleSheet();
        $textDifferences = $ficheMatiereVersioningService
            ->getStringDifferencesWithBetweenFicheMatiereAndLastVersion($ficheMatiere);

        $ficheMatiereParcours = $ficheMatiereMutualisableRepository->findByFicheMatieres($ficheMatiere);
        $ecParcours = $elementConstitutifRepository->findByFicheMatiereParcours($ficheMatiere);
        return $this->render('fiche_matiere_v2/voir.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'ficheMatiereParcours' => $ficheMatiereParcours,
            'ecParcours' => $ecParcours,
            'formation' => $formation,
            'typeEpreuves' => $typeD->getTypeEpreuves(),
            'typeD' => $typeD,
            'typeDiplome' => $typeDiplome,
            'ects' => $ficheMatiere->getEcts(),
            'mcccs' => $mcccs,
            'bccs' => $bccs,
            'typeMccc' => $ficheMatiere->getTypeMccc(),
            'stringDifferences' => $textDifferences,
            'cssDiff' => $cssDiff
        ]);
    }


    #[Route('/{slug}/modifier/tabs/{tab}', name: 'tabs')]
    public function tabs(
        FicheMatiereTabStateRepository $statesRepo,
        BlocCompetenceRepository       $blocCompetenceRepository,
        ButCompetenceRepository        $butCompetenceRepository,
        ElementConstitutifRepository   $elementConstitutifRepository,
        TypeDiplomeRepository          $typeDiplomeRepository,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                   $ficheMatiere,
        string                         $tab,
        Request                        $request
    ): Response {
        $referer = $request->headers->get('referer');

        if ($referer === null || false === str_contains($referer, 'parcours')) {
            $source = 'liste';
        } else {
            $source = 'parcours';
        }

        $formation = $ficheMatiere->getParcours()?->getFormation();
        $isBut = $formation?->getTypeDiplome()?->getLibelleCourt() === 'BUT';
        $isHd = $ficheMatiere->isHorsDiplome();

        if ($formation !== null) {
            $typeDiplome = $formation->getTypeDiplome();
        } else {
            $typeDiplome = $typeDiplomeRepository->findOneBy(['libelle_court' => 'L']);
        }

        $typeD = $typeDiplome !== null ? $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome) : null;

        $ecProprietaire = null;
        foreach ($ficheMatiere->getElementConstitutifs() as $ec) {
            if ($ec->getParcours() === $ficheMatiere->getParcours()) {
                $ecProprietaire = $ec;
                break;
            }
        }

        $form = null;
        $tabView = $tab;
        $titre = null;
        $texte_help = null;
        $bccs = [];
        $ecComps = [];
        $ecBccs = [];
        $mcccs = [];

        switch ($tab) {
            case 'identite':
                $form = $this->createForm(FicheMatiereStep1Type::class, $ficheMatiere);
                $titre = 'Identité de la fiche matière';
                $texte_help = 'Indiquez les éléments d\'identification de la fiche matière';
                break;
            case 'mutualisation':
                $form = $this->createForm(FicheMatiereStep1bType::class, $ficheMatiere);
                $titre = 'Mutualisation de la fiche matière';
                $texte_help = 'Indiquez les éléments de mutualisation de la fiche matière';
                break;
            case 'presentation':
                $form = $this->createForm(FicheMatiereStep2Type::class, $ficheMatiere);
                $titre = 'Présentation de la fiche matière';
                $texte_help = 'Indiquez les éléments descriptifs de la fiche matière';
                break;
            case 'competences':
                $titre = 'Compétences';
                $texte_help = 'Indiquez les compétences et apprentissages critiques visés';
                $form = $this->createForm(FicheMatiereStep3Type::class, $ficheMatiere);

                if ($isBut && $formation !== null) {
                    $bccs = $butCompetenceRepository->findBy(['formation' => $formation], ['numero' => 'ASC']);
                    foreach ($ficheMatiere->getApprentissagesCritiques() as $ac) {
                        $ecComps[] = $ac->getId();
                        if ($ac->getNiveau()?->getCompetence() !== null) {
                            $ecBccs[] = $ac->getNiveau()->getCompetence()->getId();
                        }
                    }
                } else {
                    if ($ficheMatiere->getParcours() !== null) {
                        $bccs = $blocCompetenceRepository->findByParcours($ficheMatiere->getParcours());
                    } else {
                        $bccs = $blocCompetenceRepository->findBy(['parcours' => null]);
                    }
                    foreach ($ficheMatiere->getCompetences() as $comp) {
                        $ecComps[] = $comp->getId();
                        if ($comp->getBlocCompetence() !== null) {
                            $ecBccs[] = $comp->getBlocCompetence()->getId();
                        }
                    }
                }
                break;
            case 'volumes_horaires':
                $titre = 'Volumes horaires';
                $texte_help = 'Indiquez les éléments de volumes horaires de la fiche matière';
                if ($isHd) {
                    $form = $this->createForm(FicheMatiereStep4HdType::class, $ficheMatiere);
                } elseif ($isBut) {
                    $form = $this->createForm(FicheMatiereStep4Type::class, $ficheMatiere);
                }
                break;
            case 'mccc':
                $titre = 'MCCC';
                $texte_help = 'Indiquez les éléments de MCCC de la fiche matière';
                if ($isBut && $typeD !== null) {
                    $mcccs = $typeD->getMcccs($ficheMatiere);
                } elseif ($typeD instanceof McccDisplayInterface && $ficheMatiere->getTypeMccc() !== null) {
                    $mcccs = $typeD->getDisplayMccc($typeD->getMcccs($ficheMatiere), $ficheMatiere->getTypeMccc());
                }
                break;
        }

        $tabStates = $statesRepo->indexByTabKey($ficheMatiere);

        $parameters = [
            'fiche_matiere' => $ficheMatiere,
            'ficheMatiere' => $ficheMatiere,
            'form' => $form?->createView(),
            'titre' => $titre,
            'texte_help' => $texte_help,
            'tabStates' => $tabStates,
            'source' => $source,
            'isBut' => $isBut,
            'isHd' => $isHd,
            'formation' => $formation,
            'typeD' => $typeD,
            'typeDiplome' => $typeDiplome,
            'ecProprietaire' => $ecProprietaire,
            'bccs' => $bccs,
            'ecBccs' => array_flip(array_filter(array_unique($ecBccs))),
            'ecComps' => array_flip(array_filter($ecComps)),
            'mcccs' => $mcccs,
            'typeEpreuves' => $typeD?->getTypeEpreuves() ?? [],
            'typeMccc' => $ficheMatiere->getTypeMccc(),
            'templateForm' => $typeD?->getMcccTemplate(),
        ];

        // Si la requête vient d'un Turbo Frame (header `Turbo-Frame` présent), renvoyer uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('fiche_matiere_v2/tabs/_' . $tabView . '.html.twig', $parameters);
        }

        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('fiche_matiere_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => $tabView,
        ]));
    }
}
