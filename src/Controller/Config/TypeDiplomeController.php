<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeDiplomeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\BaseController;
use App\DTO\TranslatableKey;
use App\Entity\TypeDiplome;
use App\Form\TypeDiplomeType;
use App\Service\DetailBuilder;
use App\Repository\TypeDiplomeRepository;
use App\Service\DataTableBuilder;
use App\Service\TypeDiplomePlateformeService;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-diplome')]
class TypeDiplomeController extends BaseController
{
    #[Route('/', name: 'app_type_diplome_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(TypeDiplome::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du type de diplôme',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('libelleCourt', [
                'label' => 'Sigle',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeApogee', [
                'label' => 'Code Apogée',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('hasMemoire', [
                'label' => 'Mémoire ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasStage', [
                'label' => 'Stage ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasProjet', [
                'label' => 'Projet ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasSituationPro', [
                'label' => 'Situation Pro. ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('ectsObligatoireSurEc', [
                'label' => 'ECTS Obli. ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addShowAction('app_type_diplome_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type de diplôme',
            ])
            ->addEditAction('app_type_diplome_edit', [
                'modal' => false,
            ])
            ->addDuplicateAction('app_type_diplome_duplicate')
            ->addDeleteAction('app_type_diplome_delete')
            ->build();
        return $this->render('config/type_diplome/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_type_diplome_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TypeDiplomeRepository        $typeDiplomeRepository,
        TypeDiplomePlateformeService $typeDiplomePlateformeService
    ): Response
    {
        $typeDiplome = new TypeDiplome();
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_type_diplome_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeDiplomeRepository->save($typeDiplome, true);

            // Gérer les plateformes d'admission avec leurs années
            $plateformesData = $form->get('plateformesAdmission')->getData();
            if ($plateformesData) {
                $typeDiplomePlateformeService->syncPlateformes($typeDiplome, $plateformesData, $this->getCampagneCollecte());
            }

            // Abandon de la fenêtre modale
            // return $this->json(true);

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Création du type de diplôme réussie',
                'title' => 'Succès',
            ]);
            return $this->redirectToRoute('app_type_diplome_index');
        }

        return $this->render('config/type_diplome/new.html.twig', [
            'type_diplome' => $typeDiplome,
            'form' => $form->createView(),
            'titre' => "Création d'un type de diplôme"
        ]);
    }

    #[Route('/{id}', name: 'app_type_diplome_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        TypeDiplome                $typeDiplome,
        DetailBuilder              $builder
    ): Response
    {
        $detail = $builder
            ->setEntity(TypeDiplome::class)
            ->addField('libelle', [
                'label' => 'Libellé du type de diplôme',
            ])
            ->addField('libelleCourt', [
                'label' => 'Sigle',
            ])
            ->addField('codeApogee', [
                'label' => 'Code Apogée',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('hasMemoire', [
                'label' => 'Mémoire ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasStage', [
                'label' => 'Stage ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasProjet', [
                'label' => 'Projet ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasSituationPro', [
                'label' => 'Situation Pro. ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('ectsObligatoireSurEc', [
                'label' => 'ECTS obligatoires sur EC ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('modalitesAdmission', [
                'label' => 'Modalités d’admission',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('presentationFormation', [
                'label' => 'Présentation des formations',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('prerequisObligatoires', [
                'label' => 'Prérequis obligatoires',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('insertionProfessionnelle', [
                'label' => 'Devenir des diplômés',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addCustomField('plateformes', function ($typeDiplome) {
                $plateformes = $typeDiplome->getTypeDiplomePlateformeAdmissions();
                if ($plateformes->isEmpty()) {
                    return 'Aucune plateforme définie';
                }

                $html = '<ul class="list-disc pl-5">';
                foreach ($plateformes as $tpa) {
                    $plateforme = $tpa->getPlateforme();
                    $annees = $tpa->getAnnees();

                    $anneesText = '';
                    if ($annees && count($annees) > 0) {
                        sort($annees);
                        $anneesText = ' (Année' . (count($annees) > 1 ? 's' : '') . ' ' . implode(', ', $annees) . ')';
                    }

                    $html .= '<li>' . $plateforme?->getLibelle() . $anneesText . '</li>';
                }
                $html .= '</ul>';

                return $html;
            }, [
                'label' => 'Plateformes d\'admission configurées',
                'format' => 'html',
            ])
            ->build();


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_diplome.show.title', [], 'modal'),
            'Dans : type diplôme ' . $typeDiplome->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $typeDiplome,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_type_diplome_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeDiplome $typeDiplome,
        TypeDiplomeRepository        $typeDiplomeRepository,
        TypeDiplomePlateformeService $typeDiplomePlateformeService
    ): Response
    {
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_type_diplome_edit', ['id' => $typeDiplome->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeDiplomeRepository->save($typeDiplome, true);

            // Gérer les plateformes d'admission avec leurs années
            $plateformesData = $form->get('plateformesAdmission')->getData();
            $typeDiplomePlateformeService->syncPlateformes($typeDiplome, $plateformesData ?? [], $this->getCampagneCollecte());

            // Abandon de la fenêtre modale
            // return $this->json(true);
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Type Diplôme modifié avec succès',
                'title' => 'Succès',
            ]);
            return $this->redirectToRoute('app_type_diplome_index');
        }

        return $this->render('config/type_diplome/new.html.twig', [
            'type_diplome' => $typeDiplome,
            'form' => $form->createView(),
            'titre' => "Modification d'un type de diplôme"
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_type_diplome_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeDiplomeRepository $typeDiplomeRepository,
        TypeDiplome $typeDiplome
    ): Response {
        $typeDiplomeNew = clone $typeDiplome;
        $typeDiplomeNew->setLibelle($typeDiplome->getLibelle() . ' - Copie');
        $typeDiplomeRepository->save($typeDiplomeNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_diplome_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeDiplome $typeDiplome,
        TypeDiplomeRepository $typeDiplomeRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeDiplome->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeDiplomeRepository->remove($typeDiplome, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
