<?php

namespace App\Service;

use App\Entity\CentreRestrictedInterface;
use App\Entity\User;
use App\Entity\Help; // Assurez-vous d'importer votre entité Help
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HelpGrantService
{
    private RouterInterface $router;
    private AuthorizationCheckerInterface $authChecker;

    // Injection du routeur et du checker de sécurité de Symfony
    public function __construct(RouterInterface $router, AuthorizationCheckerInterface $authChecker)
    {
        $this->router = $router;
        $this->authChecker = $authChecker;
    }

    public function isAllowed(CentreRestrictedInterface $entity, ?User $user = null): bool
    {
        // 1. Si l'entité est une instance de Help, on effectue la vérification automatique de la route
        if ($entity instanceof Help) {
            if (!$this->isRouteAccessible($entity, $user)) {
                return false;
            }
        }

        // 2. Votre logique actuelle (ROLE_ADMIN global & restrictions par Centres)
        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $centres = $entity->getCentresShow();
        if (!empty($centres)) {
            if (!$user) {
                return false;
            }

            $matched = false;
            foreach ($user->getUserProfils() as $userProfil) {
                $centre = $userProfil->getProfil()?->getCentre()?->value ?? null;
                if ($centre && in_array($centre, $centres, true)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie automatiquement si la route associée à l'aide est une page Admin Only.
     */
    private function isRouteAccessible(Help $help, ?User $user): bool
    {
        $routeTarget = $help->getRouteSlug();

        if (!$routeTarget) {
            return true;
        }

        try {
            $routeCollection = $this->router->getRouteCollection();
            $route = $routeCollection->get($routeTarget);

            if ($route) {
                // 1. ANALYSE DU CHEMIN : Si l'URL de la route commence par /admin, on restreint direct
                $path = $route->getPath(); // Renvoie par exemple "/admin/gestion-aides"
                if (str_starts_with($path, '/admin') || str_contains(strtolower($path), 'admin')) {
                    return $user && in_array('ROLE_ADMIN', $user->getRoles(), true);
                }

                // 2. ANALYSE DU NOM DE LA ROUTE
                if (str_contains(strtolower($routeTarget), 'admin')) {
                    return $user && in_array('ROLE_ADMIN', $user->getRoles(), true);
                }

                // 3. ANALYSE DES CONFIGURATIONS DE SÉCURITÉ INJECTÉES SUR LA ROUTE
                $security = $route->getOption('security') ?? $route->getDefault('_is_granted');
                if ($security && (str_contains($security, 'ROLE_ADMIN') || str_contains($security, 'admin'))) {
                    return $user && in_array('ROLE_ADMIN', $user->getRoles(), true);
                }
            }
        } catch (\Exception $e) {
            // En cas d'erreur de résolution, si le nom contient admin, on bloque
            if (str_contains(strtolower($routeTarget), 'admin')) {
                return $user && in_array('ROLE_ADMIN', $user->getRoles(), true);
            }
        }

        return true;
    }
}