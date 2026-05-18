<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Classes\MentionProcess;
use App\Classes\ValidationProcess;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\HistoriqueFormationRepository;
use App\Repository\HistoriqueParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(template: 'components/_domain/formation_header.html.twig')]
final class FormationHeader
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public array $process = [];

    public ?Formation $formation = null;

    public string $place = '';
    public bool $hasDemande = true;

    #[LiveProp(writable: true)]
    public ?int $formationId = null;

    private array $historiques = [];

    public function __construct(
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly MentionProcess                $validationProcess,
        private readonly EntityManagerInterface        $em,

    )
    {
        $this->process = $this->validationProcess->getProcess();
    }

    private function reloadDerived(): void
    {
        // utile si l'action a pu être appelée sans que postMount soit (ré)exécuté
        if (($this->formation === null) && $this->formationId !== null) {
            $this->postMount(); // ou répéter le chargement minimal
            return;
        }
        // recalculer l'historique / étapes
        $this->init();
        $this->getHistorique();
    }

    #[PostMount]
    public function postMount(): void
    {
        if ($this->formationId !== null) {
            $this->formation = $this->em->getRepository(Formation::class)->find($this->formationId);
        }

        $this->init();
        $this->getHistorique();
    }

    private function init(): void
    {
        $this->hasDemande = $this->formation->getEtatReconduction() !== TypeModificationDpeEnum::OUVERT || TypeModificationDpeEnum::FERMETURE_DEFINITIVE; //todo: peut être d'autres cas ou traiter dans le sens ouvert
    }

    public function getHistorique()
    {
        $entriesFormation = $this->historiqueFormationRepository->findBy(['formation' => $this->formation], ['created' => 'ASC']);

        usort($entriesFormation, fn($a, $b) => (
            ($a->getCreated() ? $a->getCreated()->getTimestamp() : 0)
            <=> ($b->getCreated() ? $b->getCreated()->getTimestamp() : 0)
        ));

        $map = [];
        foreach ($entriesFormation as $entry) {
            $key = $entry->getEtape();
            $entryTimestamp = $entry->getCreated() ? $entry->getCreated()->getTimestamp() : 0;

            if (!isset($map[$key])) {
                $map[$key] = $entry;
            } else {
                $existingTs = $map[$key]->getCreated() ? $map[$key]->getCreated()->getTimestamp() : 0;
                // garder l'entrée la plus ancienne (première occurrence)
                if ($entryTimestamp > $existingTs) {
                    $map[$key] = $entry;
                }
            }
        }

        $this->historiques = $map;
    }

    public function status(string $transition): string
    {
        return $this->dateHistorique($transition) === '- à venir -' ? 'pending' : 'completed';
    }

    public function dateHistorique(string $transition): string
    {
        if (array_key_exists($transition, $this->historiques)) {
            if ($this->historiques[$transition]->getEtape() === 'soumis_conseil' && ($this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC || $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE)) {
                if (!array_key_exists('fichier', $this->historiques[$transition]->getComplements())) {
                    return '- à venir -';
                }
            }

            return $this->historiques[$transition]->getDate() !== null ? $this->historiques[$transition]->getDate()->format('d/m/Y') : '- à venir -';
        }
        return '- à venir -';
    }

}
