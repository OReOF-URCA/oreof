<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/EcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Controller\BaseController;
use App\Entity\FicheMatiere;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/fiche-matiere', name: 'structure_fiche_matiere_')]
class FicheMatiereController extends BaseController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request
    ): Response {
        return $this->render(
            'structure/fiche_matiere/index.html.twig',
            [
                'type' => $request->query->get('type', 'parcours'),
                'page' => $request->query->get('page', 1),
            ]
        );
    }

    #[Route('/liste', name: 'liste')]
    public function liste(
//        UserRepository $userRepository,
//        Request $request
    ): Response {
        // Déterminer les conditions de base selon les droits
        $baseWheres = ['e.campagneCollecte = :campagne'];
        $baseParameters = ['campagne' => $this->getCampagneCollecte()];
        $baseJoins = [];

        // Exclure hors diplôme pour la vue principale
        $baseWheres[] = 'e.horsDiplome = 0 OR e.horsDiplome IS NULL';

        if ($this->isGranted('ROLE_ADMIN')) {
            // Admin : pas de restriction
        } elseif ($this->getUser()->getComposanteResponsableDpe()->count() > 0) {
            // Responsable de DPE : voir les fiches de sa composante
            $baseJoins[] = [
                'type' => 'inner',
                'path' => 'e.parcours',
                'alias' => 'dpe_parcours'
            ];
            $baseJoins[] = [
                'type' => 'inner',
                'path' => 'dpe_parcours.formation',
                'alias' => 'dpe_formation'
            ];
            $baseJoins[] = [
                'type' => 'inner',
                'path' => 'dpe_formation.composante',
                'alias' => 'dpe_composante'
            ];
            $baseWheres[] = 'dpe_composante IN (:dpeComposantes)';
            $baseParameters['dpeComposantes'] = $this->getUser()->getComposanteResponsableDpe();
        } else {
            // Responsable pédagogique : voir ses propres fiches
            $baseWheres[] = 'e.responsableFicheMatiere = :responsable';
            $baseParameters['responsable'] = $this->getUser();
        }

        $config = $this->buildDatatableConfig(false, $baseJoins, $baseWheres, $baseParameters);

        return $this->render('structure/fiche_matiere/_liste.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/liste/hors-diplome', name: 'liste_hd')]
    public function listeHorsDiplome(
        UserRepository $userRepository,
        Request $request
    ): Response {
        $baseWheres = ['e.campagneCollecte = :campagne', 'e.horsDiplome = 1'];
        $baseParameters = ['campagne' => $this->getCampagneCollecte()];
        $baseJoins = [];

        $config = $this->buildDatatableConfig(true, $baseJoins, $baseWheres, $baseParameters);

        return $this->render('structure/fiche_matiere/_liste.html.twig', [
            'config' => $config,
        ]);
    }

    //    #[Route('/detail/ue/{ue}/{parcours}', name: 'detail_ue')]
    //    public function detailComposante(
    //        ElementConstitutifRepository $elementConstitutifRepository,
    //        Ue $ue,
    //    ): Response {
    //        $ecs = $elementConstitutifRepository->findByUe($ue);
    //
    //        $config = $this->buildDatatableConfig(true, [], ['e.ue = :ue'], ['ue' => $ue]);
    //
    //        return $this->render('structure/fiche_matiere/_liste.html.twig', [
    //            'config' => $config,
    //        ]);
    //    }

    /**
     * Construire la configuration partagée du datatable pour les fiches matière
     */
    private function buildDatatableConfig(bool $horsDiplome = false, array $baseJoins = [], array $baseWheres = [], array $baseParameters = []): array
    {
        $columns = [
            [
                'id' => 'libelle',
                'field' => 'libelle',
                'label' => 'Fiche matière',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'class' => 'w-2/5',
                'template' => 'structure/fiche_matiere/_column_libelle.html.twig',
            ],
        ];

        if (!$horsDiplome) {
            $columns[] = [
                'id' => 'parcours',
                'field' => 'parcours.libelle',
                'label' => 'Parcours',
                'filterable' => true,
                'searchable' => false,
                'entity' => 'App\Entity\Parcours',
                'entity_label' => 'libelle',
                'type' => 'entity',
                'filter_expression' => 'e.parcours',
                'class' => 'w-1/5',
            ];
        }

        $columns = array_merge($columns, [
            [
                'id' => 'etatFiche',
                'field' => 'etatFiche',
                'label' => 'État',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'class' => 'text-center w-1/6',
                'template' => 'structure/fiche_matiere/_column_etat.html.twig',
            ],
            [
                'id' => 'utilise',
                'field' => 'elementConstitutifs',
                'label' => 'Utilisé',
                'sortable' => false,
                'filterable' => true,
                'searchable' => false,
                'type' => 'boolean',
                'class' => 'text-center w-1/12',
                'template' => 'structure/fiche_matiere/_column_utilise.html.twig',
                'filter_expression' => '(SELECT COUNT(ec.id) FROM App\Entity\ElementConstitutif ec WHERE ec.ficheMatiere = e) > 0',
            ],
            [
                'id' => 'referent',
                'field' => 'responsableFicheMatiere.display',
                'label' => 'Référent',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'entity' => 'App\Entity\User',
                'entity_label' => 'display',
                'type' => 'entity',
                'null_label' => 'Non défini',
                'filter_expression' => 'e.responsableFicheMatiere',
                'class' => 'w-1/6',
                'template' => 'structure/fiche_matiere/_column_referent.html.twig',
            ],
            [
                'id' => 'remplissage',
                'field' => 'remplissage',
                'label' => 'Remplissage',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => ['all' => 'Tous', '0' => 'Non complété', '100' => 'Complet'],
                'filter_expression' => 'CAST(JSON_EXTRACT(e.remplissage, \'$.pourcentage\') AS UNSIGNED)',
                'class' => 'w-1/6',
                'template' => 'structure/fiche_matiere/_column_remplissage.html.twig',
            ]]);


        // Colonne Actions - avec template personnalisé (pas de field)
        $columns[] = [
            'id' => 'actions',
            'label' => 'Actions',
            'sortable' => false,
            'filterable' => false,
            'searchable' => false,
            'class' => 'text-right w-1/6',
            'template' => 'structure/fiche_matiere/_column_actions.html.twig',
        ];

        return [
            'entityClass' => FicheMatiere::class,
            'columns' => $columns,
            'actions' => [],
            'baseJoins' => $baseJoins,
            'baseWheres' => $baseWheres,
            'baseParameters' => $baseParameters,
            'sortField' => 'libelle',
            'sortDirection' => 'asc',
            'perPage' => 50,
            'filtersOpen' => false,
        ];
    }
}
