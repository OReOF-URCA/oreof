<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/DefaultController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/02/2023 16:34
 */

namespace App\Controller;

use App\DTO\StatsFichesMatieres;
use App\DTO\TranslatableKey;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends BaseController
{
    #[Route('/', name: 'app_homepage')]
    public function index(
        Request $request
    ): Response {
        return $this->render(
            'default/index.html.twig',
            [
                'step' => $request->query->get('step', 'formation'),
            ]
        );
    }

    #[Route('/usage-donnees', name: 'app_usage_donnees')]
    public function usageDonnees(
        TurboStreamResponseFactory $turboStream
    ): Response
    {

        return $turboStream->streamOpenModalFromTemplates(
            'Usage des données',
            '',
            'default/_usage_donnees.html.twig',
            [
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/accessibilite', name: 'app_accessibilite')]
    public function accessibilite(
        TurboStreamResponseFactory $turboStream
    ): Response
    {
        return $turboStream->streamOpenModalFromTemplates(
            'Accessibilite',
            'Adapter l affichage et les interactions selon vos besoins',
            'default/_accessibilite.html.twig',
            [],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/wizard', name: 'app_homepage_wizard')]
    public function wizard(
        ComposanteRepository $composanteRepository,
        FormationRepository $formationRepository,
        Request                    $request,
        TurboStreamResponseFactory $turboStream
    ): Response {
        $step = $request->query->get('step', 'formation');

        $canAccessCfvu = $this->isGranted('MANAGE', ['route' => 'app_etablissement', 'subject' => 'etablissement']);
        if ($step === 'cfvu' && !$canAccessCfvu) {
            $step = 'formation';
        }

        $template = 'default/_formation.html.twig';
        $context = ['step' => $step];

        switch ($step) {
            case 'fiche':
                if ($this->isGranted('ROLE_ADMIN')) {
                    $template = 'default/_fichesSes.html.twig';
                    $context['composantes'] = $composanteRepository->findPorteuse();
                    $context['composanteFormationCounts'] = $formationRepository->countByComposanteForCampagne($this->getCampagneCollecte());
                    break;
                }

                $template = 'default/_fiches.html.twig';
                break;

            case 'cfvu':
                $template = 'default/_cfvu.html.twig';
                break;

            case 'formation':
            default:
            $template = 'default/_formation.html.twig';
            break;
        }

        $accept = $request->headers->get('Accept', '');
        if (str_contains($accept, 'text/vnd.turbo-stream.html')) {
            return $turboStream->stream('default/turbo/wizard_step.stream.html.twig', array_merge($context, [
                'partial' => $template,
            ]));
        }

        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('default/_wizard_frame.html.twig', array_merge($context, [
                'partial' => $template,
            ]));
        }

        return $this->render($template, $context);
    }

    #[Route('/ses/fiches-composante', name: 'app_fiches_composantes')]
    public function fichesComposante(
        TypeDiplomeResolver $typeDiplomeResolver,
        ComposanteRepository   $composanteRepository,
        FormationRepository $formationRepository,
        Request               $request,
    ): Response {
        $composante = $composanteRepository->find($request->query->get('value'));

        if ($composante === null) {
            throw $this->createNotFoundException('La composante n\'existe pas');
        }

        $tFormations = $formationRepository->findByComposanteAndDpe($composante->getId(), $this->getCampagneCollecte());

        $stats = [];
        foreach ($tFormations as $formation) {
            $typeD = $typeDiplomeResolver->fromTypeDiplome($formation->getTypeDiplome());
            $parcourss = $formation->getParcours();
            $stats[$formation->getId()]['stats'] = new StatsFichesMatieres();

            foreach ($parcourss as $parcours) {
                $stats[$formation->getId()][$parcours->getId()] = $typeD->calcul($parcours);
                $stats[$formation->getId()]['stats']->addStatsParcours(
                    $stats[$formation->getId()][$parcours->getId()]->statsFichesMatieresParcours
                );
            }
        }

        return $this->render('default/_fiches_composante.html.twig', [
            'formations' => $tFormations,
            'stats' => $stats
        ]);

    }
}
