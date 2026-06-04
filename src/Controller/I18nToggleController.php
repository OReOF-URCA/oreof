<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/I18nToggleController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/10/2025 10:02
 */

// src/Controller/I18nToggleController.php
namespace App\Controller;

use App\I18n\KeyModeContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

final class I18nToggleController extends AbstractController
{
    #[Route('/_i18n/toggle-keys', name: 'i18n_toggle_keys')]
    public function __invoke(
        RequestStack   $requestStack,
        KeyModeContext $ctx): RedirectResponse
    {
        $enabled = !$ctx->isEnabled();
        $ctx->setEnabled($enabled);
        $this->addFlash('info', $enabled ? 'Mode clés activé' : 'Mode clés désactivé');

        return $this->redirect($this->referer($requestStack) ?? $this->generateUrl('app_homepage'));
    }

    private function referer(RequestStack $requestStack): ?string
    {
        return $requestStack->getCurrentRequest()?->headers->get('referer');
    }
}
