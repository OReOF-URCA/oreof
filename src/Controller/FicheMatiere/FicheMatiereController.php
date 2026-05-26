<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\FicheMatiere;

use App\Classes\GetDpeParcours;
use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\Annee;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Form\FicheMatiereStep1bType;
use App\Form\FicheMatiereStep1Type;
use App\Form\FicheMatiereStep2Type;
use App\Form\FicheMatiereStep3Type;
use App\Form\FicheMatiereStep4HdType;
use App\Form\FormationStep1Type;
use App\Form\FormationStep2Type;
use App\Form\FormationStep3Type;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereTabStateRepository;
use App\Repository\FormationTabStateRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\Repository\ValidationIssueRepository;
use App\Service\LheoXML;
use App\Service\Validation\SemesterValidationRefresher;
use App\Service\VersioningFicheMatiere;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use Jfcherng\Diff\DiffHelper;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiche-matiere/v2', name: 'fiche_matiere_v2_')]
class FicheMatiereController extends BaseController
{
    #[Route('/{slug}/modifier', name: 'modifier')]
    public function modifier(
        Request                        $request,
        FicheMatiereTabStateRepository $statesRepo,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                   $ficheMatiere
    ): Response
    {
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
            return $this->render('parcours_v2/tabs/_presentation.html.twig', [
                'fiche_matiere' => $ficheMatiere,
                'form' => $parameters['form']->createView(),
                'titre' => $parameters['titre'],
                'texte_help' => $parameters['texte_help'],
                'tabStates' => $tabStates,
            ]);
        }

        return $this->render('fiche_matiere_v2/modifier.html.twig', array_merge($parameters, [
        ]));
    }

    #[Route('/{slug}', name: 'voir', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        FicheMatiere                       $ficheMatiere,
        ElementConstitutifRepository       $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        TypeDiplomeRepository              $typeDiplomeRepository,
        VersioningFicheMatiere             $ficheMatiereVersioningService
    ): Response
    {


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
            'mcccs' => $typeD->getDisplayMccc($typeD->getMcccs($ficheMatiere), $ficheMatiere->getTypeMccc()),
            'bccs' => $bccs,
            'typeMccc' => $ficheMatiere->getTypeMccc(),
            'stringDifferences' => $textDifferences,
            'cssDiff' => $cssDiff
        ]);
    }


    #[Route('/{slug}/modifier/tabs/{tab}', name: 'tabs')]
    public function tabs(
        FicheMatiereTabStateRepository $statesRepo,
        FicheMatiere                   $ficheMatiere,
        string                         $tab,
        Request                        $request): Response
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || false === str_contains($referer, 'parcours')) {
            $source = 'liste';
        } else {
            $source = 'parcours';
        }

        $form = null;
        $tabView = $tab;
        $titre = null;
        $texte_help = null;

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
            case 'volumes_horaires':
                $titre = 'Volumes horaires';
                $texte_help = 'Indiquez les éléments de volumes horaires de la fiche matière';
                if ($ficheMatiere->isHorsDiplome()) {
                    $form = $this->createForm(FicheMatiereStep4HdType::class, $ficheMatiere);
                    $tabView = 'volumes_horaires_hors_diplome';
                }
                break;
            case 'mccc':
                $titre = 'MCCC';
                $texte_help = 'Indiquez les éléments de MCCC de la fiche matière';
                break;

        }

        $tabStates = $statesRepo->indexByTabKey($ficheMatiere);

        $parameters = [
            'fiche_matiere' => $ficheMatiere,
            'form' => $form?->createView(),
            'titre' => $titre,
            'texte_help' => $texte_help,
            'tabStates' => $tabStates,
            'source' => $source,
        ];

        // Si la requête vient d'un Turbo Frame (header `Turbo-Frame` présent), renvoyer uniquement le fragment
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('fiche_matiere_v2/tabs/_' . $tabView . '.html.twig', $parameters);
        }


        // Sinon renvoyer la page complète (index) qui inclura le fragment dans son corps
        return $this->render('fiche_matiere_v2/modifier.html.twig', array_merge($parameters, [
            'tab' => $tabView
        ]));
    }
}
