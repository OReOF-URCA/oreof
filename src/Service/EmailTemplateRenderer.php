<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailTemplate;
use Twig\Environment;
use Twig\Extension\SandboxExtension;

/**
 * Rend les templates d'e-mail avec Twig en sandbox.
 * - Supporte HTML + Text + Subject
 * - S'appuie sur VariableRegistry pour construire le contexte sûr
 */
final class EmailTemplateRenderer
{
    public function __construct(
        private readonly Environment      $twig,
        private readonly VariableRegistry $variables
    )
    {
    }

    /**
     * @param array<string,mixed> $context Contexte brut (runtime ou partiel en preview)
     * @param 'runtime'|'preview' $mode Mode de rendu
     * @param array<string,array<string,mixed>> $previewOverrides Overrides par namespace pour la preview
     * @return array{subject:string, html:string, text:string}
     */
    public function render(
        EmailTemplate $tpl,
        array         $context = [],
        string        $mode = 'runtime',
        array   $previewOverrides = [],
        ?string $subjectVariant = null
    ): array
    {
        // Résout un contexte "safe" et aplati (namespaces au 1er niveau)
        $safeVars = $this->variables->resolveForKey($tpl->getWorkflow(), $context, $mode, $previewOverrides);

        // Assure la sandbox pendant tout le rendu (même si non activée globalement)
        /** @var SandboxExtension $sandbox */
        $sandbox = $this->twig->getExtension(SandboxExtension::class);
        $sandbox->enableSandbox();

        try {
            $subjectTpl = $tpl->getSubject(); // défaut
            if ($subjectVariant) {
                $map = $tpl->getSubjects();
                if (isset($map[$subjectVariant]) && is_string($map[$subjectVariant]) && $map[$subjectVariant] !== '') {
                    $subjectTpl = $map[$subjectVariant];
                }
            }
            $subject = $this->twig->createTemplate($subjectTpl ?? '')->render($safeVars);

            $html = $this->twig->createTemplate($tpl->getBodyHtml() ?? '')->render($safeVars);

            $textTpl = $tpl->getBodyText();
            $text = $textTpl !== null && $textTpl !== ''
                ? $this->twig->createTemplate($textTpl)->render($safeVars)
                : trim(strip_tags($html));

            return [
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ];
        } finally {
            $sandbox->disableSandbox();
        }
    }
}
