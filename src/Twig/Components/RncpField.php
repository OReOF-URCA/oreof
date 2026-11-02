<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('RncpField', template: 'components/rncp_field.html.twig')]
final class RncpField
{
    public ?FormView $form = null;
    public ?FormView $inRncpField = null;

    public ?int $parcoursId = null;
    public ?int $formationId = null;
    public ?int $etablissementId = null;

    public string $verifyUrl = '';
    public string $searchUrl = '';
    public ?string $initialCode = null;
    public string $label = 'Code RNCP';
    public bool $required = false;
    public string $help = 'Format : RNCP suivi de chiffres (ex : RNCP34436)';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function mount(
        ?FormView $form = null,
        ?FormView $inRncpField = null,
        ?int $parcoursId = null,
        ?int $formationId = null,
        ?int $etablissementId = null,
        ?string $initialCode = null,
        string $verifyUrl = '',
        string $searchUrl = '',
    ): void {
        $this->form = $form;
        $this->inRncpField = $inRncpField;
        $this->parcoursId = $parcoursId;
        $this->formationId = $formationId;
        $this->etablissementId = $etablissementId;

        $this->verifyUrl = $verifyUrl !== '' ? $verifyUrl : $this->urlGenerator->generate('api_rncp_verify');
        $this->searchUrl = $searchUrl !== '' ? $searchUrl : $this->urlGenerator->generate('api_rncp_search');

        if ($initialCode !== null) {
            $this->initialCode = $initialCode;
        } elseif ($form !== null && !empty($form->vars['value'])) {
            $this->initialCode = (string) $form->vars['value'];
        }
    }
}
