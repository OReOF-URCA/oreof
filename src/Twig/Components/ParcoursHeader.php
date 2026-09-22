<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Classes\ValidationProcess;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\HistoriqueFormationRepository;
use App\Repository\HistoriqueParcoursRepository;
use App\Utils\Access;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveResponder;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
final class ParcoursHeader
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public array $validationSteps = [];

    #[LiveProp(writable: true)]
    public array $validationOptions = [];

    #[LiveProp]
    public array $processSteps = [];

    #[LiveProp(writable: true)]
    public array $process = [];

    public ?Parcours $parcours = null;
    public ?Formation $formation = null;

    public int $progressPercentage = 50;
    public int $completedSteps = 0;
    public DpeParcours $dpeParcours;
    public string $place = '';
    #[LiveProp(writable: true)]
    public ?int $parcoursId = null;
    #[LiveProp(writable: true)]
    public ?int $formationId = null;
    private array $historiques = [];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LiveResponder         $responder,
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly HistoriqueParcoursRepository  $historiqueParcoursRepository,
        private readonly ValidationProcess             $validationProcess,
        private readonly WorkflowOperationInspector    $operationInspector,
        #[Target('dpeParcours')]
        private readonly WorkflowInterface             $dpeParcoursWorkflow,
        private readonly EntityManagerInterface        $em,
    )
    {
        $this->process = $this->validationProcess->getProcess();
    }


    private function reloadDerived(): void
    {
        // utile si l'action a pu être appelée sans que postMount soit (ré)exécuté
        if (($this->parcours === null || $this->formation === null) && $this->parcoursId !== null) {
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
        if ($this->parcoursId !== null) {
            $this->parcours = $this->em->getRepository(Parcours::class)->find($this->parcoursId);
        }
        if ($this->formationId !== null) {
            $this->formation = $this->em->getRepository(Formation::class)->find($this->formationId);
        }

        $this->init();
        $this->getHistorique();
        $this->processSteps = $this->validationProcess->getProcess();
        $this->validationOptions = $this->getVisibleValidationOptions();
    }

    private function getVisibleValidationOptions(): array
    {
        $options = $this->validationProcess->getOptionsForStep($this->dpeParcours);

        foreach ($options as $transition => &$option) {
            $inspection = $this->operationInspector->inspect(
                $this->dpeParcoursWorkflow,
                $this->dpeParcours,
                $transition,
            );

            if (in_array($inspection->status, [OperationStatus::Forbidden, OperationStatus::Unavailable], true)) {
                unset($options[$transition]);
                continue;
            }

            $option['operation_status'] = $inspection->status->value;
            $option['blocking_count'] = count(array_filter(
                $inspection->blockers->all(),
                static fn (OperationBlocker $blocker): bool => $blocker->isBlocking(),
            ));
        }
        unset($option);

        return $options;
    }

    private function init(): void
    {
        $this->dpeParcours = GetDpeParcours::getFromParcours($this->parcours);
        $this->place = $this->getPlace();
    }

    public function isEditable(): bool
    {
        return in_array($this->place, [
            'en_cours_redaction',
            'en_cours_redaction_ss_cfvu',
        ], true) || Access::isOuvert($this->dpeParcours);
    }

    private function getPlace(): string
    {

        if (null === $this->dpeParcours) {
            return 'initialisation_dpe';
        }

        return array_keys($this->dpeParcoursWorkflow->getMarking($this->dpeParcours)->getPlaces())[0];
    }

    public function getHistorique()
    {
        if (null === $this->dpeParcours) {
            return;
        }

        $entriesParcours = $this->historiqueParcoursRepository->findBy(['parcours' => $this->parcours], ['created' => 'ASC']);
        $entriesFormation = $this->historiqueFormationRepository->findBy(['formation' => $this->formation], ['created' => 'ASC']);

        $entries = array_merge($entriesParcours, $entriesFormation);

        usort($entries, fn($a, $b) => (
            ($a->getCreated() ? $a->getCreated()->getTimestamp() : 0)
            <=> ($b->getCreated() ? $b->getCreated()->getTimestamp() : 0)
        ));

        $map = [];
        foreach ($entries as $entry) {
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

        // Construire les étapes ordonnées à partir du process
        $ordered = array_keys($this->process);
        $currentIndex = array_search($this->place, $ordered, true);

        $this->validationSteps = [];
        foreach ($ordered as $i => $stepKey) {
            $label = is_array($this->process[$stepKey] ?? null) ? ($this->process[$stepKey]['label'] ?? $stepKey) : $stepKey;
            $status = 'pending';

            if ($currentIndex === false) {
                // pas de place connue : se baser uniquement sur l'historique
                $status = (isset($this->historiques[$stepKey]) && $this->historiques[$stepKey]->getCreated()) ? 'completed' : 'pending';
            } else {
                if ($i < $currentIndex) {
                    $status = (isset($this->historiques[$stepKey]) && $this->historiques[$stepKey]->getCreated()) ? 'completed' : 'pending';
                } elseif ($i === $currentIndex) {
                    $status = 'active';
                }
            }

            $this->validationSteps[$stepKey] = [
                'key' => $stepKey,
                'label' => $label,
                'status' => $status,
            ];
        }

        $this->completedSteps = $this->getCompletedSteps();
        $this->progressPercentage = (int)round($this->getProgressPercentage());
    }

    public function getCompletedSteps(): int
    {
        return count(array_filter($this->validationSteps, fn($step) => $step['status'] === 'completed'));
    }

    public function getProgressPercentage(): float
    {
        $total = count($this->validationSteps);
        if ($total === 0) {
            return 0;
        }
        return ($this->getCompletedSteps() / $total) * 100;
    }

    #[LiveAction]
    public function reouvrir(#[LiveArg] $key): void
    {
        $url = match ($key) {
            'reouvrir_dpe' => $this->urlGenerator->generate('app_actualite_index', [
                'parcours' => $this->parcoursId,
            ]),
            'historique' => $this->urlGenerator->generate('app_actualite_new', [
                'parcours' => $this->parcoursId,
            ]),
            default => null,
        };

        if ($url === null) {
            return;
        }

        // Event envoyé au navigateur \=\> un controller Stimulus ouvre modal_wrapper
        $this->responder->dispatchBrowserEvent('modal:open', [
            'url' => $url,
            'type' => $key,
            'size' => 'lg',
        ]);

    }
    public function status(string $transition): string
    {
        return $this->dateHistorique($transition) === '- à venir -' ? 'pending' : 'completed';
    }

    public function dateHistorique(string $transition): string
    {
        if (array_key_exists($transition, $this->historiques)) {
            if ($this->historiques[$transition]->getEtape() === 'soumis_conseil'
                && in_array($this->dpeParcours->getEtatReconduction(), [
                    TypeModificationDpeEnum::MODIFICATION_MCCC,
                    TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE,
                ], true)
            ) {
                $complements = $this->historiques[$transition]->getComplements() ?? [];
                $hasPv = isset($complements['fichier']) && '' !== trim((string) $complements['fichier']);
                $hasLaissezPasser = filter_var(
                    $complements['laisserPasser'] ?? false,
                    FILTER_VALIDATE_BOOL,
                );

                if (!$hasPv && !$hasLaissezPasser) {
                    return '- à venir -';
                }
            }

            return $this->historiques[$transition]->getDate() !== null ? $this->historiques[$transition]->getDate()->format('d/m/Y') : '- à venir -';
        }
        return '- à venir -';
    }

}
