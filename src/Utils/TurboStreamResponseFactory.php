<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Utils/TurboStreamResponseFactory.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/01/2026 19:33
 */

namespace App\Utils;

use App\Dto\TranslatableKey;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class TurboStreamResponseFactory
{
    public function __construct(
        private TranslatorInterface $translator,
        private Environment $twig
    )
    {
    }

    public function appendStreams(string ...$streams): Response
    {
        return new Response(
            implode("\n", $streams),
            200,
            ['Content-Type' => 'text/vnd.turbo-stream.html']
        );
    }

    public function streamToastError(string|TranslatableKey $message, bool $closeModal = false): Response
    {
        return $this->stream('_ui/_toast.stream.html.twig', [
            'message' => $this->translateText($message),
            'closeModal' => $closeModal,
            'toast_type' => 'danger',]);
    }

    public function streamToastSuccess(string|TranslatableKey $message, bool $closeModal = false): Response
    {
        return $this->stream('_ui/_toast.stream.html.twig', [
            'message' => $this->translateText($message),
            'closeModal' => $closeModal,
            'toast_type' => 'success',]);
    }

    public function stream(string $template, array $context = [], int $status = 200): Response
    {
        return new Response(
            $this->twig->render($template, $context),
            $status,
            ['Content-Type' => 'text/vnd.turbo-stream.html']
        );
    }

    /**
     * Ouvre une modal via Turbo Stream (template: `_ui/open.stream.html.twig`).
     * $bodyHtml et $footerHtml sont attendus comme du HTML déjà rendu.
     */
    public function streamOpenModal(
        string|TranslatableKey      $title,
        string|TranslatableKey|null $subtitle,
        string  $bodyHtml,
        string  $footerHtml,
        int     $status = 200
    ): Response
    {
        return $this->stream('_ui/open.stream.html.twig', [
            'title' => $this->translateText($title),
            'subtitle' => $this->translateText($subtitle),
            'body' => $bodyHtml,
            'footer' => $footerHtml,
        ], $status);
    }

    public function streamOpenModalFromTemplates(
        string|TranslatableKey      $title,
        string|TranslatableKey|null $subtitle,
        string  $bodyTemplate,
        array   $bodyContext,
        string $footerTemplate = '_ui/_footer_cancel.html.twig',
        array  $footerContext = [],
        int     $status = 200
    ): Response
    {
        $bodyHtml = $this->twig->render($bodyTemplate, $bodyContext);
        $footerHtml = $this->twig->render($footerTemplate, $footerContext);

        return $this->streamOpenModal($title, $subtitle, $bodyHtml, $footerHtml, $status);
    }

    private function translateText(TranslatableKey|string $title): string
    {
        if ($title instanceof TranslatableKey) {
            return $this->translator->trans($title->key, $title->parameters, $title->domain);
        }

        return $title;
    }
}
