<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationWizardController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 14:22
 */

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Form\FormationStep1Type;
use App\Form\FormationStep2Type;
use App\Form\Type\TextareaAutoSaveType;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\Access;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation/step')]
class FormationWizardController extends AbstractController
{
    #[Route('/', name: 'app_formation_wizard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('formation/index.html.twig');
    }

    #[Route('/{formation}/1', name: 'app_formation_wizard_step_1', methods: ['GET'])]
    public function step1(Formation $formation): Response
    {
        $form = $this->createForm(FormationStep1Type::class, $formation);

        return $this->render('formation_wizard/_step1.html.twig', [
            'form' => $form->createView(),
            'formation' => $formation,
        ]);
    }

    #[Route('/{formation}/2', name: 'app_formation_wizard_step_2', methods: ['GET'])]
    public function step2(Formation $formation): Response
    {

        $form = $this->createForm(FormationStep2Type::class, $formation);


        return $this->render('formation_wizard/_step2.html.twig', [
            'form' => $form->createView(),
            'formation' => $formation,

        ]);
    }

    /**
     * @throws TypeDiplomeNotFoundException
     */
    #[Route('/{formation}/3', name: 'app_formation_wizard_step_3', methods: ['GET'])]
    public function step3(
        Formation $formation
    ): Response {

        if (!Access::isAccessibleMention($formation, 'cfvu')) {
            return $this->render('parcours_wizard/_access_denied.html.twig');
        }

        $form = $this->createFormBuilder($formation)
            ->add('objectifsFormation', TextareaAutoSaveType::class, [
                'required' => true,
                'attr' => ['maxlength' => 3000, 'data-action' => 'change->formation--step3#saveObjectifsFormation'],
                'help' => '-',
                'translation_domain' => 'form',
            ])
            ->getForm();

        return $this->render('formation_wizard/_step3.html.twig', [
            'formation' => $formation,
            'typeDiplome' => $formation->getTypeDiplome(),
            'form' => $form->createView(),
        ]);
    }
}
