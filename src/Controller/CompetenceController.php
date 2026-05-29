<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/CompetenceController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Bcc;
use App\Entity\BlocCompetence;
use App\Entity\Competence;
use App\Form\CompetenceType;
use App\Repository\CompetenceRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/competence')]
class CompetenceController extends AbstractController
{
    #[Route('/new/{bcc}', name: 'app_competence_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CompetenceRepository $competenceRepository, BlocCompetence $bcc): Response
    {
        $competence = new Competence($bcc);
        $form = $this->createForm(
            CompetenceType::class,
            $competence,
            [
                'action' => $this->generateUrl(
                    'app_competence_new',
                    [
                        'bcc' => $bcc->getId(),
                        'ordre' => $request->query->get('ordre'),
                    ]
                )
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->query->get('ordre') === null) {
                //pas d'ordre donc on ajoute à la fin
                $ordre = $competenceRepository->getMaxOrdreBlocCompetence($bcc);
                $competence->setOrdre($ordre + 1);
            } else {
                //on décale les autres compétences
                $competenceRepository->decaleCompetence(
                    $bcc,
                    $request->query->get('ordre'),
                );
                $competence->setOrdre($request->query->get('ordre'));
            }


            $competence->genereCode();
            $competenceRepository->save($competence, true);

            return $this->json(true);
        }

        return $this->render('competence/new.html.twig', [
            'competence' => $competence,
            'bcc' => $bcc,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_competence_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Competence $competence, CompetenceRepository $competenceRepository): Response
    {
        $form = $this->createForm(
            CompetenceType::class,
            $competence,
            ['action' => $this->generateUrl('app_competence_edit', ['id' => $competence->getId()])]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $competenceRepository->save($competence, true);

            return $this->json(true);
        }

        return $this->render('competence/new.html.twig', [
            'competence' => $competence,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_competence_duplicate', methods: ['GET'])]
    public function duplicate(
        CompetenceRepository $competenceRepository,
        Competence $competence
    ): Response {
        $competenceNew = clone $competence;
        $competenceNew->setLibelle($competence->getLibelle() . ' - Copie');
        $ordre = $competenceRepository->getMaxOrdreBlocCompetence($competence->getBlocCompetence());
        $competenceNew->setOrdre($ordre + 1);
        $competenceNew->genereCode();
        $competenceRepository->save($competenceNew, true);

        return $this->json(true);
    }

    #[Route('/{id}/deplacer/{sens}', name: 'app_competence_deplacer', methods: ['GET'])]
    public function deplacer(
        Bcc $bcc,
        Competence $competence,
        string $sens
    ): Response {
        $bcc->deplacerCompetence($competence, $sens);

        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_competence_delete', methods: ['DELETE'])]
    public function delete(
        Bcc $bcc,
        Request $request,
        Competence $competence,
        CompetenceRepository $competenceRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $competence->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $bc = $competence->getBlocCompetence();
            $competenceRepository->remove($competence, true);
            $bcc->renumeroteCompetences($bc);
            return $this->json(true);
        }

        return $this->json(false);
    }
}
