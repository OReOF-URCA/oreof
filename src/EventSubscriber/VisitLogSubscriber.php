<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Journalise une « vraie » page vue dans le canal `visit` (fichier `<env>.visit-*.log`), lu par VisitorStatsService.
 *
 * Contrairement au log `request` (« Matched route », écrit avant le firewall), on ne retient ici que les
 * consultations humaines : requête principale GET, utilisateur connecté, réponse HTML 200, hors fragments
 * Turbo/XHR/iframe, routes techniques et robots. Exports (PDF/JSON/XML), API, appels de scripts et
 * visiteurs anonymes redirigés vers la connexion n'en font donc pas partie.
 */
final class VisitLogSubscriber implements EventSubscriberInterface
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|curl|wget|python|httpclient|monitor|headless/i';

    public function __construct(
        #[Autowire(service: 'monolog.logger.visit')] private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface                                         $tokenStorage,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->isHumanPageView($request, $response)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $this->logger->info('Page vue', [
            'route' => $request->attributes->get('_route'),
            'user' => $user->getUserIdentifier(),
        ]);
    }

    private function isHumanPageView(Request $request, Response $response): bool
    {
        $route = $request->attributes->get('_route');
        if (!is_string($route) || '' === $route || str_starts_with($route, '_') || str_starts_with($route, 'ux_')) {
            return false;
        }

        if (!$request->isMethod('GET') || Response::HTTP_OK !== $response->getStatusCode()) {
            return false;
        }

        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // Fragments : Turbo Frame, XHR/fetch, contenu chargé dans une iframe
        if ($request->headers->has('Turbo-Frame') || $request->isXmlHttpRequest() || 'iframe' === $request->headers->get('Sec-Fetch-Dest')) {
            return false;
        }

        return 1 !== preg_match(self::BOT_PATTERN, (string) $request->headers->get('User-Agent'));
    }
}
