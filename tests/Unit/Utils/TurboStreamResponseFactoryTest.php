<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\TurboStreamResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class TurboStreamResponseFactoryTest extends TestCase
{
    private function factory(): TurboStreamResponseFactory
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<turbo-stream></turbo-stream>');

        return new TurboStreamResponseFactory($this->createMock(TranslatorInterface::class), $twig);
    }

    public function testInvalidModalFormReturns422AndKeepsStreamContentType(): void
    {
        $form = new FormView();
        $form->vars['valid'] = false;
        $response = $this->factory()->streamOpenModalFromTemplates('Titre', '', 'body', ['form' => $form]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('text/vnd.turbo-stream.html', $response->headers->get('Content-Type'));
    }

    public function testUnsubmittedOrValidFormRemains200(): void
    {
        $form = new FormView();
        $form->vars['valid'] = true;
        self::assertSame(200, $this->factory()->streamOpenModalFromTemplates('Titre', '', 'body', ['form' => $form])->getStatusCode());
    }

    public function testExplicitStatusIsNotOverwritten(): void
    {
        $form = new FormView();
        $form->vars['valid'] = false;
        self::assertSame(403, $this->factory()->streamOpenModalFromTemplates('Titre', '', 'body', ['form' => $form], status: 403)->getStatusCode());
    }
}
