<?php

namespace App\Twig\Components;

use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\FicheMatiere;
use App\Repository\HistoriqueFicheMatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
final class FicheMatiereHeader
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

    public ?FicheMatiere $ficheMatiere = null;

    public string $place = '';
    public bool $hasDemande = true;

    #[LiveProp(writable: true)]
    public ?int $ficheMatiereId = null;

    private array $historiques = [];

    public function __construct(
        private readonly HistoriqueFicheMatiereRepository $historiqueFicheMatiereRepository,
        private readonly ValidationProcessFicheMatiere    $validationProcess,
        private readonly EntityManagerInterface           $em,
        #[Target('fiche')]
        protected WorkflowInterface                       $ficheWorkflow

    ) {
        $this->process = $this->validationProcess->getProcess();
    }

    private function reloadDerived(): void
    {
        // utile si l'action a pu être appelée sans que postMount soit (ré)exécuté
        if (($this->ficheMatiere === null) && $this->ficheMatiereId !== null) {
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
        if ($this->ficheMatiereId !== null) {
            $this->ficheMatiere = $this->em->getRepository(FicheMatiere::class)->find($this->ficheMatiereId);
        }

        $this->init();
        $this->getHistorique();
        $this->processSteps = $this->validationProcess->getProcess();
        $this->validationOptions = $this->validationProcess->getOptionsForStep($this->ficheMatiere);
    }

    private function init(): void
    {
        $this->place = $this->getPlace();
        $this->hasDemande = 'en_cours_redaction' === $this->place;
    }

    private function getPlace(): string
    {

        if (null === $this->ficheMatiere) {
            return 'initialisation_dpe';
        }

        return array_keys($this->ficheWorkflow->getMarking($this->ficheMatiere)->getPlaces())[0];
    }

    public function getHistorique()
    {
        $entriesFicheMatiere = $this->historiqueFicheMatiereRepository->findBy(['ficheMatiere' => $this->ficheMatiere], ['created' => 'ASC']);

        usort($entriesFicheMatiere, fn($a, $b) => (
            ($a->getCreated() ? $a->getCreated()->getTimestamp() : 0)
            <=> ($b->getCreated() ? $b->getCreated()->getTimestamp() : 0)
        ));

        $map = [];
        foreach ($entriesFicheMatiere as $entry) {
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

        $ordered = array_keys($this->process);
        $currentIndex = array_search($this->place, $ordered, true);

        $this->validationSteps = [];
        foreach ($ordered as $index => $stepKey) {
            $metadata = $this->process[$stepKey] ?? [];
            $status = 'pending';

            if ($currentIndex === false) {
                $status = isset($this->historiques[$stepKey]) ? 'completed' : 'pending';
            } elseif ($index < $currentIndex) {
                $status = isset($this->historiques[$stepKey]) ? 'completed' : 'pending';
            } elseif ($index === $currentIndex) {
                $status = 'active';
            }

            $this->validationSteps[$stepKey] = [
                'key' => $stepKey,
                'label' => is_array($metadata) ? ($metadata['label'] ?? $stepKey) : $stepKey,
                'status' => $status,
            ];
        }
    }

}
