<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/ImpersonationExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/04/2026 15:24
 */

namespace App\Twig;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImpersonationExtension extends AbstractExtension
{
    public function __construct(
        private readonly TokenStorageInterface         $tokenStorage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_impersonating', $this->isImpersonating(...)),
            new TwigFunction('get_impersonated_user_id', $this->getImpersonatedUserId(...)),
            new TwigFunction('get_original_user_id', $this->getOriginalUserId(...)),
        ];
    }

    public function isImpersonating(): bool
    {
        if ($this->authorizationChecker->isGranted('IS_IMPERSONATOR')) {
            return true;
        }

        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }

    public function getImpersonatedUserId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return null;
        }

        return $this->extractUserId($token->getUser());
    }

    private function extractUserId(mixed $user): ?int
    {
        if (!is_object($user) || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();

        return is_int($id) ? $id : null;
    }

    public function getOriginalUserId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        return $this->extractUserId($token->getOriginalToken()->getUser());
    }
}
