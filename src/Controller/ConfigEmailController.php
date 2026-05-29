<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ConfigEmailController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/10/2025 09:05
 */

namespace App\Controller;

use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessChangeRf;
use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use App\Repository\EmailTemplateRepository;
use App\Service\EmailTemplateRenderer;
use App\Service\VariableRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/config/email')]
class ConfigEmailController extends BaseController
{

    public function __construct(
        private VariableRegistry      $varRegistry,
        private GetAvailableWorkflows $getAvailableWorkflows
    )
    {
    }

    #[Route('/', name: 'app_config_email')]
    public function index(EmailTemplateRepository $repo)
    {
        $templates = $repo->findBy([], ['workflow' => 'ASC']);

        return $this->render('config_email/index.html.twig', [
            'templates' => $templates,
        ]);
    }

    #[Route('/new', name: 'admin_email_template_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $tpl = new EmailTemplate();
        $tpl->setAvailableVariables($this->varRegistry->describeAll());

        $form = $this->createForm(EmailTemplateType::class, $tpl, [
            'template_workflows' => $this->getAvailableWorkflows->availableWorkflows(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($tpl);
            $em->flush();
            $this->addFlash('success', 'Template créé.');
            return $this->redirectToRoute('app_config_email');
        }

        return $this->render('config_email/new.html.twig', [
            'form' => $form,
            'template' => $tpl,
            'variables' => $tpl->getAvailableVariables(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_email_template_edit', methods: ['GET', 'POST'])]
    public function edit(EmailTemplate $tpl, Request $request, EntityManagerInterface $em): Response
    {
        // Toujours remettre à jour la liste de variables exposées (utile si de nouveaux providers)
        $tpl->setAvailableVariables($this->varRegistry->describeAll());

        $form = $this->createForm(EmailTemplateType::class, $tpl, [
            'template_workflows' => $this->getAvailableWorkflows->availableWorkflows(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Template mis à jour.');
            return $this->redirectToRoute('app_config_email');
        }

        return $this->render('config_email/edit.html.twig', [
            'form' => $form,
            'template' => $tpl,
            'variables' => $tpl->getAvailableVariables(),
        ]);
    }

    #[Route('/{id}', name: 'admin_email_template_delete', methods: ['DELETE'])]
    public function delete(EmailTemplate $tpl, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_email_template_' . $tpl->getId(), $request->request->get('_token'))) {
            $em->remove($tpl);
            $em->flush();
            $this->addFlash('success', 'Template supprimé.');
        }
        return $this->redirectToRoute('app_config_email');
    }

    /**
     * Preview live: renvoie subject/html/text rendus en mode 'preview'
     */
    #[Route('/preview/render', name: 'admin_email_template_preview', methods: ['POST'])]
    public function preview(
        Request               $request,
        EmailTemplateRenderer $renderer
    ): JsonResponse
    {
        $subject = (string)$request->request->get('subject', '');
        $subjectVariant = $request->request->get('subjectVariant');
        $bodyHtml = (string)$request->request->get('bodyHtml', '');
        $bodyText = $request->request->get('bodyText');


        $tpl = (new EmailTemplate())
            ->setSubject($subject)
            ->setBodyHtml($bodyHtml)
            ->setBodyText($bodyText ?: null)
            ->setWorkflow((string)$request->request->get('workflow', 'preview.workflow'));

        if ($jsonSubjects = $request->request->get('subjects')) {
            $decoded = json_decode((string)$jsonSubjects, true) ?: [];
            if (is_array($decoded)) {
                // filtre sécurité: valeurs string uniquement
                $decoded = array_filter($decoded, fn($v) => is_string($v));
                $tpl->setSubjects($decoded);
            }
        }

        // Overrides optionnels envoyés par l’UI (JSON)
        $overrides = [];
        if ($json = $request->request->get('overrides')) {
            $overrides = json_decode((string)$json, true) ?: [];
        }

        $out = $renderer->render($tpl, context: [], mode: 'preview', previewOverrides: $overrides, subjectVariant: $subjectVariant);
        return new JsonResponse($out);
    }
}
