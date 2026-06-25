<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ImpersonationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/04/2026 15:23
 */

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DataTableBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/impersonation')]
#[IsGranted('ROLE_ADMIN')]
class ImpersonationController extends BaseController
{
    private const USERS_PER_PAGE = 50;

    public function __construct(
        private readonly UserRepository $userRepository,
    )
    {
    }

    #[Route('', name: 'app_impersonation_list', methods: ['GET'])]
    public function list(DataTableBuilder $builder): Response
    {
        $table = $builder
            ->setEntity(User::class)
            ->setPerPage(50)
            ->setDefaultSort('nom')
            ->addBaseWhere('e.isEnable = :isEnable')
            ->addBaseWhere('e.isDeleted = :isDeleted')
            ->addBaseParameter('isEnable', true)
            ->addBaseParameter('isDeleted', false)
            ->addColumn('username', [
                'label' => 'Login',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('nom', [
                'label' => 'Nom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('prenom', [
                'label' => 'Prénom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('email', [
                'label' => 'Email',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('roles', [
                'label' => 'Rôles',
                'sortable' => false,
                'filterable' => false,
                'template' => 'impersonation/_column_roles.html.twig',
            ])
            ->addColumn('id', [
                'label' => 'Action',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'impersonation/_column_actions.html.twig',
            ]);

        return $this->render('impersonation/list.html.twig', [
            'table' => $table->build(),
        ]);
    }

}







