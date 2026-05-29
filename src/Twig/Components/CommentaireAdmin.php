<?php

namespace App\Twig\Components;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Utils\CleanTexte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
#[IsGranted('ROLE_ADMIN')]
final class CommentaireAdmin
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?Parcours $parcours = null;

    #[LiveProp]
    public ?Formation $formation = null;

    #[LiveProp(writable: true)]
    public ?string $commentaire = null;

    #[LiveProp(writable: true)]
    public bool $isEditable = false;

    public function __construct(
        protected EntityManagerInterface $entityManager,
    )
    {
    }

    #[PostMount]
    public function onMount(): void
    {

        if ($this->parcours !== null) {
            $this->commentaire = $this->parcours->getCommentaire() ?? '';
        } elseif ($this->formation !== null) {
            $this->commentaire = $this->formation->getCommentaire() ?? '';
        } else {
            throw new \InvalidArgumentException('Either parcours or formation must be provided.');
        }
    }

    #[LiveAction]
    public function edit(): void
    {
        $this->isEditable = true;
    }

    #[LiveAction]
    public function sauvegarde(): void
    {
        $this->isEditable = false;
        $commentaire = CleanTexte::cleanTextArea($this->commentaire, true);
        $commentaire = $commentaire !== null ? trim($commentaire) : null;
        $commentaire = $commentaire === '' ? null : $commentaire;

        if ($this->parcours !== null) {
            $this->parcours->setCommentaire($commentaire);
        } else {
            $this->formation->setCommentaire($commentaire);
        }

        $this->commentaire = $commentaire;
        $this->entityManager->flush();
    }
}
