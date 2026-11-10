<?php

namespace App\EventSubscriber;

use App\I18n\KeyModeContext;
use App\I18n\PageTranslationCollector;
use App\Twig\PageTranslationExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * En mode clés, ajoute au flux Turbo (modales, soumissions) les clés de traduction qu'il a rendues
 * au panneau « Textes de la page » : sans cela, ce panneau ne connaît que les clés de la page initiale.
 */
final readonly class ModalTranslationsStreamSubscriber implements EventSubscriberInterface
{
    private const TURBO_STREAM_MIME = 'text/vnd.turbo-stream.html';

    public function __construct(
        private KeyModeContext $keyMode,
        private PageTranslationCollector $collector,
        private PageTranslationExtension $pageTranslations,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if (!str_starts_with((string) $response->headers->get('Content-Type'), self::TURBO_STREAM_MIME)) {
            return;
        }
        if ($this->collector->getCount() === 0 || !$this->keyMode->isEnabled() || !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $items = '';
        foreach ($this->pageTranslations->getPageTranslations() as $domain => $keys) {
            foreach ($keys as $key => $info) {
                $items .= $this->twig->render('_layout/_translations_item.html.twig', [
                    'domain' => $domain,
                    'key' => (string) $key,
                    'info' => $info,
                ]);
            }
        }

        $response->setContent(
            (string) $response->getContent()
            . '<turbo-stream action="append" target="pageTranslationsItemList"><template>' . $items . '</template></turbo-stream>'
        );
    }
}
