<?php

declare(strict_types=1);

namespace App\DTO\Workflow;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ValiderConseilData
{
    #[Assert\NotNull(message: 'La date du conseil est obligatoire.')]
    public ?\DateTimeInterface $dateConseil = null;

    #[Assert\File(
        maxSize: '10M',
        extensions: ['pdf'],
        extensionsMessage: 'Le PV doit être un fichier PDF.',
    )]
    public ?UploadedFile $uploadPv = null;

    #[Assert\File(
        maxSize: '10M',
        extensions: ['pdf'],
        extensionsMessage: 'La note explicative doit être un fichier PDF.',
    )]
    public ?UploadedFile $uploadArgumentaire = null;

    public bool $laissezPasser = false;

    #[Assert\Callback]
    public function validateCouncilDocument(ExecutionContextInterface $context): void
    {
        if (null !== $this->uploadPv || $this->laissezPasser) {
            return;
        }

        $context
            ->buildViolation('Déposez le PV du conseil ou indiquez un laissez-passer.')
            ->atPath('uploadPv')
            ->addViolation();
    }
}
