<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/ComposanteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Classes\AddUser;
use App\DTO\TranslatableKey;
use App\Entity\Composante;
use App\Entity\User;
use App\Form\ComposanteType;
use App\Repository\ComposanteRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/composante')]
class ComposanteController extends AbstractController
{
    #[Route('/', name: 'app_composante_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Composante::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Nom de la composante',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('sigle', [
                'label' => 'Sigle',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeComposante', [
                'label' => 'Code compo.',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeApogee', [
                'label' => 'Code CIP (Apogée)',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('directeur', [
                'label' => 'Directeur',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => User::class,
            ])
            ->addColumn('responsableDpe', [
                'label' => 'Responsable DPE',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => User::class,
            ])
            ->addShowAction('app_composante_show', [
                'modal' => true,
                'modal_title' => 'Voir une composante',
            ])
            ->addEditAction('app_composante_edit', [
                'modal' => true,
                'modal_title' => 'Modifier une composante',
            ])
//            ->addDuplicateAction('app_composante_duplicate')
            ->addDeleteAction('app_composante_delete')
            ->build();
        return $this->render('config/composante/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_composante_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        AddUser $addUser,
        Request $request,
        ComposanteRepository $composanteRepository
    ): Response
    {
        $composante = new Composante();
        $form = $this->createForm(ComposanteType::class, $composante, [
            'action' => $this->generateUrl('app_composante_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {//&& $form->isValid()
            if ($composante->getDirecteur() === null) {
                $usr = $addUser->addUser($request->request->get('directeur_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_LECTEUR');
                    $addUser->setCentreComposante($usr, $composante);
                    $composante->setDirecteur($usr);
                }
            }

            if ($composante->getResponsableDpe() === null) {
                $usr = $addUser->addUser($request->request->get('responsableDpe_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_USER');
                    $addUser->setCentreComposante($usr, $composante, 'ROLE_DPE_COMPOSANTE');
                    $composante->setResponsableDpe($usr);
                }
            }

            $composanteRepository->save($composante, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('composante.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'composante' => $composante,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_composante_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Composante                 $composante
    ): Response
    {
        $detail = $builder
            ->setEntity(Composante::class)
            ->addField('libelle', ['label' => 'Nom de la composante'])
            ->addField('sigle', ['label' => 'Sigle', 'empty_text' => 'Non renseigné'])
            ->addField('codeComposante', ['label' => 'Code composante', 'empty_text' => 'Non renseigné'])
            ->addField('codeApogee', ['label' => 'Code CIP (Apogée)', 'empty_text' => 'Non renseigné'])
            ->addField('directeur', [
                'label' => 'Directeur',
                'type' => 'entity',
                'format' => 'entity_badge',
                'entity_label' => 'display',
                'badge_class' => 'bg-info',
                'null_label' => 'Non défini',
                'null_badge_class' => 'bg-secondary',
            ])
            ->addField('responsableDpe', [
                'label' => 'Responsable DPE',
                'type' => 'entity',
                'format' => 'entity_badge',
                'entity_label' => 'display',
                'badge_class' => 'bg-info',
                'null_label' => 'Non défini',
                'null_badge_class' => 'bg-secondary',
            ])
            ->addField('adresse.display', [
                'label' => 'Adresse',
                'format' => 'html',
                'empty_text' => 'Non définie',
            ])
            ->addField('telStandard', ['label' => 'Téléphone standard', 'empty_text' => 'Non renseigné'])
            ->addField('telComplementaire', ['label' => 'Téléphone complémentaire', 'empty_text' => 'Non renseigné'])
            ->addField('mailContact', ['label' => 'Email de contact', 'empty_text' => 'Non renseigné'])
            ->addField('urlSite', ['label' => 'Site web', 'empty_text' => 'Non renseigné'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('composante.show.title', [], 'modal'),
            'Composante : ' . $composante->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $composante,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_composante_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        AddUser                    $addUser,
        Request                    $request,
        Composante                 $composante,
        ComposanteRepository       $composanteRepository
    ): Response
    {
        $form = $this->createForm(ComposanteType::class, $composante, [
            'action' => $this->generateUrl('app_composante_edit', ['id' => $composante->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) { //&& $form->isValid()
            if ($composante->getDirecteur() === null) {
                $usr = $addUser->addUser($request->request->get('directeur_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_LECTEUR');
                    $addUser->setCentreComposante($usr, $composante);
                    $composante->setDirecteur($usr);
                }
            }

            if ($composante->getResponsableDpe() === null) {
                $usr = $addUser->addUser($request->request->get('responsableDpe_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_USER');
                    $addUser->setCentreComposante($usr, $composante, 'ROLE_DPE_COMPOSANTE');
                    $composante->setResponsableDpe($usr);
                }
            }

            $composanteRepository->save($composante, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('composante.edit.title', [], 'modal'),
            'Composante : ' . $composante->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'composante' => $composante,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_composante_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Composante $composante, ComposanteRepository $composanteRepository): Response
    {

        if ($this->isCsrfTokenValid('delete'.$composante->getId(), JsonRequest::getValueFromRequest($request, 'csrf'))) {
            $composanteRepository->remove($composante, true);
        }

        return $this->redirectToRoute('app_composante_index', [], Response::HTTP_SEE_OTHER);
    }
}
