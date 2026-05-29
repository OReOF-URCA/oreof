<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/API/UserController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\API;

use App\Controller\BaseController;
use App\Repository\UserProfilRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user')]
class UserController extends BaseController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/api/user', name: 'api_user_get_user', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function user(Request $request): Response
    {
        $user = $this->userRepository->find($request->query->get('id'));

        return $this->render('api/user/_user.html.twig', [
            'user' => $user,
        ]);
    }
}
