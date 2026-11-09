<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ModalTranslationsStreamSubscriber;
use App\I18n\KeyModeContext;
use App\I18n\PageTranslationCollector;
use App\Twig\PageTranslationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ModalTranslationsStreamSubscriberTest extends TestCase
{
    private const STREAM = 'text/vnd.turbo-stream.html';

    private function dispatch(string $contentType, bool $keyMode, bool $admin, bool $withKeys, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('i18n_keys', $keyMode);
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $context = new KeyModeContext($stack);
        $collector = new PageTranslationCollector();
        if ($withKeys) {
            $collector->add('modal', 'ui.modal_wrapper.fermer', 'Fermer', 'fr');
        }
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn($admin);
        $twig = new Environment(new ArrayLoader([
            '_layout/_translations_item.html.twig' => '<div data-k="{{ domain }}|{{ key }}|{{ info.value }}"></div>',
        ]));

        $subscriber = new ModalTranslationsStreamSubscriber($context, $collector, new PageTranslationExtension($collector, $context), $checker, $twig);
        $response = new Response('<turbo-stream action="update"></turbo-stream>', 200, ['Content-Type' => $contentType]);
        $subscriber->onResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, $type, $response));

        return $response;
    }

    public function testAppendsKeysToTurboStreamInKeyModeForAdmin(): void
    {
        $content = $this->dispatch(self::STREAM, true, true, true)->getContent();

        $this->assertStringContainsString('<turbo-stream action="append" target="pageTranslationsItemList">', $content);
        $this->assertStringContainsString('modal|ui.modal_wrapper.fermer|Fermer', $content);
    }

    public function testLeavesResponseUntouchedWhenConditionsAreNotMet(): void
    {
        $original = '<turbo-stream action="update"></turbo-stream>';

        $this->assertSame($original, $this->dispatch('text/html', true, true, true)->getContent(), 'pas un flux Turbo');
        $this->assertSame($original, $this->dispatch(self::STREAM, false, true, true)->getContent(), 'mode clés désactivé');
        $this->assertSame($original, $this->dispatch(self::STREAM, true, false, true)->getContent(), 'pas admin');
        $this->assertSame($original, $this->dispatch(self::STREAM, true, true, false)->getContent(), 'aucune clé collectée');
        $this->assertSame($original, $this->dispatch(self::STREAM, true, true, true, HttpKernelInterface::SUB_REQUEST)->getContent(), 'sous-requête');
    }
}
