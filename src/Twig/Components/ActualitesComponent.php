<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/AlerteComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/03/2023 23:10
 */

namespace App\Twig\Components;

use App\Repository\ActualiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('actualites')]
final class ActualitesComponent extends AbstractController
{
    /**
     * Données de démonstration pour le catalogue UI.
     * Si renseigné, ces valeurs sont affichées à la place de la requête BDD.
     *
     * @var array<int, array{datePublication: \DateTimeInterface, titre: string, texte: string}>
     */
    public array $demoActualites = [];

    public function __construct(private ActualiteRepository $actualiteRepository)
    {
    }

    public function getActualites(): array
    {
        if ($this->demoActualites !== []) {
            return $this->demoActualites;
        }

        return $this->actualiteRepository->findBy(['affiche' => true], ['datePublication' => 'DESC']);
    }
}
