<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/AnneeUniversitaireController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;
use App\Entity\User;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\ConfigurationPublicationEnum;
use App\Form\CampagneCollecteType;
use App\Form\ConfigurePublicationType;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/campagne-collecte')]
class CampagneCollecteController extends AbstractController
{
    #[Route('/', name: 'app_campagne_collecte_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder->setEntity(CampagneCollecte::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')
            ->addColumn('libelle', [
                'label' => 'Libellé de la campagne de collecte',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('anneeUniversitaire', [
                'label' => 'Année Universitaire',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => AnneeUniversitaire::class,
            ])
            ->addColumn('timelineDates', [
                'label' => 'Dates',
                'searchable' => false,
                'template' => 'config/campagne_collecte/_datatable_timeline.html.twig',
            ])
            ->addColumn('defaut', [
                'label' => 'Collecte DPE active ?',
                'searchable' => false,
                'template' => 'config/campagne_collecte/_datatable_defaut.html.twig',
            ])
            ->addColumn('enablePublication', [
                'label' => 'Configuration de la publication',
                'searchable' => false,
                'template' => 'config/campagne_collecte/_datatable_publication.html.twig',
            ])
            ->addShowAction('app_campagne_collecte_show', [
                'modal' => true,
                'modal_title' => 'Voir une campagne de collecte',
            ])
            ->addEditAction('app_campagne_collecte_edit', [
                'modal' => true,
                'modal_title' => 'Modifier une campagne de collecte',
            ])
            ->addDuplicateAction('app_campagne_collecte_duplicate')
            ->addDeleteAction('app_campagne_collecte_delete')
            ->addAction('configure_publication', [
                'label' => 'Paramétrer',
                'route' => 'app_campagne_collecte_configure_publication',
                'icon' => 'fa-light fa-wrench',
                'class' => 'inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100',
                'modal' => true,
                'modal_title' => 'Paramétrer les options de publication',
            ])
            ->build();


        return $this->render('config/campagne_collecte/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/liste', name: 'app_campagne_collecte_liste', methods: ['GET'])]
    public function liste(CampagneCollecteRepository $campagneCollecteRepository): Response
    {
        return $this->render('config/campagne_collecte/_liste.html.twig', [
            'campagne_collectes' => $campagneCollecteRepository->findAll(),
            'libelleConfigurationPublication' => [
                ConfigurationPublicationEnum::MAQUETTE->value => 'Maquette des enseignements',
                ConfigurationPublicationEnum::MCCC->value => 'MCCC (PDF)'
            ]
        ]);
    }

    #[Route('/new', name: 'app_campagne_collecte_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response
    {
        $campagne_collecte = new CampagneCollecte();
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            ['action' => $this->generateUrl('app_campagne_collecte_new')]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagneCollecteRepository->save($campagne_collecte, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('campagne_collecte.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'campagne_collecte' => $campagne_collecte,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_campagne_collecte_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        CampagneCollecte           $campagne_collecte): Response
    {
        $detail = $builder
            ->setEntity(CampagneCollecte::class)
            ->addField('id', ['label' => 'ID'])
            ->addField('libelle', ['label' => 'Libellé'])
            ->addField('anneeUniversitaire', [
                'label' => 'Année universitaire',
                'type' => 'entity',
                'entity' => AnneeUniversitaire::class,
                'entity_label' => 'libelle',
                'empty_text' => 'Non renseignée',
            ])
            ->addField('annee', ['label' => 'Année'])
            ->addField('couleur', ['label' => 'Couleur'])
            ->addField('codeApogee', ['label' => 'Code Apogée', 'empty_text' => 'Non renseigné'])
            ->addField('defaut', ['label' => 'Collecte DPE active ?', 'format' => 'boolean'])
            ->addField('mailDpeEnvoye', ['label' => 'Mail DPE envoyé ?', 'format' => 'boolean'])
            ->addField('dateOuvertureDpe', ['label' => 'Date ouverture DPE', 'format' => 'datetime'])
            ->addField('dateClotureDpe', ['label' => 'Date clôture DPE', 'format' => 'datetime'])
            ->addField('dateTransmissionSes', ['label' => 'Date transmission SES', 'format' => 'datetime'])
            ->addField('dateCfvu', ['label' => 'Date CFVU', 'format' => 'datetime'])
            ->addField('datePublication', ['label' => 'Date publication', 'format' => 'datetime'])
            ->addField('timelineDates.count()', ['label' => 'Nombre d\'évènements timeline'])
            ->addField('timelineDates', [
                'label' => 'Évènements timeline',
                'type' => 'collection',
                'collection_property' => 'libelle',
                'separator' => ' | ',
                'empty_text' => 'Aucun évènement',
            ])
            ->addField('enablePublication', ['label' => 'Publication activée ?', 'format' => 'boolean'])
            ->addField('publicationTag', [
                'label' => 'Tag de publication',
                'empty_text' => 'Non défini',
            ])
            ->addField('publicationOptions', [
                'label' => 'Options de publication (valeurs)',
                'type' => 'collection',
                'separator' => ' / ',
                'empty_text' => 'Aucune option',
            ])
            ->addField('isFinished', ['label' => 'Campagne consolidée ?', 'format' => 'boolean'])
            ->addField('dpeParcours.count()', ['label' => 'Nombre de DPE parcours'])
            ->addField('changeRves.count()', ['label' => 'Nombre de changements RF'])
            ->addField('blocCompetences.count()', ['label' => 'Nombre de blocs compétences'])
            ->addField('userProfils.count()', ['label' => 'Nombre de profils utilisateurs'])
            ->addField('butCompetences.count()', ['label' => 'Nombre de compétences BUT'])
            ->addField('dpeDemandes.count()', ['label' => 'Nombre de demandes DPE'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('campagne_collecte.show.title', [], 'modal'),
            'Campagne de collecte : ' . $campagne_collecte->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $campagne_collecte,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_campagne_collecte_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request          $request,
        CampagneCollecte $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            [
                'action' => $this->generateUrl('app_campagne_collecte_edit', [
                    'id' => $campagne_collecte->getId()
                ])
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagneCollecteRepository->save($campagne_collecte, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('campagne_collecte.edit.title', [], 'modal'),
            'Campagne de collecte : ' . $campagne_collecte->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'campagne_collecte' => $campagne_collecte,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_campagne_collecte_duplicate', methods: ['GET'])]
    public function duplicate(
        CampagneCollecteRepository $campagneCollecteRepository,
        CampagneCollecte           $campagne_collecte
    ): Response {
        $campagne_collecteNew = clone $campagne_collecte;
        $campagne_collecteNew->setLibelle($campagne_collecte->getLibelle() . ' - Copie');
        $campagneCollecteRepository->save($campagne_collecteNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_campagne_collecte_delete', methods: ['DELETE'])]
    public function delete(
        Request          $request,
        CampagneCollecte $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $campagne_collecte->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $campagneCollecteRepository->remove($campagne_collecte, true);

            return $this->json(true);
        }

        return $this->json(false);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/configure/publication', name: 'app_campagne_collecte_configure_publication', methods: ['GET', 'POST'])]
    public function configurePublication(
        CampagneCollecte $campagneCollecte,
        Request $request,
        CampagneCollecteRepository $campagneRepository
    ) : Response {
        $form = $this->createForm(
            ConfigurePublicationType::class,
            $campagneCollecte,
            [
               'action' => $this->generateUrl('app_campagne_collecte_configure_publication', ['id' => $campagneCollecte->getId()]),
               'hasPublishedMccc' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MCCC->value] ?? 'none',
               'hasPublishedMaquette' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MAQUETTE->value] ?? 'none',
               'publicationTag' => $campagneCollecte->getPublicationTag() ?? 'none'
            ]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Configuration de la publication
            $configToSave = [];
            $jsonConfigValues = [
                ConfigurationPublicationEnum::MAQUETTE->value,
                ConfigurationPublicationEnum::MCCC->value
            ];
            foreach ($jsonConfigValues as $configValue) {
                if ($form->has($configValue)) {
                    $configToSave[$configValue] = $form->get($configValue)->getData();
                }
            }
            // Le tag ne peut être présent qu'une seule fois
            $publicationTag = $form->get('campagneTag')->getData();
            if (in_array($publicationTag, [CampagnePublicationTagEnum::ANNEE_COURANTE->value, CampagnePublicationTagEnum::ANNEE_SUIVANTE->value])) {
                $statusAlreadyTaken = $campagneRepository->findOneBy(['publicationTag' => $publicationTag]);
                if ($statusAlreadyTaken !== null && $statusAlreadyTaken->getId() !== $campagneCollecte->getId()) {
                    return $this->json(['message' => 'Ce statut est déjà utilisé'], 500);
                }
            }

            $campagneCollecte->setPublicationTag($publicationTag);
            $campagneCollecte->setPublicationOptions($configToSave);
            $campagneRepository->save($campagneCollecte, true);

            return $this->json(true);
        }

        return $this->render('config/campagne_collecte/_configure_publication.html.twig', [
            'campagneCollecte' => $campagneCollecte,
            'form' => $form
        ]);
    }
}
